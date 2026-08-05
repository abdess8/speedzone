<?php

namespace App\Enums;

/**
 * What produced a line in the stock ledger.
 *
 * Only MANUAL carries a StockAdjustmentReason: the other two already explain
 * themselves through the document they point at.
 */
enum StockMovementSource: string
{
    /** A person reconciled the shelf against the screen. */
    case MANUAL = 'MANUAL';

    /** An inbound shipment was validated at the depot. */
    case RECEPTION = 'RECEPTION';

    /** Stock left the depot for a customer order. */
    case ORDER = 'ORDER';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $source) => $source->value, self::cases());
    }

    public function label(): string
    {
        return __('stock_movement_sources.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::MANUAL => 'warning',
            self::RECEPTION => 'success',
            self::ORDER => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MANUAL => 'ri-edit-2-line',
            self::RECEPTION => 'ri-inbox-archive-line',
            self::ORDER => 'ri-shopping-basket-2-line',
        };
    }

    public function requiresReason(): bool
    {
        return $this === self::MANUAL;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $source) => [
                'value' => $source->value,
                'label' => $source->label(),
                'color' => $source->color(),
                'icon' => $source->icon(),
            ],
            self::cases()
        );
    }
}
