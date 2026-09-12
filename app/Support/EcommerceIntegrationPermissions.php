<?php

namespace App\Support;

use App\Enums\EcommercePlatform;

/**
 * Canonical permission names for the e-commerce storefront integrations.
 *
 * Split on purpose:
 *  - {@see self::READ} is visibility: the catalogue and the connection status.
 *  - {@see self::MANAGE} is the umbrella: connect, reconfigure or disconnect
 *    any platform. Account owners hold it so they never have to tick four boxes.
 *  - One grant per platform, so a vendor can let a team member plug YouCan
 *    without also handing over a Shopify shop's API keys.
 *
 * The topbar shortcut is gated on any of these, so an account given only the
 * YouCan grant still finds the screen, and one given none of them does not
 * walk into a 403.
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
        return self::all();
    }

    /**
     * Permissions that reveal the shortcut in the topbar and open the catalogue.
     *
     * @return array<int, string>
     */
    public static function moduleAccess(): array
    {
        return self::all();
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::READ, self::MANAGE, ...self::platformGrants()];
    }

    /**
     * Connect, reconfigure or disconnect any platform.
     *
     * @return array<int, string>
     */
    public static function connectAny(): array
    {
        return [self::MANAGE, ...self::platformGrants()];
    }

    /**
     * Connect, reconfigure or disconnect this platform: the umbrella or the
     * platform-specific grant.
     *
     * @return array<int, string>
     */
    public static function connect(EcommercePlatform $platform): array
    {
        return [self::MANAGE, self::manage($platform)];
    }

    public static function manage(EcommercePlatform $platform): string
    {
        return 'integrations.manage.'.$platform->value;
    }

    /**
     * @return array<int, string>
     */
    public static function platformGrants(): array
    {
        return array_map(
            static fn (EcommercePlatform $platform) => self::manage($platform),
            EcommercePlatform::cases()
        );
    }

    public static function canConnect(mixed $user, EcommercePlatform $platform): bool
    {
        if ($user === null) {
            return false;
        }

        foreach (self::connect($platform) as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pipe-joined list for the `permission:` route middleware.
     */
    public static function middleware(array $permissions): string
    {
        return 'permission:'.implode('|', $permissions);
    }
}
