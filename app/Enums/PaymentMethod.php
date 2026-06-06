<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case COD = 'COD';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $method) => $method->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::COD => 'Cash on Delivery',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $method) => ['value' => $method->value, 'label' => $method->label()],
            self::cases()
        );
    }
}
