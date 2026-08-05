<?php

namespace App\Support;

use App\Models\Role;

/**
 * Single source of truth for the role → permission grants (RBAC layer).
 *
 * Permission *names* are declared in {@see PermissionCatalog}; this class only
 * decides which role receives which of them. Both the seeder and the
 * `rbac:matrix` export command read from here, so the documented matrix can
 * never drift from what is actually seeded.
 *
 * Ownership/assignment restrictions (the ABAC layer) are *not* expressed here:
 * a permission suffixed `.own` or `.assigned` only unlocks the action, the row
 * level check still runs in the policies and in `User::has*ScopePermission()`.
 */
class RolePermissionMatrix
{
    /**
     * Marks a role as receiving every catalog permission except the exclusions.
     */
    public const WILDCARD = '*';

    /**
     * Permissions that must never be granted to a wildcard role because they
     * encode a seller-side business action rather than an administrative one.
     *
     * @var array<int, string>
     */
    public const SELLER_ONLY = [
        'returns.create_request',
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return [
            Role::ADMIN => [self::WILDCARD],
            Role::DISPATCHER => self::dispatcher(),
            Role::DRIVER => self::driver(),
            Role::SELLER => self::seller(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function for(string $role): array
    {
        return self::all()[$role] ?? [];
    }

    /**
     * Back-office staff: full operational visibility, no configuration rights.
     *
     * @return array<int, string>
     */
    public static function dispatcher(): array
    {
        return [
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
            'orders.transition.to_prepared',
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
            'returns.transition.to_received_at_hub',
            'returns.transition.to_in_transit_to_depot',
            'returns.transition.to_arrived_vendor_hub',
            'returns.transition.to_in_delivery_to_vendor',
            'returns.transition.to_delivered_to_vendor',
            'invoices.read.all',
            'invoices.print',
            'driver_invoices.read.all',
            'driver_invoices.print',
            'driver_invoices.assign_driver',
            ...DashboardPermissions::staffDefaults(),
            ...SupportPermissions::staffDefaults(),
            ...StockPermissions::staffDefaults(),
        ];
    }

    /**
     * Field agent: sees only what is assigned to him and may only advance the
     * delivery leg of the workflow.
     *
     * @return array<int, string>
     */
    public static function driver(): array
    {
        return [
            'orders.read.assigned',
            // Scoped to rows where driver_id = auth id, and further narrowed to
            // status-only edits by OrderPolicy::updateStatus().
            'orders.update.assigned',
            'orders.print',
            'pickup_requests.read.assigned',
            'pickup_requests.pickup',
            // Collecting a vendor's stock is the same round as collecting his
            // parcels. It carries no depot rights: he counts what he loads, and
            // the hub counts it again on arrival.
            StockPermissions::COLLECT_INBOUND,
            'transfers.read.assigned',
            'transfers.receive',
            'orders.transition.to_out_for_delivery',
            'orders.transition.to_delivered',
            'orders.transition.to_failed',
            'returns.create',
            // The hand-back leg only: signing a parcel into a hub belongs to
            // the hub manager, not to the driver who dropped it there.
            'returns.transition.to_in_delivery_to_vendor',
            'returns.transition.to_delivered_to_vendor',
            'driver_invoices.read.own',
            'driver_invoices.print',
            // His round and his own numbers. A driver has no reason to read the
            // turnover of the shops he collects from.
            ...DashboardPermissions::driverDefaults(),
        ];
    }

    /**
     * Merchant: strictly limited to the resources he owns.
     *
     * @return array<int, string>
     */
    public static function seller(): array
    {
        return [
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
            'stores.read',
            'stores.create',
            'stores.update',
            'stores.delete',
            'team.read',
            'team.create',
            'team.update',
            'team.suspend',
            'team_roles.manage',
            ...DashboardPermissions::sellerDefaults(),
            ...SupportPermissions::sellerDefaults(),
            ...StockPermissions::sellerDefaults(),
        ];
    }

    /**
     * Account administration a vendor may not delegate to his team.
     *
     * Granting any of these to a custom role would let a member widen his own
     * access, so they are excluded from the ceiling used when composing team
     * roles.
     *
     * @var array<int, string>
     */
    public const ACCOUNT_ADMIN_ONLY = [
        'stores.create',
        'stores.update',
        'stores.delete',
        'team.read',
        'team.create',
        'team.update',
        'team.suspend',
        'team_roles.manage',
    ];

    /**
     * The widest set of permissions a vendor may grant to a team member.
     *
     * @return array<int, string>
     */
    public static function sellerCeiling(): array
    {
        return array_values(array_diff(self::seller(), self::ACCOUNT_ADMIN_ONLY));
    }

    /**
     * Maps the entity names used in the functional specification onto the
     * resources actually implemented, so a reader of the exported matrix can
     * tell a renaming apart from a missing module.
     *
     * @var array<string, array<string, string>>
     */
    public const ENTITY_ALIASES = [
        'pickups' => [
            'resource' => 'pickup_requests',
            'note' => 'Ramassages are modelled as pickup requests (PickupRequest).',
        ],
        'tickets' => [
            'resource' => 'support',
            'note' => 'Support tickets (SupportTicket) live under the "support" resource.',
        ],
        'depots' => [
            'resource' => 'stock',
            'note' => 'Stock is held per vendor shop (Store), not per warehouse: a vendor\'s '
                .'inventory is a single figure across our depots. Inbound shipments (StockReception) '
                .'are the document trail of goods physically arriving at one.',
        ],
        'cashboxes' => [
            'resource' => 'driver_invoices',
            'note' => 'A driver\'s caisse is his wallet: DriverInvoice + DriverTransaction, '
                .'read with driver_invoices.read.own and surfaced by /driver-finance.',
        ],
    ];

    /**
     * Machine-readable matrix, grouped by resource then action, listing the
     * scope each role is granted. Consumed by `php artisan rbac:matrix`.
     *
     * @return array<string, mixed>
     */
    public static function export(): array
    {
        $catalog = PermissionCatalog::all();
        $grants = self::resolvedGrants();

        $resources = [];

        foreach ($catalog as $permission) {
            $name = $permission['name'];

            $resources[$permission['resource']][$permission['action']][self::variantKey($permission)] = [
                'permission' => $name,
                'type' => $permission['type'],
                'roles' => array_values(array_filter(
                    array_keys($grants),
                    static fn (string $role): bool => in_array($name, $grants[$role], true)
                )),
            ];
        }

        ksort($resources);

        return [
            'roles' => array_keys(self::all()),
            'scopes' => [
                'all' => 'RBAC only — every row of the resource.',
                'own' => 'ABAC — rows created by / belonging to the actor.',
                'assigned' => 'ABAC — rows assigned to the actor.',
                'any' => 'Scopeless action (no row-level dimension).',
            ],
            'entity_aliases' => self::ENTITY_ALIASES,
            'resources' => $resources,
        ];
    }

    /**
     * Key that distinguishes two permissions sharing a resource and an action.
     *
     * Scope is the usual discriminator, but every `orders.transition.*` entry is
     * scopeless, so grouping them by scope collapsed all fourteen of them onto a
     * single line. Transitions are keyed by their target status instead.
     *
     * @param  array<string, string|null>  $permission
     */
    private static function variantKey(array $permission): string
    {
        if ($permission['type'] === 'workflow_transition') {
            $target = strrchr((string) $permission['name'], '.');

            return $target === false ? 'any' : substr($target, 1);
        }

        return $permission['scope'] ?? 'any';
    }

    /**
     * Expand the wildcard role into its concrete permission list.
     *
     * @return array<string, array<int, string>>
     */
    public static function resolvedGrants(): array
    {
        $everything = array_column(PermissionCatalog::all(), 'name');

        $resolved = [];

        foreach (self::all() as $role => $permissions) {
            $resolved[$role] = in_array(self::WILDCARD, $permissions, true)
                ? array_values(array_diff($everything, self::SELLER_ONLY))
                : array_values(array_unique($permissions));
        }

        return $resolved;
    }
}
