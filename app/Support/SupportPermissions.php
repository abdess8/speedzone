<?php

namespace App\Support;

/**
 * Canonical permission names for the support ticket module.
 * Assign these to roles via Settings → Roles & Permissions.
 */
final class SupportPermissions
{
    public const CREATE = 'support.create';

    public const READ_OWN = 'support.read.own';

    public const READ_ALL = 'support.read.all';

    public const REPLY = 'support.reply';

    public const ASSIGN = 'support.assign';

    public const UPDATE_STATUS = 'support.update_status';

    public const CLOSE = 'support.close';

    public const MANAGE = 'support.manage';

    /**
     * Permissions that grant back-office / staff visibility on all tickets.
     *
     * @return array<int, string>
     */
    public static function staffAccess(): array
    {
        return [self::READ_ALL, self::MANAGE];
    }

    /**
     * Permissions that allow handling tickets (assign, status, full reply).
     *
     * @return array<int, string>
     */
    public static function staffManagement(): array
    {
        return [self::ASSIGN, self::UPDATE_STATUS, self::MANAGE];
    }

    /**
     * Permissions that allow viewing the support module in navigation.
     *
     * @return array<int, string>
     */
    public static function moduleAccess(): array
    {
        return [self::READ_OWN, self::READ_ALL, self::MANAGE];
    }

    /**
     * Default support permissions for the Seller role (initial + catalog updates).
     *
     * @return array<int, string>
     */
    public static function sellerDefaults(): array
    {
        return [self::CREATE, self::READ_OWN, self::REPLY, self::CLOSE];
    }

    /**
     * Default support permissions for back-office staff (Dispatcher / support agents).
     *
     * @return array<int, string>
     */
    public static function staffDefaults(): array
    {
        return [self::READ_ALL, self::REPLY, self::ASSIGN, self::UPDATE_STATUS, self::CLOSE, self::MANAGE];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::CREATE,
            self::READ_OWN,
            self::READ_ALL,
            self::REPLY,
            self::ASSIGN,
            self::UPDATE_STATUS,
            self::CLOSE,
            self::MANAGE,
        ];
    }
}
