<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PickupRequestStatus;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PickupScanService
{
    public const MODE_DRIVER = 'driver';

    public const MODE_ADMIN = 'admin';

    public function __construct(private readonly OrderStatusService $orderStatus) {}

    public function resolveScannerMode(User $actor): string
    {
        $canPickup = $actor->hasPermission('pickup_requests.pickup');
        $canChangeStatus = $actor->hasPermission('pickup_requests.change_status');

        if (! $canPickup && ! $canChangeStatus) {
            throw new AuthorizationException('You are not allowed to perform pickup scans.');
        }

        if ($canChangeStatus && (! $canPickup || ! $actor->isDriver())) {
            return self::MODE_ADMIN;
        }

        return self::MODE_DRIVER;
    }

    public function targetPickupStatus(User $actor): PickupRequestStatus
    {
        return $this->resolveScannerMode($actor) === self::MODE_ADMIN
            ? PickupRequestStatus::IN_DEPOT
            : PickupRequestStatus::PICKED_UP;
    }

    /**
     * @return array{success: bool, valid?: bool, message: string, order?: array<string, mixed>}
     */
    public function validateScan(User $actor, string $trackingNumber): array
    {
        Gate::forUser($actor)->authorize('scan', PickupRequest::class);

        $trackingNumber = trim($trackingNumber);

        if ($trackingNumber === '') {
            return $this->failure('Invalid tracking number.');
        }

        $order = Order::query()
            ->with(['pickupRequest', 'city'])
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (! $order) {
            return $this->failure('Order not found.');
        }

        $validation = $this->validateOrderForScan($actor, $order);

        if (! $validation['valid']) {
            return $this->failure($validation['message']);
        }

        Gate::forUser($actor)->authorize('scanForPickup', $order);

        return [
            'success' => true,
            'valid' => true,
            'message' => 'Valid',
            'order' => $this->formatOrder($order),
        ];
    }

    /**
     * @param  array<int, string>  $trackingNumbers
     * @return array{updated: int, orders: Collection<int, Order>}
     */
    public function bulkStatusUpdate(User $actor, array $trackingNumbers, ?string $requestedStatus = null): array
    {
        Gate::forUser($actor)->authorize('scan', PickupRequest::class);

        $targetPickupStatus = $this->targetPickupStatus($actor);
        $targetOrderStatus = $targetPickupStatus->orderStatus();

        if ($targetOrderStatus === null) {
            throw ValidationException::withMessages([
                'status' => 'Invalid target status for pickup scan.',
            ]);
        }

        if ($requestedStatus !== null && $requestedStatus !== $targetPickupStatus->value) {
            throw ValidationException::withMessages([
                'status' => 'Invalid status transition for your role.',
            ]);
        }

        $numbers = collect($trackingNumbers)->filter()->unique()->values();

        if ($numbers->isEmpty()) {
            throw ValidationException::withMessages([
                'orders' => 'Select at least one order.',
            ]);
        }

        $orders = Order::query()
            ->with(['pickupRequest', 'city'])
            ->whereIn('tracking_number', $numbers)
            ->get()
            ->keyBy('tracking_number');

        $missing = $numbers->filter(fn (string $number) => ! $orders->has($number));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'orders' => 'One or more orders were not found: '.$missing->implode(', '),
            ]);
        }

        foreach ($numbers as $trackingNumber) {
            /** @var Order $order */
            $order = $orders->get($trackingNumber);
            $validation = $this->validateOrderForScan($actor, $order);

            if (! $validation['valid']) {
                throw ValidationException::withMessages([
                    'orders' => "{$trackingNumber}: {$validation['message']}",
                ]);
            }

            Gate::forUser($actor)->authorize('scanForPickup', $order);
        }

        $updated = 0;
        $affectedPickups = collect();

        DB::transaction(function () use ($numbers, $orders, $actor, $targetOrderStatus, $targetPickupStatus, &$updated, &$affectedPickups): void {
            foreach ($numbers as $trackingNumber) {
                /** @var Order $order */
                $order = $orders->get($trackingNumber);

                if ($order->status === $targetOrderStatus) {
                    continue;
                }

                $this->transitionOrder($order, $targetOrderStatus, $actor);
                $updated++;

                if ($order->pickup_request_id && $order->pickupRequest) {
                    $affectedPickups->put($order->pickup_request_id, $order->pickupRequest);
                }
            }

            foreach ($affectedPickups->unique() as $pickup) {
                if ($pickup instanceof PickupRequest) {
                    $this->syncPickupStatusIfComplete($pickup, $targetPickupStatus, $actor);
                }
            }
        });

        return [
            'updated' => $updated,
            'orders' => $orders->values(),
        ];
    }

    /**
     * @return array{valid: bool, message: string}
     */
    public function validateOrderForScan(User $actor, Order $order): array
    {
        try {
            $mode = $this->resolveScannerMode($actor);
        } catch (AuthorizationException) {
            return ['valid' => false, 'message' => 'Unauthorized scan'];
        }

        return $mode === self::MODE_DRIVER
            ? $this->validateDriverScan($actor, $order)
            : $this->validateAdminScan($order);
    }

    /**
     * @return array{valid: bool, message: string}
     */
    private function validateDriverScan(User $actor, Order $order): array
    {
        $pickup = $order->pickupRequest;

        if (! $pickup) {
            return ['valid' => false, 'message' => 'You cannot scan this order.'];
        }

        if ($pickup->assigned_to !== $actor->id) {
            return ['valid' => false, 'message' => 'This order is not assigned to you.'];
        }

        if ($pickup->status !== PickupRequestStatus::WAITING_FOR_PICKUP) {
            return ['valid' => false, 'message' => 'Order already processed.'];
        }

        if ($order->status !== OrderStatus::WAITING_PICKUP) {
            if (in_array($order->status, [OrderStatus::PICKED_UP, OrderStatus::IN_DEPOT, OrderStatus::DELIVERED, OrderStatus::RETURNED], true)) {
                return ['valid' => false, 'message' => 'Order already processed.'];
            }

            return ['valid' => false, 'message' => 'You cannot scan this order.'];
        }

        return ['valid' => true, 'message' => 'Valid'];
    }

    /**
     * @return array{valid: bool, message: string}
     */
    private function validateAdminScan(Order $order): array
    {
        if ($order->status === OrderStatus::PICKED_UP) {
            return ['valid' => true, 'message' => 'Valid'];
        }

        if (in_array($order->status, [OrderStatus::IN_DEPOT, OrderStatus::DELIVERED, OrderStatus::RETURNED], true)) {
            return ['valid' => false, 'message' => 'Order already processed.'];
        }

        return ['valid' => false, 'message' => 'You cannot scan this order.'];
    }

    private function transitionOrder(Order $order, OrderStatus $toStatus, User $actor): void
    {
        $from = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;

        $order->update(['status' => $toStatus->value]);
        $order->recordStatus(
            $toStatus,
            $actor,
            "Pickup scan: {$from} → {$toStatus->value}.",
            pickupRequestId: $order->pickup_request_id,
        );

        if ($toStatus === OrderStatus::IN_DEPOT) {
            $this->orderStatus->handleAutoCityDeliveryTransition($order);
        }
    }

    private function syncPickupStatusIfComplete(PickupRequest $pickup, PickupRequestStatus $targetStatus, User $actor): void
    {
        $pickup->refresh()->load('orders');

        $requiredOrderStatus = $targetStatus->orderStatus();

        if ($requiredOrderStatus === null) {
            return;
        }

        $allMatch = $pickup->orders->every(
            fn (Order $order) => ($order->status instanceof OrderStatus ? $order->status : OrderStatus::from((string) $order->status)) === $requiredOrderStatus
        );

        if (! $allMatch) {
            return;
        }

        $current = $pickup->status instanceof PickupRequestStatus
            ? $pickup->status
            : PickupRequestStatus::from((string) $pickup->status);

        if ($current === $targetStatus) {
            return;
        }

        $from = $current->value;
        $pickup->update(['status' => $targetStatus->value]);
        $pickup->recordStatus($targetStatus, $actor, $from, 'All orders synced via bulk pickup scan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrder(Order $order): array
    {
        return [
            'tracking_number' => $order->tracking_number,
            'status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
            'customer' => $order->customer_full_name,
            'city' => $order->city?->name,
        ];
    }

    /**
     * @return array{success: false, valid: false, message: string}
     */
    private function failure(string $message): array
    {
        return [
            'success' => false,
            'valid' => false,
            'message' => $message,
        ];
    }
}
