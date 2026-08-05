<?php

namespace App\Policies;

use App\Models\Sector;
use App\Models\User;

class SectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sectors.read');
    }

    public function view(User $user, Sector $sector): bool
    {
        return $user->hasPermission('sectors.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sectors.create');
    }

    public function update(User $user, Sector $sector): bool
    {
        return $user->hasPermission('sectors.update');
    }

    public function delete(User $user, Sector $sector): bool
    {
        return $user->hasPermission('sectors.delete');
    }
}
