<?php

namespace App\Enums;

enum UserStatus: string
{
    case PendingEmailVerification = 'PENDING_EMAIL_VERIFICATION';
    case PendingApproval = 'PENDING_APPROVAL';
    case Active = 'ACTIVE';
    case Rejected = 'REJECTED';
    /** Access revoked by the vendor admin for one of his team members. */
    case Suspended = 'SUSPENDED';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = trans('user_statuses.'.$case->value);
        }

        return $options;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PendingEmailVerification => 'bg-info-subtle text-info',
            self::PendingApproval => 'bg-warning-subtle text-warning',
            self::Active => 'bg-success-subtle text-success',
            self::Rejected => 'bg-danger-subtle text-danger',
            self::Suspended => 'bg-dark-subtle text-dark',
        };
    }

    public function canAccessPlatform(): bool
    {
        return $this === self::Active;
    }
}
