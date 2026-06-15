<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PickupRequestStatus;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PickupRequestService
{
    public function __construct(
        private readonly PickupReferenceGenerator $references,
        private readonly OrderStatusService $orderStatus,
    ) {}

    /**
     * Create a pickup request grouping multiple CREATED orders.
     *
     * @param  array<int, int>  $orderIds
     *
     * @throws ValidationException
     */
    public function create(User $seller, array $orderIds, string $pickupAddress, ?string $notes = null): PickupRequest
    {
        if (! $seller->hasPermission('pickup_requests.create')) {
            throw new AuthorizationException('Missing permission: pickup_requests.create');
        }

        $orders = $this->resolveEligibleOrders($seller, $orderIds);

        return DB::transaction(function () use ($seller, $orders, $pickupAddress, $notes): PickupRequest {
            $totalAmount = round((float) $orders->sum('order_amount'), 2);

            $pickup = PickupRequest::create([
                'reference' => $this->references->generate(),
                'created_by' => $seller->id,
                'status' => PickupRequestStatus::WAITING_FOR_PICKUP,
                'pickup_address' => $pickupAddress,
                'number_of_packages' => $orders->count(),
                'total_orders_amount' => $totalAmount,
                'notes' => $notes,
            ]);

            $pickup->recordStatus(
                PickupRequestStatus::WAITING_FOR_PICKUP,
                $seller,
                null,
                'Pickup request created.'
            );

            $this->syncOrdersToPickup($pickup, $orders, PickupRequestStatus::WAITING_FOR_PICKUP, $seller);

            return $pickup->load(['creator', 'orders.city', 'orders.sector']);
        });
    }

    public function assignDriver(PickupRequest $pickup, User $driver, User $actor): PickupRequest
    {
        if (! $actor->hasPermission('pickup_requests.assign')) {
            throw new AuthorizationException('Missing permission: pickup_requests.assign');
        }

        if (! $driver->isDriver()) {
            throw ValidationException::withMessages([
                'driver_id' => 'The selected user is not a driver.',
            ]);
        }

        $pickup->update(['assigned_to' => $driver->id]);

        $pickup->recordStatus(
            $pickup->status,
            $actor,
            $pickup->status instanceof PickupRequestStatus ? $pickup->status->value : $pickup->status,
            "Assigned to {$driver->full_name}."
        );

        return $pickup->refresh()->load(['creator', 'assignee', 'orders.city', 'orders.sector']);
    }

    public function applyStatus(
        PickupRequest $pickup,
        PickupRequestStatus $toStatus,
        User $actor,
        ?string $comment = null,
        ?string $fromStatus = null
    ): PickupRequest {
        $from = $fromStatus ?? ($pickup->status instanceof PickupRequestStatus ? $pickup->status->value : $pickup->status);

        return DB::transaction(function () use ($pickup, $toStatus, $actor, $comment, $from): PickupRequest {
            $pickup->update(['status' => $toStatus->value]);
            $pickup->recordStatus($toStatus, $actor, $from, $comment);

            $orders = $pickup->orders()->get();

            if ($toStatus === PickupRequestStatus::CANCELLED) {
                $this->releaseOrders($orders, $actor, $comment);
            } else {
                $this->syncOrdersToPickup($pickup, $orders, $toStatus, $actor, $comment);
            }

            return $pickup->refresh()->load(['creator', 'assignee', 'orders.city', 'orders.sector', 'statusHistories.changedBy']);
        });
    }

    /**
     * Bulk-update orders from QR scan batch (driver/admin pickup flow).
     *
     * @param  array<int, string>  $trackingNumbers
     * @return array{updated: int, orders: Collection<int, Order>}
     */
    public function bulkScanPickup(User $actor, array $trackingNumbers, PickupRequestStatus $toStatus): array
    {
        return app(PickupScanService::class)->bulkStatusUpdate(
            $actor,
            $trackingNumbers,
            $toStatus->value
        );
    }

    /**
     * @param  array<int, int>  $orderIds
     */
    private function resolveEligibleOrders(User $seller, array $orderIds): Collection
    {
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));

        if ($orderIds === []) {
            throw ValidationException::withMessages([
                'order_ids' => 'Select at least one order for pickup.',
            ]);
        }

        $orders = Order::query()
            ->eligibleForPickup($seller->id)
            ->whereIn('id', $orderIds)
            ->get();

        if ($orders->count() !== count($orderIds)) {
            throw ValidationException::withMessages([
                'order_ids' => 'One or more orders are invalid, not owned by you, or not in CREATED status.',
            ]);
        }

        return $orders;
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function syncOrdersToPickup(
        PickupRequest $pickup,
        Collection $orders,
        PickupRequestStatus $pickupStatus,
        User $actor,
        ?string $comment = null
    ): void {
        $orderStatus = $pickupStatus->orderStatus();

        foreach ($orders as $order) {
            $order->update([
                'pickup_request_id' => $pickup->id,
                'status' => $orderStatus?->value ?? $order->status,
            ]);

            if ($orderStatus) {
                $order->recordStatus(
                    $orderStatus,
                    $actor,
                    $comment ?? "Pickup {$pickup->reference} status: {$pickupStatus->label()}.",
                    pickupRequestId: $pickup->id,
                );
            }

            if ($orderStatus === OrderStatus::IN_DEPOT) {
                $this->orderStatus->handleAutoCityDeliveryTransition($order);
            }
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function releaseOrders(Collection $orders, User $actor, ?string $comment = null): void
    {
        foreach ($orders as $order) {
            $order->update([
                'pickup_request_id' => null,
                'status' => OrderStatus::CREATED->value,
            ]);

            $order->recordStatus(OrderStatus::CREATED, $actor, $comment ?? 'Pickup request cancelled.');
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function driverOptions(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::DRIVER))
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email', 'phone_number']);
    }
}
