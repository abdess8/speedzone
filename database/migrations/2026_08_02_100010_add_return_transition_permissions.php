<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Per-step grants for the six-status return workflow.
     *
     * `returns.update_status` stays valid and still unlocks every step, so no
     * existing role loses anything here. These exist so a hub role can be built
     * that signs parcels in without also being able to close the return at the
     * seller's door.
     *
     * @var array<int, string>
     */
    private const PERMISSIONS = [
        'returns.transition.to_received_at_hub',
        'returns.transition.to_in_transit_to_depot',
        'returns.transition.to_arrived_vendor_hub',
        'returns.transition.to_in_delivery_to_vendor',
        'returns.transition.to_delivered_to_vendor',
    ];

    /**
     * The driver only owns the hand-back leg.
     *
     * @var array<int, string>
     */
    private const DRIVER_PERMISSIONS = [
        'returns.transition.to_in_delivery_to_vendor',
        'returns.transition.to_delivered_to_vendor',
    ];

    public function up(): void
    {
        $ids = [];

        foreach (self::PERMISSIONS as $name) {
            $ids[$name] = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => 'returns',
                    'action' => 'transition',
                    'scope' => null,
                    'type' => 'workflow_transition',
                ]
            )->id;
        }

        // Roles that already hold the blanket permission get the whole set, so
        // the granular checks they now go through resolve the same way.
        Role::query()
            ->whereNull('owner_id')
            ->whereHas('permissions', fn ($q) => $q->whereIn('name', ['returns.update_status', 'returns.manage']))
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching(array_values($ids)));

        Role::query()
            ->whereNull('owner_id')
            ->where('name', Role::DRIVER)
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching(
                array_values(array_intersect_key($ids, array_flip(self::DRIVER_PERMISSIONS)))
            ));
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
    }
};
