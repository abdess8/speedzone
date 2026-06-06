<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->get()->keyBy('name');
        $permissions = Permission::query()->pluck('id', 'name');

        $adminRole = $roles->get(Role::ADMIN);
        $dispatcherRole = $roles->get(Role::DISPATCHER);
        $driverRole = $roles->get(Role::DRIVER);
        $sellerRole = $roles->get(Role::SELLER);

        $allPermissionIds = $permissions->values()->all();

        $adminRole?->permissions()->sync($allPermissionIds);

        $dispatcherRole?->permissions()->sync(
            $permissions->only([
                'orders.read.all',
                'orders.update.all',
                'orders.transition.to_pickup_requested',
                'orders.transition.to_waiting_pickup',
                'orders.transition.to_picked_up',
                'orders.transition.to_in_depot',
                'orders.transition.to_in_transit',
                'orders.transition.to_in_delivery_city',
            ])->values()->all()
        );

        $driverRole?->permissions()->sync(
            $permissions->only([
                'orders.read.own',
                'orders.update.own',
                'orders.transition.to_out_for_delivery',
                'orders.transition.to_delivered',
                'orders.transition.to_failed',
                'orders.transition.to_returned',
            ])->values()->all()
        );

        $sellerRole?->permissions()->sync(
            $permissions->only([
                'orders.create',
                'orders.read.own',
                'orders.update.own',
                'orders.delete.own',
            ])->values()->all()
        );

        $firstUser = User::query()->orderBy('id')->first();
        if ($firstUser && $adminRole) {
            $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);
            if (! $firstUser->role_id) {
                $firstUser->update(['role_id' => $adminRole->id]);
            }
        }
    }
}
