<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Partner;
use App\Models\User;
use App\Services\OrderDriverAssignmentService;
use App\Services\PickupScanService;
use Illuminate\Auth\Access\AuthorizationException;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('orders.read.all')
            || $user->hasPermission('orders.read.own')
            || $user->hasPermission('orders.read.assigned');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasOrderScopePermission('read', $order);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('orders.create');
    }

    /**
     * Editing the order's own fields (customer, address, amounts…).
     *
     * Distinct from {@see self::updateStatus()}: a driver may move an order
     * through the workflow but must never rewrite its content.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->hasPermission('orders.update.all')) {
            return true;
        }

        if ($order->seller_id === $user->id && $user->hasPermission('orders.update.own')) {
            // A seller may only correct an order nobody has picked up yet.
            return $this->statusOf($order) === OrderStatus::CREATED;
        }

        return false;
    }

    /**
     * Moving the order through the status workflow.
     *
     * RBAC decides *which* target statuses are reachable (checked per target in
     * OrderTransitionService via `orders.transition.to_*`); this ability is the
     * ABAC half, answering "may this user act on *this* order at all?".
     */
    public function updateStatus(User $user, Order $order): bool
    {
        if ($user->hasPermission('orders.update.all')) {
            return true;
        }

        // Field agent: strictly the orders dispatched to him.
        if ((int) $order->driver_id === (int) $user->id && $user->hasPermission('orders.update.assigned')) {
            return true;
        }

        // Seller: may still cancel/reject an order that has not moved yet.
        if ($order->seller_id === $user->id && $user->hasPermission('orders.update.own')) {
            return $this->statusOf($order) === OrderStatus::CREATED;
        }

        return false;
    }

    private function statusOf(Order $order): OrderStatus
    {
        return $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from($order->status);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasOrderScopePermission('delete', $order);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('orders.export');
    }

    public function print(User $user, ?Order $order = null): bool
    {
        if (! $user->hasPermission('orders.print')) {
            return false;
        }

        // Printing a specific label still respects read scope.
        return $order === null || $user->hasOrderScopePermission('read', $order);
    }

    public function scanForPickup(User $user, Order $order): bool
    {
        if (! $user->hasPermission('pickup_requests.pickup') && ! $user->hasPermission('pickup_requests.change_status')) {
            return false;
        }

        try {
            return app(PickupScanService::class)->validateOrderForScan($user, $order)['valid'];
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function assignDriver(User $user, Order $order): bool
    {
        return $this->evaluateAssignDriver($user, $order);
    }

    /**
     * Business rules for driver assignment (bypasses Gate super-admin shortcut).
     */
    public function evaluateAssignDriver(User $user, Order $order): bool
    {
        if (! $user->hasPermission('driver_invoices.assign_driver')
            && ! $user->hasPermission('partners.deliveries.manage')) {
            return false;
        }

        if ($order->partner_id) {
            if (! $user->hasPermission('partners.deliveries.manage')) {
                return false;
            }

            $partner = $order->relationLoaded('partner')
                ? $order->partner
                : Partner::query()->find($order->partner_id);

            if (! $user->hasPermission('partners.read') && ! ($partner && $user->managesPartner($partner))) {
                return false;
            }

            return app(OrderDriverAssignmentService::class)->canAssign($order, $user);
        }

        if (! $user->hasOrderScopePermission('read', $order)) {
            return false;
        }

        return app(OrderDriverAssignmentService::class)->canAssign($order, $user);
    }
}
