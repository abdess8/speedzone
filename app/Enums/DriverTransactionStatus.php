<?php

namespace App\Enums;

enum DriverTransactionStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case PAID = 'PAID';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function label(): string
    {
        return __('driver_transaction_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::CONFIRMED => 'info',
            self::PAID => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'ri-time-line',
            self::CONFIRMED => 'ri-checkbox-circle-line',
            self::PAID => 'ri-money-dollar-circle-line',
        };
    }

    /**
     * Whether the transaction is frozen because it belongs to an invoice.
     */
    public function isLocked(): bool
    {
        return $this === self::PAID;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
                'icon' => $status->icon(),
            ],
            self::cases()
        );
    }
}
