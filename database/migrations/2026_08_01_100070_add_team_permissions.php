<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Vendor team administration.
     *
     * Excluded from RolePermissionMatrix::sellerCeiling(): a member who could
     * manage the team would be able to promote himself.
     *
     * @var array<string, array{resource: string, action: string}>
     */
    private const PERMISSIONS = [
        'team.read' => ['resource' => 'team', 'action' => 'read'],
        'team.create' => ['resource' => 'team', 'action' => 'create'],
        'team.update' => ['resource' => 'team', 'action' => 'update'],
        'team.suspend' => ['resource' => 'team', 'action' => 'suspend'],
        'team_roles.manage' => ['resource' => 'team_roles', 'action' => 'manage'],
    ];

    public function up(): void
    {
        $ids = [];

        foreach (self::PERMISSIONS as $name => $definition) {
            $ids[] = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => $definition['resource'],
                    'action' => $definition['action'],
                    'scope' => null,
                    'type' => 'resource',
                ]
            )->id;
        }

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', array_merge(User::SUPER_ADMIN_ROLES, [Role::SELLER]))
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->delete();
    }
};
