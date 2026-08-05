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

    /**
     * Whether an admin may create this type by hand. A delivery payment is
     * derived from a delivered order and must never be typed in.
     */
    public function isManual(): bool
    {
        return $this !== self::DELIVERY_PAYMENT;
    }

    /**
     * @return array<int, self>
     */
    public static function manualCases(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type) => $type->isManual()));
    }

    /**
     * @return array<int, string>
     */
    public static function manualValues(): array
    {
        return array_map(static fn (self $type) => $type->value, self::manualCases());
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
        return self::optionsFor(self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    public static function manualOptions(): array
    {
        return self::optionsFor(self::manualCases());
    }

    /**
     * @param  array<int, self>  $types
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    private static function optionsFor(array $types): array
    {
        return array_map(
            static fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'color' => $type->color(),
                'icon' => $type->icon(),
            ],
            $types
        );
    }
}
