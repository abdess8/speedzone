<?php

namespace App\Policies;

use App\Models\PickupRequest;
use App\Models\User;

class PickupRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pickup_requests.read.all')
            || $user->hasPermission('pickup_requests.read.own')
            || $user->hasPermission('pickup_requests.read.assigned');
    }

    public function view(User $user, PickupRequest $pickupRequest): bool
    {
        return $user->hasPickupRequestScopePermission('read', $pickupRequest);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pickup_requests.create');
    }

    public function assign(User $user, PickupRequest $pickupRequest): bool
    {
        return $user->hasPermission('pickup_requests.assign');
    }

    public function changeStatus(User $user, PickupRequest $pickupRequest): bool
    {
        if ($user->hasPermission('pickup_requests.change_status')) {
            return true;
        }

        return $user->hasPermission('pickup_requests.pickup')
            && $pickupRequest->assigned_to === $user->id;
    }

    public function print(User $user, PickupRequest $pickupRequest): bool
    {
        return $user->hasPickupRequestScopePermission('read', $pickupRequest);
    }
}
