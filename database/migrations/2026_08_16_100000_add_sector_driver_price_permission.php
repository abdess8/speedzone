<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Splits the driver payout out of `sectors.read`.
     *
     * Sector pricing has to be readable by every vendor who books a delivery,
     * but what we pay the driver is a commercial term between us and him. It
     * was travelling inside the same payload, so any seller could read it. It
     * now needs its own grant, held by administration only.
     */
    public function up(): void
    {
        $id = Permission::query()->updateOrCreate(
            ['name' => 'sectors.read_driver_price'],
            [
                'resource' => 'sectors',
                'action' => 'read_driver_price',
                'scope' => null,
                'type' => 'admin',
            ]
        )->id;

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$id]));
    }

    public function down(): void
    {
        Permission::query()->where('name', 'sectors.read_driver_price')->delete();
    }
};
