<?php

namespace App\Enums;

/**
 * Why a counted quantity differs from the recorded one.
 *
 * Mandatory on every manual adjustment with a non-zero delta: an inventory
 * correction without a motive is indistinguishable from a data-entry mistake,
 * and shrinkage that is never categorised is shrinkage nobody can act on.
 */
enum StockAdjustmentReason: string
{
    case THEFT_OR_LOSS = 'THEFT_OR_LOSS';
    case DAMAGED = 'DAMAGED';
    case COUNT_ERROR = 'COUNT_ERROR';
    case RETURN_NOT_RESTOCKED = 'RETURN_NOT_RESTOCKED';
    case GIFT_OR_SAMPLE = 'GIFT_OR_SAMPLE';
    /** Opening balance of a reference brought in from an existing catalog. */
    case INITIAL_STOCK = 'INITIAL_STOCK';
    case OTHER = 'OTHER';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $reason) => $reason->value, self::cases());
    }

    public function label(): string
    {
        return __('stock_adjustment_reasons.'.$this->value);
    }

    /**
     * Bootstrap contextual colour used by the reason chips.
     */
    public function color(): string
    {
        return match ($this) {
            self::THEFT_OR_LOSS => 'danger',
            self::DAMAGED => 'warning',
            self::COUNT_ERROR => 'info',
            self::RETURN_NOT_RESTOCKED => 'primary',
            self::GIFT_OR_SAMPLE => 'success',
            self::INITIAL_STOCK => 'dark',
            self::OTHER => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::THEFT_OR_LOSS => 'ri-error-warning-line',
            self::DAMAGED => 'ri-hammer-line',
            self::COUNT_ERROR => 'ri-calculator-line',
            self::RETURN_NOT_RESTOCKED => 'ri-arrow-go-back-line',
            self::GIFT_OR_SAMPLE => 'ri-gift-line',
            self::INITIAL_STOCK => 'ri-flag-line',
            self::OTHER => 'ri-more-2-line',
        };
    }

    /**
     * Reasons a person may pick when reconciling an inventory.
     *
     * INITIAL_STOCK is excluded: it describes a catalog being brought online,
     * which is something the import does, not something a counter chooses.
     *
     * @return array<int, self>
     */
    public static function selectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $reason): bool => $reason !== self::INITIAL_STOCK
        ));
    }

    /**
     * Reasons that require the free-text note to be filled in.
     *
     * "Other" carries no information by itself, and theft is the one line a
     * vendor will be asked to substantiate later.
     */
    public function requiresNote(): bool
    {
        return match ($this) {
            self::OTHER, self::THEFT_OR_LOSS => true,
            default => false,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string, icon: string, requires_note: bool}>
     */
    public static function options(): array
    {
        return self::optionsFrom(self::selectableCases());
    }

    /**
     * Every reason, including the ones only the system writes.
     *
     * Used by the audit filter: a movement the seller cannot create is still a
     * movement an administrator has to be able to look for.
     *
     * @return array<int, array{value: string, label: string, color: string, icon: string, requires_note: bool}>
     */
    public static function auditOptions(): array
    {
        return self::optionsFrom(self::cases());
    }

    /**
     * @param  array<int, self>  $cases
     * @return array<int, array{value: string, label: string, color: string, icon: string, requires_note: bool}>
     */
    private static function optionsFrom(array $cases): array
    {
        return array_map(
            static fn (self $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
                'color' => $reason->color(),
                'icon' => $reason->icon(),
                'requires_note' => $reason->requiresNote(),
            ],
            $cases
        );
    }
}
