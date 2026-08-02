<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerOrderBulkService
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
        private readonly OrderDriverAssignmentService $driverAssignment,
    ) {}

    /**
     * @param  array<int, int>  $orderIds
     * @return array{updated: int, skipped: int}
     */
    public function advanceToNextStatus(User $actor, array $orderIds): array
    {
        $this->assertCanManageDeliveries($actor);

        $orders = $this->scopedQuery($actor)
            ->whereIn('id', $orderIds)
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $nextStatus = $this->resolveNextStatus($order, $actor);

            if ($nextStatus === null) {
                $skipped++;

                continue;
            }

            try {
                $this->transitions->transition($order, $nextStatus, $actor, 'Bulk advance to next status.');
                $updated++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return array{updated: int, skipped: int}
     */
    public function assignDriver(User $actor, array $orderIds, int $driverId): array
    {
        $this->assertCanAssignDriver($actor);

        $driver = User::query()->whereKey($driverId)->first();

        if (! $driver?->isDriver()) {
            throw ValidationException::withMessages([
                'driver_id' => 'Selected user is not a driver.',
            ]);
        }

        $orders = $this->scopedQuery($actor)
            ->whereIn('id', $orderIds)
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if (! $this->driverAssignment->canAssign($order, $actor)) {
                $skipped++;

                continue;
            }

            try {
                $this->driverAssignment->assign($order, $driver, $actor, 'Bulk driver assignment.');
                $updated++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, string>  $trackingNumbers
     * @return array{updated: int, skipped: int, orders: Collection<int, Order>}
     */
    public function bulkScanAdvance(User $actor, array $trackingNumbers): array
    {
        $this->assertCanManageDeliveries($actor);

        $numbers = collect($trackingNumbers)->filter()->unique()->values();

        if ($numbers->isEmpty()) {
            throw ValidationException::withMessages([
                'orders' => 'Select at least one order.',
            ]);
        }

        $orders = Order::query()
            ->with(['city', 'partner'])
            ->visibleForPartnerDeliveryAccess($actor)
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
        }

        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($numbers, $orders, $actor, &$updated, &$skipped): void {
            foreach ($numbers as $trackingNumber) {
                /** @var Order $order */
                $order = $orders->get($trackingNumber);
                $nextStatus = $this->resolveNextStatus($order, $actor);

                if ($nextStatus === null) {
                    $skipped++;

                    continue;
                }

                $current = $order->status instanceof OrderStatus
                    ? $order->status->value
                    : (string) $order->status;

                if ($current === $nextStatus) {
                    $skipped++;

                    continue;
                }

                $this->transitions->transition($order, $nextStatus, $actor, 'Status updated via QR bulk scan.');
                $updated++;
            }
        });

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'orders' => $orders->values(),
        ];
    }

    /**
     * @return array{success: bool, valid: bool, message: string, order?: array<string, mixed>}
     */
    public function validateScan(User $actor, string $trackingNumber): array
    {
        $this->assertCanManageDeliveries($actor);

        $order = Order::query()
            ->with(['city', 'partner'])
            ->visibleForPartnerDeliveryAccess($actor)
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (! $order) {
            return [
                'success' => false,
                'valid' => false,
                'message' => 'Order not found.',
            ];
        }

        $validation = $this->validateOrderForScan($actor, $order);

        if (! $validation['valid']) {
            return [
                'success' => false,
                'valid' => false,
                'message' => $validation['message'],
            ];
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from($order->status);

        return [
            'success' => true,
            'valid' => true,
            'message' => 'Valid',
            'order' => [
                'id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'customer' => $order->customer_full_name,
                'city' => $order->city?->name ?? '—',
                'status' => $status->value,
                'status_label' => $status->label(),
                'partner' => $order->partner?->name,
                'next_status' => $this->resolveNextStatus($order, $actor),
            ],
        ];
    }

    /**
     * @return array{valid: bool, message: string}
     */
    public function validateOrderForScan(User $actor, Order $order): array
    {
        if (! $order->partner_id) {
            return ['valid' => false, 'message' => 'Not a partner order.'];
        }

        if (! $this->canManageOrder($actor, $order)) {
            return ['valid' => false, 'message' => 'You are not allowed to manage this partner order.'];
        }

        if ($this->resolveNextStatus($order, $actor) === null) {
            return ['valid' => false, 'message' => 'No allowed next status for this order.'];
        }

        return ['valid' => true, 'message' => 'Valid'];
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null}>
     */
    public function driverOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::DRIVER))
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email'])
            ->map(fn (User $driver) => [
                'id' => $driver->id,
                'name' => $driver->full_name,
                'email' => $driver->email,
            ])
            ->all();
    }

    private function scopedQuery(User $actor): Builder
    {
        return Order::query()->visibleForPartnerDeliveryAccess($actor);
    }

    private function canManageOrder(User $actor, Order $order): bool
    {
        if (! $actor->hasPermission('partners.deliveries.manage') && ! $actor->isDriver()) {
            return false;
        }

        return $actor->can('partner-delivery.update', $order);
    }

    private function assertCanManageDeliveries(User $actor): void
    {
        if (! $actor->hasPermission('partners.deliveries.manage')) {
            throw new AuthorizationException('Missing required permission: partners.deliveries.manage');
        }
    }

    private function assertCanAssignDriver(User $actor): void
    {
        if (! $actor->hasPermission('driver_invoices.assign_driver')
            && ! $actor->hasPermission('partners.deliveries.manage')) {
            throw new AuthorizationException('Missing required permission to assign drivers.');
        }
    }

    private function resolveNextStatus(Order $order, User $actor): ?string
    {
        foreach ($this->transitions->allowedNextStatuses($order) as $status) {
            if ($actor->hasPermission('orders.transition.to_'.strtolower($status))) {
                return $status;
            }
        }

        return null;
    }
}
