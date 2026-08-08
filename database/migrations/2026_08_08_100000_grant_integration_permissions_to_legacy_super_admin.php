<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\EcommerceIntegrationPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Catch the super admin role the previous migration walked past.
     *
     * `add_ecommerce_integration_permissions` granted to `User::SUPER_ADMIN_ROLES`,
     * which at the time did not list the legacy `Super Admin` spelling every
     * live install actually carries — so the one account that administers the
     * platform never received the two grants and the topbar entry stayed hidden.
     * The constant is fixed; this replays the grant for the rows already created.
     */
    public function up(): void
    {
        $ids = Permission::query()
            ->whereIn('name', EcommerceIntegrationPermissions::all())
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        // The grants belong to the migration that created the permissions;
        // undoing them here would strip a role the other migration also feeds.
    }
};
