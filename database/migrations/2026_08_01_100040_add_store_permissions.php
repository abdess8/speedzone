<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Store management is a vendor-side capability: the seller administers his
     * own shops. Super admins get it too so the back office can inspect and fix
     * a vendor's setup.
     *
     * Everything but stores.read is excluded from
     * RolePermissionMatrix::sellerCeiling(), otherwise a team member could be
     * granted the right to create a store and then grant himself access to it.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'stores.read' => 'read',
        'stores.create' => 'create',
        'stores.update' => 'update',
        'stores.delete' => 'delete',
    ];

    public function up(): void
    {
        $ids = [];

        foreach (self::PERMISSIONS as $name => $action) {
            $ids[] = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => 'stores',
                    'action' => $action,
                    'scope' => null,
                    'type' => 'resource',
                ]
            )->id;
        }

        Role::query()
            ->whereIn('name', array_merge(User::SUPER_ADMIN_ROLES, [Role::SELLER]))
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->delete();
    }
};
