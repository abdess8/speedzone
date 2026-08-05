<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\DashboardPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Per-widget dashboard grants.
     *
     * The dashboard used to be all-or-nothing: anyone who could read an order
     * could read the turnover, the customer list and every driver's timings on
     * the same screen. These six permissions cut it into the questions it
     * answers, so a vendor can hand the operational half to the employee who
     * packs his parcels without handing over his revenue with it.
     *
     * @var array<string, array{action: string, roles: array<int, string>}>
     */
    private const PERMISSIONS = [
        DashboardPermissions::VIEW => [
            'action' => 'view',
            'roles' => [Role::DISPATCHER, Role::DRIVER, Role::SELLER],
        ],
        DashboardPermissions::VIEW_FINANCIALS => [
            'action' => 'view_financials',
            'roles' => [Role::DISPATCHER, Role::SELLER],
        ],
        DashboardPermissions::VIEW_OPERATIONS => [
            'action' => 'view_operations',
            'roles' => [Role::DISPATCHER, Role::DRIVER, Role::SELLER],
        ],
        DashboardPermissions::VIEW_PERFORMANCE => [
            'action' => 'view_performance',
            'roles' => [Role::DISPATCHER, Role::DRIVER, Role::SELLER],
        ],
        DashboardPermissions::VIEW_CUSTOMERS => [
            'action' => 'view_customers',
            'roles' => [Role::DISPATCHER, Role::SELLER],
        ],
        DashboardPermissions::VIEW_NETWORK => [
            'action' => 'view_network',
            'roles' => [Role::DISPATCHER, Role::SELLER],
        ],
    ];

    public function up(): void
    {
        $grants = [];
        $ids = [];

        foreach (self::PERMISSIONS as $name => $definition) {
            $id = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => 'dashboard',
                    'action' => $definition['action'],
                    'scope' => null,
                    'type' => 'resource',
                ]
            )->id;

            $ids[] = $id;

            foreach ([...User::SUPER_ADMIN_ROLES, ...$definition['roles']] as $role) {
                $grants[$role][] = $id;
            }
        }

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', array_keys($grants))
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($grants[$role->name]));

        // Custom team roles already opened the whole dashboard through their
        // order-read grant. Introducing the split must not quietly take panels
        // away from someone who has them today, so every existing role keeps the
        // full set and the owner narrows it from the team screen when he wants to.
        Role::query()
            ->whereNotNull('owner_id')
            ->whereHas('permissions', fn ($query) => $query->where('name', 'orders.read.own'))
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->delete();
    }
};
