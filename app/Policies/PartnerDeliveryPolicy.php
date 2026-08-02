<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class PartnerDeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageAllPartners()
            || $user->hasPermission('partners.deliveries.manage')
            || $user->isDriver();
    }

    public function view(User $user, Order $order): bool
    {
        return $this->canAccess($user, $order);
    }

    public function update(User $user, Order $order): bool
    {
        if (! $order->partner_id) {
            return false;
        }

        if (! $user->hasPermission('partners.deliveries.manage') && ! $user->isDriver()) {
            return false;
        }

        return $this->canAccess($user, $order);
    }

    private function canAccess(User $user, Order $order): bool
    {
        if (! $order->partner_id) {
            return false;
        }

        if ($user->canManageAllPartners()) {
            return true;
        }

        if ($user->isDriver()) {
            return (int) $order->driver_id === (int) $user->id;
        }

        return $user->partners()->whereKey($order->partner_id)->exists();
    }
}
