<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * A vendor administers his own shops and nobody else's.
 *
 * Super admins bypass through the Gate::before hook in AuthServiceProvider, so
 * the ownership check here does not need to make an exception for them.
 */
class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stores.read');
    }

    public function view(User $user, Store $store): bool
    {
        return $user->hasPermission('stores.read') && $this->belongsToAccount($user, $store);
    }

    public function create(User $user): bool
    {
        // Only the vendor admin: a team member must never be able to add a shop
        // and hand himself access to it.
        return $user->hasPermission('stores.create') && ! $user->isTeamMember();
    }

    public function update(User $user, Store $store): bool
    {
        return $user->hasPermission('stores.update')
            && ! $user->isTeamMember()
            && $this->belongsToAccount($user, $store);
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->hasPermission('stores.delete')
            && ! $user->isTeamMember()
            && $this->belongsToAccount($user, $store);
    }

    /**
     * Whether the store hangs off the actor's vendor account.
     *
     * Read access additionally requires membership in the store_user pivot,
     * which is what the `view` path checks through accessibleStoreIds().
     */
    private function belongsToAccount(User $user, Store $store): bool
    {
        return (int) $store->owner_id === $user->accountOwnerId()
            && ($user->isTeamMember() ? $user->canAccessStore($store->id) : true);
    }
}
