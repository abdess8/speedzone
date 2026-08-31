<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\StatusTransitionPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Grants of the shape `source status → target status`, one per edge of the
     * order and return transition graphs, backing the bulk status editor.
     *
     * Existing roles are back-filled from the target-status grants they already
     * hold, so nobody gains or loses reach the day the screen ships: a role that
     * could stamp DELIVERED one parcel at a time can now do it in a batch, and a
     * role that could not, still cannot.
     */
    public function up(): void
    {
        $ids = [];

        foreach (StatusTransitionPermissions::catalog() as $permission) {
            $ids[$permission['name']] = Permission::query()->updateOrCreate(
                ['name' => $permission['name']],
                [
                    'resource' => $permission['resource'],
                    'action' => $permission['action'],
                    'scope' => null,
                    'type' => $permission['type'],
                ]
            )->id;
        }

        Role::query()
            ->with('permissions:id,name')
            ->each(function (Role $role) use ($ids): void {
                $derived = StatusTransitionPermissions::derivedFrom(
                    $role->permissions->pluck('name')->all()
                );

                $role->permissions()->syncWithoutDetaching(
                    array_values(array_intersect_key($ids, array_flip($derived)))
                );
            });
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', StatusTransitionPermissions::names())
            ->delete();
    }
};
