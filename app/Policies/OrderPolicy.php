<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

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
        return $user->hasOrderScopePermission('update', $order);
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
}
