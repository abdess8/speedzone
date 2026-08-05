<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pure financial logic for billing: which orders are billable, and how much each
 * one contributes to a seller invoice. No persistence happens here — this keeps
 * the math testable and reusable by both the preview and the generator.
 *
 * Money rules (confirmed with product):
 *  - Delivered order  -> seller is PAID:    final = order_amount - delivery_fee
 *  - Returned order   -> seller is CHARGED: final = -return_fee
 *  - delivery_fee comes from the order snapshot (order.delivery_price)
 *  - return_fee  comes from the order's sector (sector.return_price), snapshotted now
 *  - net = delivered_amount - delivery_fees_total - return_fees_total
 */
class BillingService
{
    /**
     * Build the query of orders that can be settled for a seller.
     *
     * When a period is provided, the window is read on the order's own delivery
     * or return date — the day the parcel actually reached its last stop — and
     * not on the day a status line happened to be written. The two used to drift
     * apart whenever a status was corrected after the fact or replayed from a
     * partner, which put an order on the invoice of the wrong month. The
     * automatic flow passes no period and bills everything still outstanding.
     *
     * `$storeId` narrows the run to one shop. Billing is store by store so an
     * invoice never mixes two shops and stays readable by a team member who
     * only has access to one of them.
     */
    public function billableOrdersQuery(
        User $seller,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        ?int $storeId = null,
    ): Builder {
        $query = Order::query()
            ->ownedBy($seller->id)
            ->billable();

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        if ($start || $end) {
            $query->where(function (Builder $sub) use ($start, $end) {
                $sub
                    ->where(fn (Builder $delivered) => $this->completedWithin(
                        $delivered, OrderStatus::DELIVERED, 'delivered_at', $start, $end
                    ))
                    ->orWhere(fn (Builder $returned) => $this->completedWithin(
                        $returned, OrderStatus::RETURNED, 'returned_at', $start, $end
                    ));
            });
        }

        return $query;
    }

    /**
     * One branch of the period filter: orders in `$status` whose completion
     * stamp falls inside the window.
     *
     * An order missing its stamp is left out of a dated run rather than swept
     * into it: a settlement that cannot name the day it settles is a dispute
     * waiting to happen. It stays billable, and the undated run still catches
     * it.
     */
    private function completedWithin(
        Builder $query,
        OrderStatus $status,
        string $column,
        ?CarbonInterface $start,
        ?CarbonInterface $end,
    ): Builder {
        $query->where('status', $status->value)->whereNotNull($column);

        if ($start) {
            $query->whereDate($column, '>=', $start->toDateString());
        }

        if ($end) {
            $query->whereDate($column, '<=', $end->toDateString());
        }

        return $query;
    }

    /**
     * Compute the snapshot line for a single order.
     *
     * `completed_at` is the day the parcel was delivered or handed back to the
     * seller: it is what makes an invoice line traceable to a real event rather
     * than to the day the invoice happened to be generated.
     *
     * @return array{order_amount: float, delivery_fee: float, return_fee: float, final_amount: float, status: string, completed_at: ?CarbonInterface}
     */
    public function computeLine(Order $order): array
    {
        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from($order->status);
        $orderAmount = round((float) $order->order_amount, 2);

        if ($status === OrderStatus::RETURNED) {
            $returnFee = round((float) ($order->sector?->return_price ?? 0), 2);

            return [
                'order_amount' => $orderAmount,
                'delivery_fee' => 0.0,
                'return_fee' => $returnFee,
                'final_amount' => round(-$returnFee, 2),
                'status' => $status->value,
                'completed_at' => $order->completedAt(),
            ];
        }

        // Delivered (the only other billable status).
        $deliveryFee = round((float) $order->delivery_price, 2);

        return [
            'order_amount' => $orderAmount,
            'delivery_fee' => $deliveryFee,
            'return_fee' => 0.0,
            'final_amount' => round($orderAmount - $deliveryFee, 2),
            'status' => $status->value,
            'completed_at' => $order->completedAt(),
        ];
    }

    /**
     * Aggregate snapshot totals for a collection of orders.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<string, float|int>
     */
    public function summarize(Collection $orders): array
    {
        $deliveredAmount = 0.0;
        $returnedAmount = 0.0;
        $deliveryFees = 0.0;
        $returnFees = 0.0;

        foreach ($orders as $order) {
            $line = $this->computeLine($order);
            $status = $line['status'];

            if ($status === OrderStatus::RETURNED->value) {
                $returnedAmount += $line['order_amount'];
                $returnFees += $line['return_fee'];
            } else {
                $deliveredAmount += $line['order_amount'];
                $deliveryFees += $line['delivery_fee'];
            }
        }

        $gross = round($deliveredAmount, 2);
        $net = round($deliveredAmount - $deliveryFees - $returnFees, 2);

        return [
            'total_orders_count' => $orders->count(),
            'delivered_amount' => $gross,
            'returned_amount' => round($returnedAmount, 2),
            'delivery_fees_total' => round($deliveryFees, 2),
            'return_fees_total' => round($returnFees, 2),
            'gross_amount' => $gross,
            'net_amount' => $net,
        ];
    }

    /**
     * Produce a non-persisted preview (summary + per-order lines) used before
     * confirming a manual generation, or to show a seller their pending orders.
     *
     * @return array{summary: array<string, float|int>, lines: array<int, array<string, mixed>>}
     */
    public function preview(
        User $seller,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        ?int $storeId = null,
    ): array {
        $orders = $this->billableOrdersQuery($seller, $start, $end, $storeId)
            ->with(['city', 'sector'])
            ->orderBy('id')
            ->get();

        $lines = $orders->map(function (Order $order) {
            $line = $this->computeLine($order);

            return array_merge($line, [
                'id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'customer_full_name' => $order->customer_full_name,
                'city' => $order->city?->name,
                'sector' => $order->sector?->name,
                'created_at' => $order->created_at?->toIso8601String(),
                'completed_at' => $line['completed_at']?->toIso8601String(),
            ]);
        })->all();

        return [
            'summary' => $this->summarize($orders),
            'lines' => $lines,
        ];
    }
}
