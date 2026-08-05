<?php

namespace App\Enums;

use App\Services\DeliveryOutcomeService;

/**
 * What the driver reports when he closes a delivery attempt.
 *
 * Deliberately not an OrderStatus: FAILED describes the attempt, not the
 * parcel. Where the order lands afterwards depends on the failure reason, and
 * is resolved by {@see DeliveryOutcomeService}.
 */
enum DeliveryOutcome: string
{
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $outcome) => $outcome->value, self::cases());
    }

    public function label(): string
    {
        return __('orders.delivery_outcome.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::DELIVERED => 'success',
            self::FAILED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DELIVERED => 'ri-checkbox-circle-line',
            self::FAILED => 'ri-close-circle-line',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $outcome) => [
                'value' => $outcome->value,
                'label' => $outcome->label(),
                'color' => $outcome->color(),
                'icon' => $outcome->icon(),
            ],
            self::cases()
        );
    }
}
