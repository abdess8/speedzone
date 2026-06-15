<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case GENERATED = 'GENERATED';
    case SENT = 'SENT';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    /**
     * Human-friendly label for the status.
     */
    public function label(): string
    {
        return __('invoice_statuses.'.$this->value);
    }

    /**
     * Bootstrap contextual colour used by badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::GENERATED => 'info',
            self::SENT => 'primary',
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
            self::SENT => 'ri-mail-send-line',
            self::PAID => 'ri-checkbox-circle-line',
            self::CANCELLED => 'ri-close-circle-line',
        };
    }

    /**
     * Whether the invoice is frozen and must no longer be edited.
     *
     * Once an invoice is generated the snapshot is immutable; only the
     * GENERATED -> PAID and GENERATED -> CANCELLED transitions are allowed.
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
