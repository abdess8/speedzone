<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly TrackingNumberGenerator $trackingNumbers) {}

    /**
     * Create a new order on behalf of the authenticated seller.
     *
     * @param  array<string, mixed>  $data  Validated order payload.
     */
    public function create(array $data, User $seller): Order
    {
        return DB::transaction(function () use ($data, $seller): Order {
            $data['delivery_price'] = $this->resolveDeliveryPrice($data);

            $order = new Order($data);
            $order->seller_id = $seller->id;
            $order->tracking_number = $this->trackingNumbers->generate();
            $order->status = OrderStatus::CREATED->value;
            $order->save();

            $order->recordStatus(
                OrderStatus::CREATED,
                $seller,
                'Order created.'
            );

            return $order->load(['city', 'seller']);
        });
    }

    /**
     * Update an editable order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data): Order
    {
        if (array_key_exists('delivery_price', $data) || array_key_exists('city_id', $data)) {
            $data['delivery_price'] = $this->resolveDeliveryPrice($data, $order);
        }

        $order->fill($data)->save();

        return $order->refresh()->load(['city', 'seller']);
    }

    /**
     * Fall back to the destination city's default delivery price when none is given.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDeliveryPrice(array $data, ?Order $order = null): float
    {
        if (isset($data['delivery_price']) && $data['delivery_price'] !== null && $data['delivery_price'] !== '') {
            return (float) $data['delivery_price'];
        }

        $cityId = $data['city_id'] ?? $order?->city_id;
        $city = $cityId ? City::find($cityId) : null;

        return (float) ($city?->delivery_price ?? 0);
    }
}
