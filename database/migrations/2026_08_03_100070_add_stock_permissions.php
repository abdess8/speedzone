<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\StockPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Vendor fulfilment permissions.
     *
     * The five vendor-side grants are inside RolePermissionMatrix::sellerCeiling(),
     * so an owner may delegate any of them to a team role — that delegation is
     * the point of the module. The two hub-side grants are not: signing stock
     * into a depot and auditing every vendor's movements are our operations, not
     * the vendor's, and they are seeded to staff roles only.
     *
     * @var array<string, array{resource: string, action: string, roles: array<int, string>}>
     */
    private const PERMISSIONS = [
        StockPermissions::VIEW => [
            'resource' => 'stock',
            'action' => 'view',
            'roles' => [Role::SELLER, Role::DISPATCHER],
        ],
        StockPermissions::CREATE_PRODUCT => [
            'resource' => 'stock',
            'action' => 'create_product',
            'roles' => [Role::SELLER],
        ],
        StockPermissions::CREATE_INBOUND => [
            'resource' => 'stock',
            'action' => 'create_inbound',
            'roles' => [Role::SELLER],
        ],
        StockPermissions::ADJUST => [
            'resource' => 'stock',
            'action' => 'adjust',
            'roles' => [Role::SELLER],
        ],
        StockPermissions::ORDERS_CREATE_WITH_STOCK => [
            'resource' => 'orders',
            'action' => 'create_with_stock',
            'roles' => [Role::SELLER],
        ],
        StockPermissions::RECEIVE_INBOUND => [
            'resource' => 'stock',
            'action' => 'receive_inbound',
            'roles' => [Role::DISPATCHER],
        ],
        StockPermissions::ADMIN_OVERRIDE => [
            'resource' => 'stock',
            'action' => 'admin_override',
            'roles' => [],
        ],
    ];

    public function up(): void
    {
        $grants = [];

        foreach (self::PERMISSIONS as $name => $definition) {
            $id = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => $definition['resource'],
                    'action' => $definition['action'],
                    'scope' => null,
                    'type' => $name === StockPermissions::ADMIN_OVERRIDE ? 'admin' : 'resource',
                ]
            )->id;

            // Super admins hold everything; the per-role lists below only widen
            // that with the operational roles each permission belongs to.
            foreach ([...User::SUPER_ADMIN_ROLES, ...$definition['roles']] as $role) {
                $grants[$role][] = $id;
            }
        }

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', array_keys($grants))
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($grants[$role->name]));
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->delete();
    }
};
