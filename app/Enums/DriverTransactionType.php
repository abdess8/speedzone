<?php

namespace App\Enums;

enum DriverTransactionType: string
{
    case DELIVERY_PAYMENT = 'DELIVERY_PAYMENT';
    case ADJUSTMENT = 'ADJUSTMENT';
    case BONUS = 'BONUS';
    case PENALTY = 'PENALTY';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    public function label(): string
    {
        return __('driver_transaction_types.'.$this->value);
    }

    /**
     * Whether this transaction increases the driver's balance.
     */
    public function isCredit(): bool
    {
        return $this !== self::PENALTY;
    }

    public function color(): string
    {
        return match ($this) {
            self::DELIVERY_PAYMENT => 'success',
            self::ADJUSTMENT => 'info',
            self::BONUS => 'primary',
            self::PENALTY => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DELIVERY_PAYMENT => 'ri-e-bike-2-line',
            self::ADJUSTMENT => 'ri-scales-3-line',
            self::BONUS => 'ri-gift-line',
            self::PENALTY => 'ri-error-warning-line',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'color' => $type->color(),
                'icon' => $type->icon(),
            ],
            self::cases()
        );
    }
}
