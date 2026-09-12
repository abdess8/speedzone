<?php

namespace App\Policies;

use App\Enums\EcommercePlatform;
use App\Models\EcommerceIntegration;
use App\Models\Store;
use App\Models\User;
use App\Support\EcommerceIntegrationPermissions;

/**
 * A vendor plugs his own shop. Team members need the matching grant and
 * membership of the SpeedZone store the connection hangs from.
 */
class EcommerceIntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        foreach (EcommerceIntegrationPermissions::moduleAccess() as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function view(User $user, EcommerceIntegration $integration): bool
    {
        return $this->viewAny($user) && $this->belongsToAccount($user, $integration);
    }

    public function create(User $user, EcommercePlatform $platform, Store $store): bool
    {
        return EcommerceIntegrationPermissions::canConnect($user, $platform)
            && $this->canUseStore($user, $store);
    }

    public function update(User $user, EcommerceIntegration $integration): bool
    {
        return EcommerceIntegrationPermissions::canConnect($user, $integration->platform)
            && $this->belongsToAccount($user, $integration);
    }

    public function delete(User $user, EcommerceIntegration $integration): bool
    {
        return $this->update($user, $integration);
    }

    private function belongsToAccount(User $user, EcommerceIntegration $integration): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $integration->seller_id === $user->accountOwnerId()
            && $user->canAccessStore((int) $integration->store_id);
    }

    private function canUseStore(User $user, Store $store): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $store->owner_id === $user->accountOwnerId()
            && $user->canAccessStore((int) $store->id);
    }
}
