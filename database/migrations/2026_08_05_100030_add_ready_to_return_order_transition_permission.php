<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * READY_TO_RETURN replaces FAILED as the exit taken when a customer refuses
     * or cancels at the door, so everyone who could previously close a delivery
     * as failed must be able to reach it — otherwise the driver's sheet loses
     * its only non-delivery option.
     */
    private const PERMISSION = 'orders.transition.to_ready_to_return';

    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['name' => self::PERMISSION],
            [
                'name' => self::PERMISSION,
                'resource' => 'orders',
                'action' => 'transition',
                'scope' => null,
                'type' => 'workflow_transition',
            ]
        );

        Role::query()
            ->whereIn('name', [Role::ADMIN, Role::DISPATCHER, Role::DRIVER])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSION)->delete();
    }
};
