<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Team administration is reserved to the vendor admin.
 *
 * Every ability requires isAccountOwner(): the corresponding permissions are
 * excluded from RolePermissionMatrix::sellerCeiling(), but checking ownership
 * as well means a member still cannot reach these screens even if a permission
 * were mistakenly attached to his role.
 *
 * Super admins bypass all of this through the Gate::before hook.
 */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isVendorAdmin($user) && $user->hasPermission('team.read');
    }

    public function create(User $user): bool
    {
        return $this->isVendorAdmin($user) && $user->hasPermission('team.create');
    }

    public function update(User $user, User $member): bool
    {
        return $this->owns($user, $member) && $user->hasPermission('team.update');
    }

    public function suspend(User $user, User $member): bool
    {
        return $this->owns($user, $member) && $user->hasPermission('team.suspend');
    }

    public function manageRoles(User $user): bool
    {
        return $this->isVendorAdmin($user) && $user->hasPermission('team_roles.manage');
    }

    public function updateRole(User $user, Role $role): bool
    {
        return $this->manageRoles($user) && $role->owner_id === $user->id;
    }

    private function owns(User $user, User $member): bool
    {
        return $this->isVendorAdmin($user) && $member->parent_user_id === $user->id;
    }

    private function isVendorAdmin(User $user): bool
    {
        return $user->isAccountOwner();
    }
}
