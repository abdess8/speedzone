<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['name' => 'orders.read.assigned'],
            [
                'resource' => 'orders',
                'action' => 'read',
                'scope' => 'assigned',
                'type' => 'resource',
            ]
        );

        Role::query()
            ->where('name', Role::DRIVER)
            ->each(function (Role $role) use ($permission): void {
                $role->permissions()->syncWithoutDetaching([$permission->id]);

                $own = Permission::query()->where('name', 'orders.read.own')->first();
                if ($own) {
                    $role->permissions()->detach($own->id);
                }
            });
    }

    public function down(): void
    {
        $assigned = Permission::query()->where('name', 'orders.read.assigned')->first();

        if ($assigned) {
            Role::query()
                ->where('name', Role::DRIVER)
                ->each(function (Role $role) use ($assigned): void {
                    $role->permissions()->detach($assigned->id);

                    $own = Permission::query()->where('name', 'orders.read.own')->first();
                    if ($own) {
                        $role->permissions()->syncWithoutDetaching([$own->id]);
                    }
                });

            $assigned->delete();
        }
    }
};
