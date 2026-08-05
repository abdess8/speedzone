<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum BillingFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case BIWEEKLY = 'biweekly';
    case MONTHLY = 'monthly';
    case CUSTOM = 'custom';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $frequency) => $frequency->value, self::cases());
    }

    public function label(): string
    {
        return __('billing_frequencies.'.$this->value);
    }

    /**
     * Number of days covered by one billing cycle (null for custom).
     */
    public function intervalDays(): ?int
    {
        return match ($this) {
            self::DAILY => 1,
            self::WEEKLY => 7,
            self::BIWEEKLY => 14,
            self::MONTHLY => null,
            self::CUSTOM => null,
        };
    }

    /**
     * Whether invoices for this frequency are produced by the automatic
     * scheduler. Custom frequencies are billed manually by an admin.
     */
    public function isAutomatic(): bool
    {
        return $this !== self::CUSTOM;
    }

    /**
     * Compute the next billing date from a reference date.
     *
     * Returns null for the custom frequency, which is never auto-scheduled.
     */
    public function nextDateFrom(CarbonInterface $from): ?CarbonInterface
    {
        return match ($this) {
            self::DAILY => $from->copy()->addDay(),
            self::WEEKLY => $from->copy()->addWeek(),
            self::BIWEEKLY => $from->copy()->addWeeks(2),
            self::MONTHLY => $from->copy()->addMonthNoOverflow(),
            self::CUSTOM => null,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $frequency) => [
                'value' => $frequency->value,
                'label' => $frequency->label(),
            ],
            self::cases()
        );
    }
}
