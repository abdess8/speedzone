<?php

namespace App\Enums;

/**
 * Lifecycle of an inbound shipment, following the goods rather than the paperwork.
 *
 * The vendor prepares a slip (DRAFT) and declares it ready, at which point it is
 * on us to come and get it (AWAITING_PICKUP). A collector goes to the shop, counts
 * what is actually handed over and signs for it (COLLECTED) — that count is the
 * first one made by somebody with nothing to gain from it. He then drives the
 * goods to the depot (IN_TRANSIT), where an agent counts them a second time and
 * closes the document (VALIDATED). Only that last count reaches the catalog.
 *
 * Three counts on one document is not redundancy: it is what makes a loss
 * attributable. A gap between the declaration and the collection happened at the
 * shop; a gap between the collection and the depot happened on the road.
 *
 * There is deliberately no "partially received" state: a shipment where three of
 * ten units were unusable is still one closed document, with the gap recorded per
 * line.
 */
enum StockReceptionStatus: string
{
    case DRAFT = 'DRAFT';
    case AWAITING_PICKUP = 'AWAITING_PICKUP';
    case COLLECTED = 'COLLECTED';
    case IN_TRANSIT = 'IN_TRANSIT';
    case VALIDATED = 'VALIDATED';
    case CANCELLED = 'CANCELLED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    /**
     * Statuses in the order the shipment travels through them.
     *
     * @return array<int, self>
     */
    public static function pipeline(): array
    {
        return [
            self::DRAFT,
            self::AWAITING_PICKUP,
            self::COLLECTED,
            self::IN_TRANSIT,
            self::VALIDATED,
        ];
    }

    public function label(): string
    {
        return __('stock_reception_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            // Amber because it is a queue somebody has to work, not a state the
            // document rests in.
            self::AWAITING_PICKUP => 'warning',
            self::COLLECTED => 'info',
            self::IN_TRANSIT => 'primary',
            self::VALIDATED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'ri-draft-line',
            self::AWAITING_PICKUP => 'ri-time-line',
            self::COLLECTED => 'ri-truck-line',
            self::IN_TRANSIT => 'ri-route-line',
            self::VALIDATED => 'ri-checkbox-circle-line',
            self::CANCELLED => 'ri-close-circle-line',
        };
    }

    /**
     * Whether the vendor may still edit the lines.
     *
     * Only while nobody has been asked to come for the parcel. Once it is in the
     * pickup queue the declaration is frozen: it is the document the collector
     * will count against.
     */
    public function isEditableByVendor(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Whether the goods have physically left the shop.
     *
     * From here on the vendor cannot call the shipment off — the parcel is in our
     * hands and only we can account for it.
     */
    public function isInOurHands(): bool
    {
        return in_array($this, [self::COLLECTED, self::IN_TRANSIT, self::VALIDATED], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::VALIDATED, self::CANCELLED], true);
    }

    /**
     * Statuses this one may move to.
     *
     * @return array<int, self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::DRAFT => [self::AWAITING_PICKUP, self::CANCELLED],
            self::AWAITING_PICKUP => [self::COLLECTED, self::CANCELLED],
            self::COLLECTED => [self::IN_TRANSIT, self::CANCELLED],
            self::IN_TRANSIT => [self::VALIDATED, self::CANCELLED],
            self::VALIDATED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextStatuses(), true);
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
