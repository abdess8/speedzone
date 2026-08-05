<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The grant that lets a hub agent declare a stock order picked and packed.
     *
     * Named like the thirteen transition permissions already in the catalog
     * rather than as a standalone `orders.prepare`, so the whole workflow stays
     * readable from a single naming rule. It is staff-side only: the goods sit
     * in our depot, so we are the ones who pack them.
     */
    private const PERMISSION = 'orders.transition.to_prepared';

    public function up(): void
    {
        $id = Permission::query()->updateOrCreate(
            ['name' => self::PERMISSION],
            [
                'resource' => 'orders',
                'action' => 'transition',
                'scope' => null,
                'type' => 'workflow_transition',
            ]
        )->id;

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', [...User::SUPER_ADMIN_ROLES, Role::DISPATCHER])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$id]));
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSION)->delete();
    }
};
