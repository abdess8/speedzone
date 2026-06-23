<?php

namespace App\Enums;

enum ReturnInitiatedByRole: string
{
    case SELLER = 'seller';
    case ADMIN = 'admin';
    case DRIVER = 'driver';
    case SYSTEM = 'system';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role) => $role->value, self::cases());
    }

    public function label(): string
    {
        return __('returns.initiated_by.'.$this->value);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            self::cases()
        );
    }
}
