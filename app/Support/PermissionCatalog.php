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
            self::orderPermissions(),
            self::cityPermissions()
        );
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
            self::make('orders.read.all', 'orders', 'read', 'all', 'resource'),
            self::make('orders.update.own', 'orders', 'update', 'own', 'resource'),
            self::make('orders.update.all', 'orders', 'update', 'all', 'resource'),
            self::make('orders.delete.own', 'orders', 'delete', 'own', 'resource'),
            self::make('orders.delete.all', 'orders', 'delete', 'all', 'resource'),
            self::make('orders.export', 'orders', 'export', null, 'resource'),
            self::make('orders.print', 'orders', 'print', null, 'resource'),
            self::make('orders.transition.to_pickup_requested', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_waiting_pickup', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_picked_up', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_in_depot', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_in_transit', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_in_delivery_city', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_out_for_delivery', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_delivered', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_failed', 'orders', 'transition', null, 'workflow_transition'),
            self::make('orders.transition.to_returned', 'orders', 'transition', null, 'workflow_transition'),
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
