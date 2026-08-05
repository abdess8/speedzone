<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\StockPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The grant that lets a collector go to the shop, count what is handed over
     * and drive it to the depot.
     *
     * Hub-side, like `stock.receive_inbound`, and for the same reason: the point
     * of the collection count is that it is made by somebody other than the
     * vendor. A shop able to sign for its own shipment would make the figure
     * worthless, so this is never delegatable to the vendor side.
     *
     * Granted to drivers because they are the ones on the road — and to dispatch,
     * which covers the round when no driver is available.
     */
    public function up(): void
    {
        $id = Permission::query()->updateOrCreate(
            ['name' => StockPermissions::COLLECT_INBOUND],
            [
                'resource' => 'stock',
                'action' => 'collect_inbound',
                'scope' => null,
                'type' => 'resource',
            ]
        )->id;

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', [...User::SUPER_ADMIN_ROLES, Role::DISPATCHER, Role::DRIVER])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$id]));
    }

    public function down(): void
    {
        Permission::query()->where('name', StockPermissions::COLLECT_INBOUND)->delete();
    }
};
