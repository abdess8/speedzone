<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            [
                'name' => 'orders.transition.to_rejected',
                'resource' => 'orders',
                'action' => 'transition',
                'scope' => null,
                'type' => 'workflow_transition',
            ],
            [
                'name' => 'orders.transition.to_canceled',
                'resource' => 'orders',
                'action' => 'transition',
                'scope' => null,
                'type' => 'workflow_transition',
            ],
        ];

        $permissionIds = [];

        foreach ($definitions as $definition) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $definition['name']],
                $definition
            );

            $permissionIds[] = $permission->id;
        }

        Role::query()
            ->whereIn('name', [Role::ADMIN, Role::DISPATCHER])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds));
    }

    public function down(): void
    {
        $names = [
            'orders.transition.to_rejected',
            'orders.transition.to_canceled',
        ];

        Permission::query()->whereIn('name', $names)->delete();
    }
};
