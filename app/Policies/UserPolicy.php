<?php

namespace App\Policies;

use App\Models\User;

/**
 * User administration is a pure RBAC resource: there is no ownership dimension,
 * so every ability maps straight onto a `users.*` permission.
 *
 * Note that `Gate::before` in AuthServiceProvider lets super admins through
 * before any of this runs.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.read');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.read') || $user->is($target);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.update');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermission('users.delete');
    }

    /**
     * Deleting your own account would end the session mid-request and, for the
     * last remaining super admin, leave the platform unadministrable.
     *
     * Kept out of {@see self::delete()} on purpose: `Gate::before` lets super
     * admins skip the policy entirely, so the invariant is called directly.
     */
    public function evaluateDelete(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return false;
        }

        return $this->delete($user, $target) || $user->isSuperAdmin();
    }

    public function assignRoles(User $user): bool
    {
        return $user->hasPermission('users.roles.assign');
    }
}
