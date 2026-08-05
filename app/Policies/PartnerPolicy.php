<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('partners.read');
    }

    /**
     * View the dedicated partner orders list (separate from native orders).
     */
    public function viewOrders(User $user): bool
    {
        return $user->hasPermission('partners.read')
            || $user->hasPermission('partners.deliveries.manage')
            || $user->isDriver();
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->hasPermission('partners.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('partners.create');
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->hasPermission('partners.update');
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->hasPermission('partners.delete');
    }

    /**
     * Trigger an on-demand ingestion ("Force Sync Now") for a partner.
     */
    public function sync(User $user, Partner $partner): bool
    {
        return $user->hasPermission('partners.sync');
    }

    /**
     * Bulk status updates and QR scanning on partner orders.
     */
    public function manageDeliveries(User $user): bool
    {
        return $user->hasPermission('partners.deliveries.manage');
    }
}
