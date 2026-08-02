<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RolePermissionMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor-defined roles ("Préparateur de commandes", "Gestionnaire de stock").
 *
 * The invariant enforced here is that a custom role can never be broader than
 * the platform's Seller role minus the account-administration permissions: a
 * vendor may only delegate what he himself holds, and never the right to
 * manage stores or the team, which would let a member escalate his own access.
 */
class TeamRoleService
{
    public function __construct(private readonly UserSessionService $sessions) {}

    /**
     * Permission names a vendor is allowed to hand out.
     *
     * @return array<int, string>
     */
    public function ceiling(): array
    {
        return RolePermissionMatrix::sellerCeiling();
    }

    /**
     * The ceiling as selectable options, grouped by resource for the editor.
     *
     * @return array<int, array{resource: string, permissions: array<int, array{id: int, name: string, action: string, scope: string|null}>}>
     */
    public function permissionOptions(): array
    {
        $permissions = Permission::query()
            ->whereIn('name', $this->ceiling())
            ->orderBy('resource')
            ->orderBy('action')
            ->get(['id', 'name', 'resource', 'action', 'scope']);

        return $permissions
            ->groupBy('resource')
            ->map(fn ($group, $resource) => [
                'resource' => $resource,
                'permissions' => $group->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'action' => $permission->action,
                    'scope' => $permission->scope,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function create(User $owner, string $label, array $permissionNames): Role
    {
        return DB::transaction(function () use ($owner, $label, $permissionNames) {
            $role = Role::query()->create([
                'owner_id' => $owner->id,
                'name' => $this->uniqueName($owner, $label),
                'label' => $label,
            ]);

            $role->permissions()->sync($this->allowedPermissionIds($permissionNames));

            return $role;
        });
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function update(Role $role, string $label, array $permissionNames): Role
    {
        $this->assertCustom($role);

        return DB::transaction(function () use ($role, $label, $permissionNames) {
            // `name` stays frozen: it is an internal identifier, and renaming it
            // would break nothing visible while risking a unique clash.
            $role->update(['label' => $label]);
            $role->permissions()->sync($this->allowedPermissionIds($permissionNames));

            // Members holding the role keep a memoised permission map in their
            // session-bound model instances only, so nothing to clear there —
            // but a narrowed role should not stay usable in an open tab.
            $role->loadMissing('users');
            $role->users->each(fn (User $member) => $this->sessions->revokeAll($member));

            return $role->fresh(['permissions']);
        });
    }

    public function delete(Role $role): void
    {
        $this->assertCustom($role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('team.roles.errors.in_use'),
            ]);
        }

        $role->permissions()->detach();
        $role->delete();
    }

    /**
     * Reject the platform roles: they are shared by every account.
     */
    private function assertCustom(Role $role): void
    {
        if (! $role->isCustom()) {
            throw ValidationException::withMessages([
                'role' => __('team.roles.errors.system_role'),
            ]);
        }
    }

    /**
     * Drop anything outside the ceiling instead of failing.
     *
     * The form only ever offers allowed permissions, so a value outside the
     * ceiling means a tampered payload; silently ignoring it keeps the
     * privilege boundary intact without leaking which permissions exist.
     *
     * @param  array<int, string>  $permissionNames
     * @return array<int, int>
     */
    private function allowedPermissionIds(array $permissionNames): array
    {
        $allowed = array_intersect($permissionNames, $this->ceiling());

        if ($allowed === []) {
            return [];
        }

        return Permission::query()->whereIn('name', $allowed)->pluck('id')->all();
    }

    private function uniqueName(User $owner, string $label): string
    {
        $base = Role::vendorName($owner->id, $label);
        $name = $base;
        $suffix = 2;

        while (Role::query()->where('name', $name)->exists()) {
            $name = $base.'-'.$suffix++;
        }

        return $name;
    }
}
