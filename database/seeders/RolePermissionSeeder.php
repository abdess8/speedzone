<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RolePermissionMatrix;
use App\Support\SupportPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection as SupportCollection;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $roles = Role::query()->get()->keyBy('name');
        /** @var SupportCollection<string, int> $permissions */
        $permissions = Permission::query()->pluck('id', 'name');

        foreach (RolePermissionMatrix::resolvedGrants() as $roleName => $permissionNames) {
            $roles->get($roleName)?->permissions()->sync(
                $permissions->only($permissionNames)->values()->all()
            );
        }

        // Non-destructive: attach support permissions when new ones are added to the catalog
        // without resetting custom role assignments made via Settings → Roles & Permissions.
        $this->ensurePermissions($roles->get(Role::SELLER), SupportPermissions::sellerDefaults(), $permissions);
        $this->ensurePermissions($roles->get(Role::DISPATCHER), SupportPermissions::staffDefaults(), $permissions);

        $adminRole = $roles->get(Role::ADMIN);

        $firstUser = User::query()->orderBy('id')->first();
        if ($firstUser && $adminRole) {
            $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);
            if (! $firstUser->role_id) {
                $firstUser->update(['role_id' => $adminRole->id]);
            }
        }
    }

    /**
     * Attach permissions to a role without removing existing assignments.
     *
     * @param  SupportCollection<string, int>  $permissions
     */
    private function ensurePermissions(?Role $role, array $permissionNames, SupportCollection $permissions): void
    {
        if (! $role) {
            return;
        }

        $ids = $permissions->only($permissionNames)->values()->all();

        if ($ids !== []) {
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }
}
