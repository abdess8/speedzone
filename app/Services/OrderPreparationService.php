<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The depot workstation for orders picked from a vendor's stock.
 *
 * Those orders never travel to us — the goods are already on our shelves — so
 * they never pass through a pickup. What replaces it is this: an agent picks the
 * lines, packs the box and declares the order prepared, one at a time or by
 * sweeping a QR scanner over a trolley of labels.
 *
 * Marking an order prepared is a plain status transition, so authorisation,
 * journalling and the routing that follows all stay in
 * {@see OrderTransitionService} rather than being reimplemented here.
 */
class OrderPreparationService
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
    ) {}

    /**
     * Orders waiting to be picked and packed.
     *
     * Crosses the store boundary on purpose: the queue belongs to our depot, not
     * to a vendor, and an agent works everybody's parcels from the same trolley.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    public function queue(array $filters = [])
    {
        return Order::acrossStores()
            ->where('status', OrderStatus::AWAITING_PREPARATION->value)
            ->with(['items', 'city', 'sector', 'seller', 'store', 'stockHubCity'])
            ->when(
                ! empty($filters['hub_city_id']),
                fn ($query) => $query->where('stock_hub_city_id', (int) $filters['hub_city_id'])
            )
            ->when(
                ! empty($filters['search']),
                fn ($query) => $query->where('tracking_number', 'like', '%'.$filters['search'].'%')
            )
            // Oldest first: a preparation queue is a queue.
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Check a single scanned label without acting on it.
     *
     * @return array{valid: bool, message: string, order: array<string, mixed>|null}
     */
    public function validateScan(User $actor, string $trackingNumber): array
    {
        $order = $this->find($trackingNumber);

        if ($order === null) {
            return [
                'valid' => false,
                'message' => __('preparation.errors.unknown_order'),
                'order' => null,
            ];
        }

        $payload = $this->scanPayload($order);

        if (! $actor->can('updateStatus', $order)) {
            return [
                'valid' => false,
                'message' => __('preparation.errors.not_yours'),
                'order' => $payload,
            ];
        }

        if ($order->status !== OrderStatus::AWAITING_PREPARATION) {
            return [
                'valid' => false,
                // The usual reason a label is refused: somebody already packed it.
                'message' => __('preparation.errors.wrong_status', [
                    'status' => $order->status->label(),
                ]),
                'order' => $payload,
            ];
        }

        return [
            'valid' => true,
            'message' => __('preparation.scanner.valid'),
            'order' => $payload,
        ];
    }

    /**
     * Declare a batch of orders prepared, addressed by database id.
     *
     * Drives the checkbox selection on the queue screen.
     *
     * @param  array<int, int|string>  $ids
     * @return array{prepared: int, skipped: int}
     */
    public function prepareByIds(User $actor, array $ids): array
    {
        $orders = Order::acrossStores()
            ->whereIn('id', array_map('intval', $ids))
            ->get();

        return $this->prepareAll($actor, $orders);
    }

    /**
     * Declare a scanned batch prepared, addressed by the number on the label.
     *
     * Unknown labels fail the whole batch rather than being skipped: a code the
     * agent scanned and we cannot find means the trolley and the screen disagree,
     * and silently packing the rest would hide that.
     *
     * @param  array<int, string>  $trackingNumbers
     * @return array{prepared: int, skipped: int}
     */
    public function prepareByTracking(User $actor, array $trackingNumbers): array
    {
        $wanted = array_values(array_unique(array_map('trim', $trackingNumbers)));

        $orders = Order::acrossStores()
            ->whereIn('tracking_number', $wanted)
            ->get();

        $missing = array_diff($wanted, $orders->pluck('tracking_number')->all());

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'orders' => __('preparation.errors.unknown_in_batch', [
                    'codes' => implode(', ', $missing),
                ]),
            ]);
        }

        return $this->prepareAll($actor, $orders);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{prepared: int, skipped: int}
     */
    private function prepareAll(User $actor, Collection $orders): array
    {
        $prepared = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            // An order somebody else packed a second earlier is not a failure
            // worth stopping the trolley for.
            if ($order->status !== OrderStatus::AWAITING_PREPARATION || ! $actor->can('updateStatus', $order)) {
                $skipped++;

                continue;
            }

            $this->transitions->transition(
                $order,
                OrderStatus::PREPARED->value,
                $actor,
                'Picked and packed at the depot.',
            );

            $prepared++;
        }

        return ['prepared' => $prepared, 'skipped' => $skipped];
    }

    private function find(string $trackingNumber): ?Order
    {
        $trackingNumber = trim($trackingNumber);

        if ($trackingNumber === '') {
            return null;
        }

        return Order::acrossStores()
            ->with(['items', 'city', 'seller', 'stockHubCity'])
            ->where('tracking_number', $trackingNumber)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function scanPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'customer' => $order->customer_full_name,
            'city' => $order->city?->name,
            'hub_city' => $order->stockHubCity?->name,
            'seller' => $order->seller?->full_name,
            'units' => (int) $order->items->sum('quantity'),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
        ];
    }
}
