<?php

namespace App\Enums;

/**
 * What a transfer manifest carries.
 *
 * The two directions of the network share one truck: outbound parcels heading
 * to customers, and undelivered ones heading back to their seller. They are
 * picked from different pools and validated against different rules, so the
 * manifest states up front which pools it draws from.
 */
enum TransferContentType: string
{
    case ORDERS = 'ORDERS';
    case RETURNS = 'RETURNS';
    case MIXED = 'MIXED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    public function label(): string
    {
        return __('transfer_content_types.'.$this->value);
    }

    public function description(): string
    {
        return __('transfer_content_types.descriptions.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::ORDERS => 'ri-box-3-line',
            self::RETURNS => 'ri-arrow-go-back-line',
            self::MIXED => 'ri-shuffle-line',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ORDERS => 'primary',
            self::RETURNS => 'warning',
            self::MIXED => 'info',
        };
    }

    public function includesOrders(): bool
    {
        return $this !== self::RETURNS;
    }

    public function includesReturns(): bool
    {
        return $this !== self::ORDERS;
    }

    /**
     * Narrow a manifest to what it actually ended up carrying, so a "mixed"
     * transfer that received only returns does not keep advertising a pool it
     * never used.
     */
    public static function fromCounts(int $orderCount, int $returnCount): self
    {
        return match (true) {
            $orderCount > 0 && $returnCount > 0 => self::MIXED,
            $returnCount > 0 => self::RETURNS,
            default => self::ORDERS,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, icon: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'color' => $type->color(),
            ],
            self::cases()
        );
    }
}
