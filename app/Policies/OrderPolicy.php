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
        return $user->hasPermission('orders.read.all') || $user->hasPermission('orders.read.own');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasOrderScopePermission('read', $order);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->isSuperAdmin() && $user->hasPermission('orders.update.all')) {
            return true;
        }

        if ($order->seller_id === $user->id && $user->hasPermission('orders.update.own')) {
            $status = $order->status instanceof OrderStatus
                ? $order->status
                : OrderStatus::from($order->status);

            return $status === OrderStatus::CREATED;
        }

        return false;
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
