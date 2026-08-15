<?php

namespace App\Support;

class PermissionCatalog
{
    /**
     * @return array<int, array<string, string|null>>
     */
    public static function all(): array
    {
        return array_merge(
            self::adminPermissions(),
            self::dashboardPermissions(),
            self::orderPermissions(),
            self::pickupRequestPermissions(),
            self::transferPermissions(),
            self::returnPermissions(),
            self::invoicePermissions(),
            self::driverInvoicePermissions(),
            self::supportPermissions(),
            self::cityPermissions(),
            self::sectorPermissions(),
            self::alertPermissions(),
            self::driverZonePermissions(),
            self::partnerPermissions(),
            self::storePermissions(),
            self::teamPermissions(),
            self::stockPermissions(),
            self::ecommerceIntegrationPermissions(),
            self::statusTransitionPermissions()
        );
    }

    /**
     * One grant per `source status → target status` edge, for both orders and
     * returns.
     *
     * Derived from the transition graphs rather than listed, so an edge added
     * to a graph becomes manageable by the administrator on the next seed
     * instead of silently escaping the bulk-edit matrix.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function statusTransitionPermissions(): array
    {
        return StatusTransitionPermissions::catalog();
    }

    /**
     * Plugging a vendor's own shop (Shopify, YouCan, WooCommerce, PrestaShop)
     * into the platform.
     *
     * Inside the seller ceiling: the shop is the vendor's, and whether the
     * person who runs his back office may connect it is his call. Reading is
     * split from managing because the credentials are the sensitive half —
     * seeing that a store is connected is not.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function ecommerceIntegrationPermissions(): array
    {
        return [
            self::make(EcommerceIntegrationPermissions::READ, 'integrations', 'read', null, 'resource'),
            self::make(EcommerceIntegrationPermissions::MANAGE, 'integrations', 'manage', null, 'resource'),
        ];
    }

    /**
     * Dashboard panels, one grant per family of widgets.
     *
     * All of them are read-only and all of them are inside the seller ceiling:
     * whether a warehouse employee sees the shop's turnover next to the parcels
     * he has to pack is the account owner's decision, not ours.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function dashboardPermissions(): array
    {
        return [
            self::make(DashboardPermissions::VIEW, 'dashboard', 'view', null, 'resource'),
            self::make(DashboardPermissions::VIEW_FINANCIALS, 'dashboard', 'view_financials', null, 'resource'),
            self::make(DashboardPermissions::VIEW_OPERATIONS, 'dashboard', 'view_operations', null, 'resource'),
            self::make(DashboardPermissions::VIEW_PERFORMANCE, 'dashboard', 'view_performance', null, 'resource'),
            self::make(DashboardPermissions::VIEW_CUSTOMERS, 'dashboard', 'view_customers', null, 'resource'),
            self::make(DashboardPermissions::VIEW_NETWORK, 'dashboard', 'view_network', null, 'resource'),
        ];
    }

    /**
     * Vendor fulfilment: catalog, inbound shipments, inventory.
     *
     * The five vendor grants are inside the seller ceiling — delegating them to
     * a warehouse employee is the point of the module. The three hub grants are
     * not: collecting stock from a shop, counting it in at the depot and auditing
     * every vendor's movements are our operations on someone else's goods.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function stockPermissions(): array
    {
        return [
            self::make(StockPermissions::VIEW, 'stock', 'view', null, 'resource'),
            self::make(StockPermissions::CREATE_PRODUCT, 'stock', 'create_product', null, 'resource'),
            self::make(StockPermissions::IMPORT_PRODUCTS, 'stock', 'import_products', null, 'resource'),
            self::make(StockPermissions::CREATE_INBOUND, 'stock', 'create_inbound', null, 'resource'),
            self::make(StockPermissions::ADJUST, 'stock', 'adjust', null, 'resource'),
            self::make(StockPermissions::ORDERS_CREATE_WITH_STOCK, 'orders', 'create_with_stock', null, 'resource'),
            self::make(StockPermissions::COLLECT_INBOUND, 'stock', 'collect_inbound', null, 'resource'),
            self::make(StockPermissions::RECEIVE_INBOUND, 'stock', 'receive_inbound', null, 'resource'),
            self::make(StockPermissions::ADMIN_OVERRIDE, 'stock', 'admin_override', null, 'admin'),
        ];
    }

    /**
     * Vendor team administration.
     *
     * Like the store write permissions, these are excluded from the seller
     * ceiling: a member allowed to manage the team could grant himself any
     * role, which would make every other restriction moot.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function teamPermissions(): array
    {
        return [
            self::make('team.read', 'team', 'read', null, 'resource'),
            self::make('team.create', 'team', 'create', null, 'resource'),
            self::make('team.update', 'team', 'update', null, 'resource'),
            self::make('team.suspend', 'team', 'suspend', null, 'resource'),
            self::make('team_roles.manage', 'team_roles', 'manage', null, 'resource'),
        ];
    }

    /**
     * Vendor shop management.
     *
     * Deliberately kept out of the seller permission ceiling used for custom
     * team roles: a member who could create a store could also grant himself
     * access to it.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function storePermissions(): array
    {
        return [
            self::make('stores.read', 'stores', 'read', null, 'resource'),
            self::make('stores.create', 'stores', 'create', null, 'resource'),
            self::make('stores.update', 'stores', 'update', null, 'resource'),
            self::make('stores.delete', 'stores', 'delete', null, 'resource'),
        ];
    }

    /**
     * B2B partner integration permissions.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function partnerPermissions(): array
    {
        return [
            self::make('partners.create', 'partners', 'create', null, 'admin'),
            self::make('partners.read', 'partners', 'read', null, 'admin'),
            self::make('partners.update', 'partners', 'update', null, 'admin'),
            self::make('partners.delete', 'partners', 'delete', null, 'admin'),
            self::make('partners.sync', 'partners', 'sync', null, 'resource'),
            self::make('partners.deliveries.manage', 'partners', 'deliveries.manage', null, 'resource'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function adminPermissions(): array
    {
        return [
            self::make('permissions.create', 'permissions', 'create', null, 'admin'),
            self::make('permissions.read', 'permissions', 'read', null, 'admin'),
            self::make('permissions.update', 'permissions', 'update', null, 'admin'),
            self::make('permissions.delete', 'permissions', 'delete', null, 'admin'),
            self::make('roles.create', 'roles', 'create', null, 'admin'),
            self::make('roles.read', 'roles', 'read', null, 'admin'),
            self::make('roles.update', 'roles', 'update', null, 'admin'),
            self::make('roles.delete', 'roles', 'delete', null, 'admin'),
            self::make('users.read', 'users', 'read', null, 'admin'),
            self::make('users.create', 'users', 'create', null, 'admin'),
            self::make('users.update', 'users', 'update', null, 'admin'),
            self::make('users.delete', 'users', 'delete', null, 'admin'),
            self::make('users.roles.assign', 'users', 'roles.assign', null, 'admin'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function orderPermissions(): array
    {
        return [
            self::make('orders.create', 'orders', 'create', null, 'resource'),
            self::make('orders.read.own', 'orders', 'read', 'own', 'resource'),
            self::make('orders.read.assigned', 'orders', 'read', 'assigned', 'resource'),
            self::make('orders.read.all', 'orders', 'read', 'all', 'resource'),
            self::make('orders.update.own', 'orders', 'update', 'own', 'resource'),
            self::make('orders.update.assigned', 'orders', 'update', 'assigned', 'resource'),
            self::make('orders.update.all', 'orders', 'update', 'all', 'resource'),
            self::make('orders.delete.own', 'orders', 'delete', 'own', 'resource'),
            self::make('orders.delete.all', 'orders', 'delete', 'all', 'resource'),
            self::make('orders.export', 'orders', 'export', null, 'resource'),
            self::make('orders.print', 'orders', 'print', null, 'resource'),
            // Picking and packing a stock order at the depot. There is no
            // matching grant for AWAITING_PREPARATION: that status is stamped at
            // creation, never chosen.
            self::make('orders.transition.to_prepared', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_pickup_requested', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_waiting_pickup', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_picked_up', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_in_depot', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_transfer_created', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_in_transit', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_received_in_destination', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_in_delivery_city', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_out_for_delivery', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_delivered', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_failed', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_ready_to_return', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_rejected', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_canceled', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_returned', 'orders', 'transition', null, 'workflow_transition'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function pickupRequestPermissions(): array
    {
        return [
            self::make('pickup_requests.create', 'pickup_requests', 'create', null, 'resource'),
            self::make('pickup_requests.read.own', 'pickup_requests', 'read', 'own', 'resource'),
            self::make('pickup_requests.read.all', 'pickup_requests', 'read', 'all', 'resource'),
            self::make('pickup_requests.read.assigned', 'pickup_requests', 'read', 'assigned', 'resource'),
            self::make('pickup_requests.assign', 'pickup_requests', 'assign', null, 'resource'),
            self::make('pickup_requests.change_status', 'pickup_requests', 'change_status', null, 'resource'),
            self::make('pickup_requests.pickup', 'pickup_requests', 'pickup', null, 'resource'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function transferPermissions(): array
    {
        return [
            self::make('transfers.create', 'transfers', 'create', null, 'resource'),
            self::make('transfers.read', 'transfers', 'read', null, 'resource'),
            self::make('transfers.read.assigned', 'transfers', 'read', 'assigned', 'resource'),
            self::make('transfers.update', 'transfers', 'update', null, 'resource'),
            self::make('transfers.dispatch', 'transfers', 'dispatch', null, 'resource'),
            self::make('transfers.receive', 'transfers', 'receive', null, 'resource'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function returnPermissions(): array
    {
        return [
            self::make('returns.create_request', 'returns', 'create_request', null, 'resource'),
            self::make('returns.read.own', 'returns', 'read', 'own', 'resource'),
            self::make('returns.read.all', 'returns', 'read', 'all', 'resource'),
            self::make('returns.create', 'returns', 'create', null, 'resource'),
            self::make('returns.manage', 'returns', 'manage', null, 'resource'),
            self::make('returns.update_status', 'returns', 'update_status', null, 'resource'),
            self::make('returns.edit_customer_data', 'returns', 'edit_customer_data', null, 'resource'),
            // Per-step grants, so a hub manager can stamp arrivals without also
            // being able to close the return at the seller's door. The blanket
            // `returns.update_status` still satisfies all of them.
            self::make('returns.transition.to_received_at_hub', 'returns', 'transition', null, 'workflow_transition'),
            self::make('returns.transition.to_in_transit_to_depot', 'returns', 'transition', null, 'workflow_transition'),
            self::make('returns.transition.to_arrived_vendor_hub', 'returns', 'transition', null, 'workflow_transition'),
            self::make('returns.transition.to_in_delivery_to_vendor', 'returns', 'transition', null, 'workflow_transition'),
            self::make('returns.transition.to_delivered_to_vendor', 'returns', 'transition', null, 'workflow_transition'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function invoicePermissions(): array
    {
        return [
            self::make('invoices.read.own', 'invoices', 'read', 'own', 'resource'),
            self::make('invoices.read.all', 'invoices', 'read', 'all', 'resource'),
            self::make('invoices.generate', 'invoices', 'generate', null, 'resource'),
            self::make('invoices.pay', 'invoices', 'pay', null, 'resource'),
            self::make('invoices.cancel', 'invoices', 'cancel', null, 'resource'),
            self::make('invoices.delete', 'invoices', 'delete', null, 'resource'),
            self::make('invoices.print', 'invoices', 'print', null, 'resource'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function driverInvoicePermissions(): array
    {
        return [
            self::make('driver_invoices.read.own', 'driver_invoices', 'read', 'own', 'resource'),
            self::make('driver_invoices.read.all', 'driver_invoices', 'read', 'all', 'resource'),
            self::make('driver_invoices.generate', 'driver_invoices', 'generate', null, 'resource'),
            self::make('driver_invoices.pay', 'driver_invoices', 'pay', null, 'resource'),
            self::make('driver_invoices.cancel', 'driver_invoices', 'cancel', null, 'resource'),
            self::make('driver_invoices.delete', 'driver_invoices', 'delete', null, 'resource'),
            self::make('driver_invoices.print', 'driver_invoices', 'print', null, 'resource'),
            self::make('driver_invoices.assign_driver', 'driver_invoices', 'assign_driver', null, 'resource'),
            self::make('driver_invoices.adjust', 'driver_invoices', 'adjust', null, 'resource'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function supportPermissions(): array
    {
        return [
            self::make(SupportPermissions::CREATE, 'support', 'create', null, 'resource'),
            self::make(SupportPermissions::READ_OWN, 'support', 'read', 'own', 'resource'),
            self::make(SupportPermissions::READ_ALL, 'support', 'read', 'all', 'resource'),
            self::make(SupportPermissions::REPLY, 'support', 'reply', null, 'resource'),
            self::make(SupportPermissions::ASSIGN, 'support', 'assign', null, 'resource'),
            self::make(SupportPermissions::UPDATE_STATUS, 'support', 'update_status', null, 'resource'),
            self::make(SupportPermissions::CLOSE, 'support', 'close', null, 'resource'),
            self::make(SupportPermissions::MANAGE, 'support', 'manage', null, 'resource'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function cityPermissions(): array
    {
        return [
            self::make('cities.create', 'cities', 'create', null, 'admin'),
            self::make('cities.read', 'cities', 'read', null, 'admin'),
            self::make('cities.update', 'cities', 'update', null, 'admin'),
            self::make('cities.delete', 'cities', 'delete', null, 'admin'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function alertPermissions(): array
    {
        return [
            self::make('alerts.create', 'alerts', 'create', null, 'admin'),
            self::make('alerts.read', 'alerts', 'read', null, 'admin'),
            self::make('alerts.update', 'alerts', 'update', null, 'admin'),
            self::make('alerts.delete', 'alerts', 'delete', null, 'admin'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function sectorPermissions(): array
    {
        return [
            self::make('sectors.create', 'sectors', 'create', null, 'admin'),
            self::make('sectors.read', 'sectors', 'read', null, 'admin'),
            self::make('sectors.update', 'sectors', 'update', null, 'admin'),
            self::make('sectors.delete', 'sectors', 'delete', null, 'admin'),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public static function driverZonePermissions(): array
    {
        return [
            self::make('driver_zones.read', 'driver_zones', 'read', null, 'admin'),
            self::make('driver_zones.assign', 'driver_zones', 'assign', null, 'admin'),
            self::make('driver_zones.remove', 'driver_zones', 'remove', null, 'admin'),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function make(
        string $name,
        string $resource,
        string $action,
        ?string $scope,
        string $type
    ): array {
        return [
            'name' => $name,
            'resource' => $resource,
            'action' => $action,
            'scope' => $scope,
            'type' => $type,
            'description' => null,
        ];
    }
}
