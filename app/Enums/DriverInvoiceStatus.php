<?php

namespace App\Enums;

enum DriverInvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case GENERATED = 'GENERATED';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function label(): string
    {
        return __('driver_invoice_statuses.'.$this->value);
    }

    /**
     * Bootstrap contextual colour used by badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::GENERATED => 'info',
            self::PAID => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Remix Icon class used in badges and timelines.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'ri-draft-line',
            self::GENERATED => 'ri-file-list-3-line',
            self::PAID => 'ri-checkbox-circle-line',
            self::CANCELLED => 'ri-close-circle-line',
        };
    }

    /**
     * Whether the invoice snapshot is frozen and must no longer be edited.
     *
     * A paid invoice is locked; anything other than DRAFT is immutable.
     */
    public function isLocked(): bool
    {
        return $this !== self::DRAFT;
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
