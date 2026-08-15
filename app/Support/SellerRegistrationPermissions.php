<?php

namespace App\Support;

/**
 * Default seller permissions assignable during admin approval.
 */
final class SellerRegistrationPermissions
{
    /**
     * @return array<int, string>
     */
    public static function assignable(): array
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
            'returns.create_request',
            'returns.read.own',
            'support.create',
            'support.read.own',
            'support.reply',
            'support.close',
            'cities.read',
            'sectors.read',
            'invoices.read.own',
            'invoices.print',
            ...NotificationPermissions::sellerDefaults(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return [
            'orders.create',
            'orders.read.own',
            'pickup_requests.create',
            'returns.create_request',
            'support.create',
            // A merchant who hears nothing about his own invoices and tickets
            // has to go looking for them, so the topics that concern him are on
            // from the start.
            ...NotificationPermissions::sellerDefaults(),
        ];
    }
}
