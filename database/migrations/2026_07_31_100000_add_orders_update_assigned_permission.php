<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Drivers held `orders.update.own`, but "own" resolves against `seller_id`,
     * so the scope check never matched a driver and every status transition was
     * rejected. `orders.update.assigned` resolves against `driver_id` instead.
     */
    public function up(): void
    {
        $assigned = Permission::query()->updateOrCreate(
            ['name' => 'orders.update.assigned'],
            [
                'resource' => 'orders',
                'action' => 'update',
                'scope' => 'assigned',
                'type' => 'resource',
            ]
        );

        Role::query()
            ->where('name', Role::DRIVER)
            ->each(function (Role $role) use ($assigned): void {
                $role->permissions()->syncWithoutDetaching([$assigned->id]);

                $own = Permission::query()->where('name', 'orders.update.own')->first();

                if ($own) {
                    $role->permissions()->detach($own->id);
                }
            });
    }

    public function down(): void
    {
        $assigned = Permission::query()->where('name', 'orders.update.assigned')->first();

        if (! $assigned) {
            return;
        }

        Role::query()
            ->where('name', Role::DRIVER)
            ->each(function (Role $role) use ($assigned): void {
                $role->permissions()->detach($assigned->id);

                $own = Permission::query()->where('name', 'orders.update.own')->first();

                if ($own) {
                    $role->permissions()->syncWithoutDetaching([$own->id]);
                }
            });

        $assigned->delete();
    }
};
