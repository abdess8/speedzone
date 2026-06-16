<?php

namespace App\Services;

use App\Enums\DriverTransactionType;
use App\Models\DriverTransaction;
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
 * Rules (confirmed with product):
 *  - Each delivered order earns the driver the sector driver price, snapshotted
 *    at delivery time (driver_transactions.amount / driver_price_snapshot).
 *  - Bonuses / adjustments add to the balance, penalties subtract from it.
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
     * Aggregate snapshot totals for a collection of transactions.
     *
     * @param  Collection<int, DriverTransaction>  $transactions
     * @return array<string, float|int>
     */
    public function summarize(Collection $transactions): array
    {
        $deliveriesCount = 0;
        $deliveryTotal = 0.0;
        $bonusTotal = 0.0;
        $penaltyTotal = 0.0;
        $adjustmentTotal = 0.0;

        foreach ($transactions as $transaction) {
            $type = $transaction->transaction_type instanceof DriverTransactionType
                ? $transaction->transaction_type
                : DriverTransactionType::from($transaction->transaction_type);
            $amount = round((float) $transaction->amount, 2);

            match ($type) {
                DriverTransactionType::DELIVERY_PAYMENT => [$deliveriesCount++, $deliveryTotal += $amount],
                DriverTransactionType::BONUS => $bonusTotal += $amount,
                DriverTransactionType::PENALTY => $penaltyTotal += abs($amount),
                DriverTransactionType::ADJUSTMENT => $adjustmentTotal += $amount,
            };
        }

        $total = round($deliveryTotal + $bonusTotal + $adjustmentTotal - $penaltyTotal, 2);

        return [
            'deliveries_count' => $deliveriesCount,
            'transactions_count' => $transactions->count(),
            'delivery_total' => round($deliveryTotal, 2),
            'bonus_total' => round($bonusTotal, 2),
            'penalty_total' => round($penaltyTotal, 2),
            'adjustment_total' => round($adjustmentTotal, 2),
            'total_amount' => $total,
        ];
    }

    /**
     * Produce a non-persisted preview (summary + per-transaction lines) used
     * before confirming a manual generation, or to show a driver their pending
     * earnings.
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

        return [
            'id' => $transaction->id,
            'order_id' => $transaction->order_id,
            'tracking_number' => $order?->tracking_number,
            'customer_full_name' => $order?->customer_full_name,
            'city' => $order?->city?->name,
            'sector' => $transaction->sector?->name ?? $order?->sector?->name,
            'transaction_type' => $type->value,
            'transaction_type_label' => $type->label(),
            'amount' => round((float) $transaction->amount, 2),
            'note' => $transaction->note,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    /**
     * Earnings statistics for the driver dashboard (today / this week / month).
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
