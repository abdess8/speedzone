<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('alerts.read');
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->hasPermission('alerts.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('alerts.create');
    }

    public function update(User $user, Alert $alert): bool
    {
        return $user->hasPermission('alerts.update');
    }

    public function delete(User $user, Alert $alert): bool
    {
        return $user->hasPermission('alerts.delete');
    }
}
