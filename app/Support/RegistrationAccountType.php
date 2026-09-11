<?php

namespace App\Support;

use App\Models\Role;

/**
 * Public self-serve registration is limited to these two roles. Both follow
 * the same path: create account → verify email → wait for admin approval.
 */
final class RegistrationAccountType
{
    public const SELLER = 'seller';

    public const DRIVER = 'driver';

    public const SESSION_KEY = 'register_account_type';

    /**
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        return [self::SELLER, self::DRIVER];
    }

    public static function from(mixed $value): string
    {
        return in_array($value, self::allowed(), true) ? $value : self::SELLER;
    }

    public static function roleName(mixed $type): string
    {
        return self::from($type) === self::DRIVER ? Role::DRIVER : Role::SELLER;
    }
}
