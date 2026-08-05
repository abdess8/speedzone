<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly TrackingNumberGenerator $trackingNumbers,
        private readonly OrderAuditService $auditService,
        private readonly OrderStockService $stockService,
    ) {}

    /**
     * Create a new order on behalf of the authenticated seller.
     *
     * Ownership goes to the vendor account, so an order keyed in by a team
     * member is billed to — and stays readable by — his employer. The store is
     * filled in automatically by BelongsToStore from the active store.
     *
     * When the payload carries catalog lines, they are written and their stock
     * debited in the same transaction: an order that cannot be served must not
     * exist, and stock that left the shelf must always have an order to point at.
     *
     * Those same lines also decide where the order starts. Goods already sitting
     * in our depot have nothing to be collected from the vendor, so the parcel
     * skips the pickup leg and lands in the preparation queue instead.
     *
     * @param  array<string, mixed>  $data  Validated order payload.
     */
    public function create(array $data, User $seller): Order
    {
        return DB::transaction(function () use ($data, $seller): Order {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $data['delivery_price'] = $this->resolveDeliveryPrice($data);
            $fromStock = $items !== [];

            $order = new Order($data);
            $order->seller_id = $seller->accountOwnerId();
            $order->tracking_number = $this->trackingNumbers->generate();
            $order->status = $fromStock
                ? OrderStatus::AWAITING_PREPARATION->value
                : OrderStatus::CREATED->value;

            if ($fromStock) {
                $order->stock_hub_city_id = $this->resolveStockHubCityId();
            }

            $order->save();

            if ($fromStock) {
                $this->stockService->attach($order, $items, $seller);
            }

            $order->recordStatus(
                $order->status,
                $seller,
                $fromStock ? 'Order created from stock.' : 'Order created.'
            );

            return $order->load(['city', 'sector', 'seller', 'items']);
        });
    }

    /**
     * The depot a stock order ships out of.
     *
     * Snapshotted onto the order rather than read back through the shop: the
     * shop may be moved to another depot once it is empty, and a parcel already
     * in flight has to keep leaving from where it was actually picked.
     *
     * A shop with stock but no depot is reachable — importing a catalog credits
     * opening quantities without any inbound shipment — so this fails loudly
     * rather than guessing a city the goods are not in.
     */
    private function resolveStockHubCityId(): int
    {
        $storeId = app(StoreContext::class)->id();

        $hubCityId = $storeId === null
            ? null
            : Store::query()->whereKey($storeId)->value('stock_hub_city_id');

        if ($hubCityId === null) {
            throw ValidationException::withMessages([
                'items' => __('stock.errors.no_depot'),
            ]);
        }

        return (int) $hubCityId;
    }

    /**
     * Update an editable order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($order, $data, $actor): Order {
            if (array_key_exists('delivery_price', $data) || array_key_exists('sector_id', $data)) {
                $data['delivery_price'] = $this->resolveDeliveryPrice($data, $order);
            }

            $this->auditService->recordChanges($order, $data, $actor);

            $order->fill($data)->save();

            return $order->refresh()->load(['city', 'sector', 'seller']);
        });
    }

    /**
     * Extract the fields that can be pre-filled when cloning an order.
     *
     * @return array<string, mixed>
     */
    public function clonePayload(Order $order): array
    {
        $payment = $order->payment_method instanceof PaymentMethod
            ? $order->payment_method
            : PaymentMethod::resolve((string) $order->payment_method);

        $payload = [
            'customer_first_name' => $order->customer_first_name,
            'customer_last_name' => $order->customer_last_name,
            'customer_phone' => $order->customer_phone,
            'customer_address' => $order->customer_address,
            'city_id' => $order->city_id,
            'sector_id' => $order->sector_id,
            'is_fragile' => (bool) $order->is_fragile,
            'can_be_opened' => (bool) $order->can_be_opened,
            'option_exchange' => (bool) $order->option_exchange,
            'notes' => $order->notes,
            'payment_method' => $payment->value,
            'delivery_price' => (float) $order->delivery_price,
            'delivery_included' => (bool) $order->delivery_included,
            'order_value' => $order->order_value !== null ? (float) $order->order_value : null,
            'order_amount' => null,
        ];

        if ($payment === PaymentMethod::CASH && $order->order_amount !== null) {
            $payload['order_amount'] = (float) $order->order_amount;
        }

        return $payload;
    }

    /**
     * Resolve the delivery price for an order.
     *
     * The destination sector is the source of truth for pricing. A caller may
     * still override it explicitly (e.g. a negotiated rate).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDeliveryPrice(array $data, ?Order $order = null): float
    {
        if (isset($data['delivery_price']) && $data['delivery_price'] !== null && $data['delivery_price'] !== '') {
            return (float) $data['delivery_price'];
        }

        $sectorId = $data['sector_id'] ?? $order?->sector_id;
        $sector = $sectorId ? Sector::find($sectorId) : null;

        return (float) ($sector?->delivery_price ?? 0);
    }
}
