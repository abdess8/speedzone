<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Role administration. Pure RBAC, no ownership dimension.
 *
 * This is the most sensitive resource in the system: whoever can edit a role can
 * grant themselves every other permission, so the abilities map onto dedicated
 * `roles.*` permissions that only super admins hold by default.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.read');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.delete');
    }

    /**
     * The four seeded roles are referenced by name throughout the domain
     * (Role::DRIVER, Role::SELLER…), so deleting one would silently break scope
     * resolution for every user still attached to it.
     *
     * Kept out of {@see self::delete()} on purpose: `Gate::before` lets super
     * admins skip the policy entirely, and they are precisely the accounts that
     * can reach this screen, so the invariant is called directly instead.
     */
    public function evaluateDelete(User $user, Role $role): bool
    {
        if (in_array($role->name, Role::DEFAULTS, true)) {
            return false;
        }

        return $this->delete($user, $role) || $user->isSuperAdmin();
    }
}
