<?php

namespace App\Services;

use App\Enums\DriverTransactionType;
use App\Enums\PaymentMethod;
use App\Models\DriverTransaction;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pure financial logic for driver settlement: which transactions are billable
 * and how much each contributes to a driver invoice. No persistence happens
 * here so the math is reusable by both the preview and the generator.
 *
 * A driver invoice is a cash discharge, not a payout of commissions:
 *  - Cash orders: the driver collected `order.total_amount` from the customer.
 *  - Card orders: the customer already paid, so nothing was collected.
 *  - The driver keeps the sector driver price snapshotted at delivery
 *    (`driver_price_snapshot`).
 *  - He remits collected − commission. Bonuses / adjustments reduce what he
 *    owes; penalties increase it.
 *  - A transaction is only billable once it is CONFIRMED and not yet invoiced.
 */
class DriverBillingService
{
    /**
     * Build the query of transactions that can be settled for a driver.
     *
     * When a period is provided, only transactions created inside the window are
     * included. The automatic flow passes no period and bills everything still
     * outstanding.
     */
    public function billableTransactionsQuery(User $driver, ?CarbonInterface $start = null, ?CarbonInterface $end = null): Builder
    {
        $query = DriverTransaction::query()
            ->forDriver($driver->id)
            ->billable();

        if ($start) {
            $query->whereDate('created_at', '>=', $start->toDateString());
        }
        if ($end) {
            $query->whereDate('created_at', '<=', $end->toDateString());
        }

        return $query;
    }

    /**
     * Compute the discharge line for a single transaction.
     *
     * `amount` is what the driver must remit for this row (negative means
     * SpeedZone owes the driver, typically a card delivery or a bonus).
     *
     * @return array{
     *     collected_amount: float,
     *     commission: float,
     *     amount: float,
     *     transaction_type: string
     * }
     */
    public function computeLine(DriverTransaction $transaction): array
    {
        $type = $transaction->transaction_type instanceof DriverTransactionType
            ? $transaction->transaction_type
            : DriverTransactionType::from($transaction->transaction_type);
        $signed = round((float) $transaction->amount, 2);

        if ($type !== DriverTransactionType::DELIVERY_PAYMENT) {
            return [
                'collected_amount' => 0.0,
                'commission' => 0.0,
                'amount' => round(-$signed, 2),
                'transaction_type' => $type->value,
            ];
        }

        $order = $transaction->relationLoaded('order') ? $transaction->order : $transaction->order;
        $collected = $this->collectedAmount($order);
        $commission = round((float) ($transaction->driver_price_snapshot ?: $transaction->amount), 2);

        return [
            'collected_amount' => $collected,
            'commission' => $commission,
            'amount' => round($collected - $commission, 2),
            'transaction_type' => $type->value,
        ];
    }

    /**
     * Aggregate snapshot totals for a collection of transactions.
     *
     * @param  Collection<int, DriverTransaction>  $transactions
     * @return array<string, float|int>
     */
    public function summarize(Collection $transactions): array
    {
        $deliveriesCount = 0;
        $collectedAmount = 0.0;
        $commissionTotal = 0.0;
        $bonusTotal = 0.0;
        $penaltyTotal = 0.0;
        $adjustmentTotal = 0.0;
        $dueAmount = 0.0;

        foreach ($transactions as $transaction) {
            $type = $transaction->transaction_type instanceof DriverTransactionType
                ? $transaction->transaction_type
                : DriverTransactionType::from($transaction->transaction_type);
            $line = $this->computeLine($transaction);
            $dueAmount += $line['amount'];

            match ($type) {
                DriverTransactionType::DELIVERY_PAYMENT => [
                    $deliveriesCount++,
                    $collectedAmount += $line['collected_amount'],
                    $commissionTotal += $line['commission'],
                ],
                DriverTransactionType::BONUS => $bonusTotal += abs((float) $transaction->amount),
                DriverTransactionType::PENALTY => $penaltyTotal += abs((float) $transaction->amount),
                DriverTransactionType::ADJUSTMENT => $adjustmentTotal += (float) $transaction->amount,
            };
        }

        return [
            'deliveries_count' => $deliveriesCount,
            'transactions_count' => $transactions->count(),
            'collected_amount' => round($collectedAmount, 2),
            'commission_total' => round($commissionTotal, 2),
            'delivery_total' => round($commissionTotal, 2),
            'bonus_total' => round($bonusTotal, 2),
            'penalty_total' => round($penaltyTotal, 2),
            'adjustment_total' => round($adjustmentTotal, 2),
            'total_amount' => round($dueAmount, 2),
        ];
    }

    /**
     * Produce a non-persisted preview (summary + per-transaction lines) used
     * before confirming a manual generation, or to show a driver their pending
     * discharge.
     *
     * @return array{summary: array<string, float|int>, lines: array<int, array<string, mixed>>}
     */
    public function preview(User $driver, ?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        $transactions = $this->billableTransactionsQuery($driver, $start, $end)
            ->with(['order.city', 'order.sector', 'sector'])
            ->orderBy('id')
            ->get();

        return [
            'summary' => $this->summarize($transactions),
            'lines' => $transactions->map(fn (DriverTransaction $tx) => $this->line($tx))->all(),
        ];
    }

    /**
     * Map a transaction to a UI/PDF line.
     *
     * @return array<string, mixed>
     */
    public function line(DriverTransaction $transaction): array
    {
        $type = $transaction->transaction_type instanceof DriverTransactionType
            ? $transaction->transaction_type
            : DriverTransactionType::from($transaction->transaction_type);
        $order = $transaction->relationLoaded('order') ? $transaction->order : $transaction->order;
        $computed = $this->computeLine($transaction);

        return [
            'id' => $transaction->id,
            'order_id' => $transaction->order_id,
            'tracking_number' => $order?->tracking_number,
            'customer_full_name' => $order?->customer_full_name,
            'city' => $order?->city?->name,
            'sector' => $transaction->sector?->name ?? $order?->sector?->name,
            'transaction_type' => $type->value,
            'transaction_type_label' => $type->label(),
            'collected_amount' => $computed['collected_amount'],
            'commission' => $computed['commission'],
            'amount' => $computed['amount'],
            'note' => $transaction->note,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    /**
     * Earnings statistics for the driver dashboard (today / this week / month).
     *
     * These remain the driver's commission, not the cash he remits.
     *
     * @return array<string, array<string, float|int>>
     */
    public function dashboardStats(User $driver, ?CarbonInterface $asOf = null): array
    {
        $now = $asOf ? Carbon::parse($asOf) : Carbon::now();

        return [
            'today' => $this->statsForRange($driver, $now->copy()->startOfDay(), $now->copy()->endOfDay()),
            'week' => $this->statsForRange($driver, $now->copy()->startOfWeek(), $now->copy()->endOfWeek()),
            'month' => $this->statsForRange($driver, $now->copy()->startOfMonth(), $now->copy()->endOfMonth()),
        ];
    }

    /**
     * Cash the driver actually took from the customer. Card payments were
     * already settled with the seller, so they contribute nothing here.
     */
    private function collectedAmount(?Order $order): float
    {
        if (! $order) {
            return 0.0;
        }

        $payment = $order->payment_method instanceof PaymentMethod
            ? $order->payment_method
            : PaymentMethod::resolve((string) $order->payment_method);

        if (! $payment->requiresCashCollection()) {
            return 0.0;
        }

        return round((float) $order->total_amount, 2);
    }

    /**
     * @return array<string, float|int>
     */
    private function statsForRange(User $driver, CarbonInterface $start, CarbonInterface $end): array
    {
        $base = DriverTransaction::query()
            ->forDriver($driver->id)
            ->whereBetween('created_at', [$start, $end]);

        $deliveries = (clone $base)
            ->where('transaction_type', DriverTransactionType::DELIVERY_PAYMENT->value)
            ->count();

        $earned = (clone $base)
            ->where('transaction_type', '!=', DriverTransactionType::PENALTY->value)
            ->sum('amount');

        $penalties = (clone $base)
            ->where('transaction_type', DriverTransactionType::PENALTY->value)
            ->sum('amount');

        return [
            'deliveries' => (int) $deliveries,
            'amount' => round((float) $earned - abs((float) $penalties), 2),
        ];
    }
}
