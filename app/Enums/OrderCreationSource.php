<?php

namespace App\Enums;

/**
 * How a SpeedZone parcel was born. Set by the server, never by the form.
 */
enum OrderCreationSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case Integration = 'integration';
    case Partner = 'partner';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $source) => $source->value, self::cases());
    }

    public function label(): string
    {
        return __('orders.creation_source.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'secondary',
            self::Import => 'info',
            self::Integration => 'primary',
            self::Partner => 'warning',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $source) => [
                'value' => $source->value,
                'label' => $source->label(),
            ],
            self::cases()
        );
    }
}
