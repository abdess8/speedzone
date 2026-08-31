<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PickupRequestStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    public function __construct(
        private readonly OrderDriverAutoAssignmentService $driverAutoAssignment,
    ) {}

    /**
     * Route a freshly prepared stock order.
     *
     * A packed parcel whose depot already sits in the customer's city has no
     * journey left to make, so it jumps straight to the delivery city and picks
     * up a local driver — the transfer reception that normally does that never
     * happens for it.
     *
     * Anything bound for another city stays PREPARED and waits to be loaded onto
     * an inter-city transfer alongside the parcels sitting in the depot.
     */
    public function handlePreparedRouting(Order $order): bool
    {
        $order->refresh();

        if (! $this->isHomeDelivery($order)) {
            return false;
        }

        $moved = DB::transaction(function () use ($order): bool {
            $order->refresh();

            if ($order->status !== OrderStatus::PREPARED) {
                return false;
            }

            $order->update(['status' => OrderStatus::IN_DELIVERY_CITY->value]);
            $order->recordStatus(
                OrderStatus::IN_DELIVERY_CITY,
                null,
                'Automatic transition: parcel prepared in its delivery city.',
                isSystem: true,
            );

            return true;
        });

        if ($moved) {
            $this->driverAutoAssignment->assignForDeliveryCity($order->refresh());
        }

        return $moved;
    }

    /**
     * Whether a prepared parcel is already standing in the city it must reach.
     */
    public function isHomeDelivery(Order $order): bool
    {
        if ($order->status !== OrderStatus::PREPARED) {
            return false;
        }

        $hubCityId = $order->stock_hub_city_id;
        $deliveryCityId = $order->city_id;

        if (! $hubCityId || ! $deliveryCityId) {
            return false;
        }

        return (int) $hubCityId === (int) $deliveryCityId;
    }

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
