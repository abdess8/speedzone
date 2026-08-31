<?php

namespace App\Support;

/**
 * Canonical permission names for the e-commerce storefront integrations.
 *
 * The topbar shortcut is gated on READ alone, so an account can be given
 * visibility on which shops are plugged in without also handing over the API
 * keys that let someone re-point the feed.
 */
final class EcommerceIntegrationPermissions
{
    public const READ = 'integrations.read';

    public const MANAGE = 'integrations.manage';

    /**
     * Grants an account owner holds out of the box.
     *
     * @return array<int, string>
     */
    public static function sellerDefaults(): array
    {
        return [self::READ, self::MANAGE];
    }

    /**
     * Permissions that reveal the shortcut in the topbar.
     *
     * @return array<int, string>
     */
    public static function moduleAccess(): array
    {
        return [self::READ, self::MANAGE];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::READ, self::MANAGE];
    }
}
