<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * User management had no permission of its own: `/users` and `/roles` were
     * reachable by any authenticated account. These back the new route guards
     * and the UserPolicy / RolePolicy.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'users.read' => 'read',
        'users.create' => 'create',
        'users.update' => 'update',
        'users.delete' => 'delete',
    ];

    public function up(): void
    {
        $ids = [];

        foreach (self::PERMISSIONS as $name => $action) {
            $ids[] = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => 'users',
                    'action' => $action,
                    'scope' => null,
                    'type' => 'admin',
                ]
            )->id;
        }

        Role::query()
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->delete();
    }
};
