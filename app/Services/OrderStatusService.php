<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PickupRequestStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    /**
     * Automatically transition an order to IN_DELIVERY_CITY when the package
     * is already in the destination city and no inter-city transfer is needed.
     *
     * Conditions:
     * - Pickup request is completed (IN_DEPOT)
     * - Order status is IN_DEPOT
     * - Seller pickup city equals order delivery city
     */
    public function handleAutoCityDeliveryTransition(Order $order): bool
    {
        $order->refresh();
        $order->loadMissing(['pickupRequest', 'seller.city', 'city']);

        if (! $this->shouldAutoTransitionToDeliveryCity($order)) {
            return false;
        }

        return DB::transaction(function () use ($order): bool {
            $order->refresh();

            if ($order->status !== OrderStatus::IN_DEPOT) {
                return false;
            }

            $order->update(['status' => OrderStatus::IN_DELIVERY_CITY->value]);
            $order->recordStatus(
                OrderStatus::IN_DELIVERY_CITY,
                null,
                'Automatic transition: package already in destination city.',
                isSystem: true,
                pickupRequestId: $order->pickup_request_id,
            );

            return true;
        });
    }

    /**
     * @param  iterable<int, Order>  $orders
     */
    public function handleAutoCityDeliveryTransitionForMany(iterable $orders): void
    {
        foreach ($orders as $order) {
            $this->handleAutoCityDeliveryTransition($order);
        }
    }

    public function shouldAutoTransitionToDeliveryCity(Order $order): bool
    {
        if ($order->status !== OrderStatus::IN_DEPOT) {
            return false;
        }

        $pickup = $order->pickupRequest;

        if (! $pickup) {
            return false;
        }

        $pickupStatus = $pickup->status instanceof PickupRequestStatus
            ? $pickup->status
            : PickupRequestStatus::tryFrom((string) $pickup->status);

        if ($pickupStatus !== PickupRequestStatus::IN_DEPOT) {
            return false;
        }

        $pickupCityId = $order->seller?->city_id;
        $deliveryCityId = $order->city_id;

        if (! $pickupCityId || ! $deliveryCityId) {
            return false;
        }

        return (int) $pickupCityId === (int) $deliveryCityId;
    }
}
