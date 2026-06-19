<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\SupportPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection as SupportCollection;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $roles = Role::query()->get()->keyBy('name');
        /** @var SupportCollection<string, int> $permissions */
        $permissions = Permission::query()->pluck('id', 'name');

        $adminRole = $roles->get(Role::ADMIN);
        $dispatcherRole = $roles->get(Role::DISPATCHER);
        $driverRole = $roles->get(Role::DRIVER);
        $sellerRole = $roles->get(Role::SELLER);

        $sellerOnlyPermissions = [
            'returns.create_request',
        ];

        $adminPermissionIds = $permissions->except($sellerOnlyPermissions)->values()->all();

        $adminRole?->permissions()->sync($adminPermissionIds);

        $dispatcherRole?->permissions()->sync(
            $permissions->only([
                'orders.read.all',
                'orders.update.all',
                'orders.export',
                'orders.print',
                'cities.read',
                'sectors.read',
                'driver_zones.read',
                'driver_zones.assign',
                'driver_zones.remove',
                'pickup_requests.read.all',
                'pickup_requests.assign',
                'pickup_requests.change_status',
                'orders.transition.to_pickup_requested',
                'orders.transition.to_waiting_pickup',
                'orders.transition.to_picked_up',
                'orders.transition.to_in_depot',
                'orders.transition.to_transfer_created',
                'orders.transition.to_in_transit',
                'orders.transition.to_received_in_destination',
                'orders.transition.to_in_delivery_city',
                'orders.transition.to_out_for_delivery',
                'orders.transition.to_delivered',
                'orders.transition.to_failed',
                'orders.transition.to_rejected',
                'orders.transition.to_canceled',
                'partners.read',
                'partners.deliveries.manage',
                'partners.sync',
                'transfers.create',
                'transfers.read',
                'transfers.update',
                'transfers.dispatch',
                'transfers.receive',
                'returns.read.all',
                'returns.manage',
                'returns.update_status',
                'returns.edit_customer_data',
                'invoices.read.all',
                'invoices.print',
                'driver_invoices.read.all',
                'driver_invoices.print',
                'driver_invoices.assign_driver',
                ...SupportPermissions::staffDefaults(),
            ])->values()->all()
        );

        $driverRole?->permissions()->sync(
            $permissions->only([
                'orders.read.own',
                'orders.update.own',
                'orders.print',
                'pickup_requests.read.assigned',
                'pickup_requests.pickup',
                'transfers.read.assigned',
                'transfers.receive',
                'orders.transition.to_out_for_delivery',
                'orders.transition.to_delivered',
                'orders.transition.to_failed',
                'returns.create',
                'returns.update_status',
                'driver_invoices.read.own',
                'driver_invoices.print',
            ])->values()->all()
        );

        $sellerRole?->permissions()->sync(
            $permissions->only([
                'orders.create',
                'orders.read.own',
                'orders.update.own',
                'orders.delete.own',
                'orders.export',
                'orders.print',
                'pickup_requests.create',
                'pickup_requests.read.own',
                'cities.read',
                'sectors.read',
                'returns.create_request',
                'returns.read.own',
                'invoices.read.own',
                'invoices.print',
                ...SupportPermissions::sellerDefaults(),
            ])->values()->all()
        );

        // Non-destructive: attach support permissions when new ones are added to the catalog
        // without resetting custom role assignments made via Settings → Roles & Permissions.
        $this->ensurePermissions($sellerRole, SupportPermissions::sellerDefaults(), $permissions);
        $this->ensurePermissions($dispatcherRole, SupportPermissions::staffDefaults(), $permissions);

        $firstUser = User::query()->orderBy('id')->first();
        if ($firstUser && $adminRole) {
            $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);
            if (! $firstUser->role_id) {
                $firstUser->update(['role_id' => $adminRole->id]);
            }
        }
    }

    /**
     * Attach permissions to a role without removing existing assignments.
     *
     * @param  SupportCollection<string, int>  $permissions
     */
    private function ensurePermissions(?Role $role, array $permissionNames, SupportCollection $permissions): void
    {
        if (! $role) {
            return;
        }

        $ids = $permissions->only($permissionNames)->values()->all();

        if ($ids !== []) {
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }
}
