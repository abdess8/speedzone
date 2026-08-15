<?php

namespace App\Enums;

use App\Services\OrderTransitionService;
use App\Services\ReturnTransitionService;

/**
 * The two things a bulk status edit can act on.
 *
 * Everything the feature needs to stay generic hangs off this enum: which
 * status vocabulary applies, which transition graph governs it, which
 * permission resource its grants are filed under, and which targets are off
 * limits to a batch. A third entity would only have to answer these questions.
 */
enum BulkStatusEntityType: string
{
    case ORDER = 'ORDER';
    case RETURN = 'RETURN';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    /**
     * Permission resource these grants are filed under, so the Roles screen
     * groups them next to the rest of the module.
     */
    public function resource(): string
    {
        return match ($this) {
            self::ORDER => 'orders',
            self::RETURN => 'returns',
        };
    }

    public function label(): string
    {
        return __('bulk_status.entities.'.$this->value);
    }

    /**
     * The transition graph that governs this entity, keyed by source status.
     *
     * @return array<string, array<int, string>>
     */
    public function transitionMap(): array
    {
        return match ($this) {
            self::ORDER => OrderTransitionService::transitionMap(),
            self::RETURN => ReturnTransitionService::transitionMap(),
        };
    }

    /**
     * Statuses are resolved leniently on purpose: a grant left in the database
     * for a status that has since left the enum must render as an oddity on the
     * admin matrix, not take the whole screen down with it.
     */
    public function statusLabel(string $status): string
    {
        return $this->status($status)?->label() ?? $status;
    }

    public function statusColor(string $status): string
    {
        return $this->status($status)?->color() ?? 'secondary';
    }

    public function statusIcon(string $status): string
    {
        return $this->status($status)?->icon() ?? 'ri-question-line';
    }

    private function status(string $status): OrderStatus|ReturnStatus|null
    {
        return match ($this) {
            self::ORDER => OrderStatus::tryFrom($status),
            self::RETURN => ReturnStatus::tryFrom($status),
        };
    }

    /**
     * Descriptor consumed by both the wizard and the admin matrix.
     *
     * @return array{value: string, label: string, color: string, icon: string}
     */
    public function statusDescriptor(string $status): array
    {
        return [
            'value' => $status,
            'label' => $this->statusLabel($status),
            'color' => $this->statusColor($status),
            'icon' => $this->statusIcon($status),
        ];
    }

    /**
     * Statuses a batch may never target, whatever the graph and the grants say.
     *
     * Three reasons put a status here, and none of them is "risky":
     *
     * - it is stamped as a side effect and can never be chosen (the returns
     *   leg of the order lifecycle, and the retired FAILED);
     * - landing on it demands a per-parcel failure reason, which a single form
     *   applied to two hundred rows cannot honestly supply;
     * - it demands a per-parcel decision the batch has no field for — naming
     *   the driver who carries a return back to the vendor, which is what the
     *   hand-back screen exists to do.
     *
     * @return array<int, string>
     */
    public function excludedTargets(): array
    {
        return match ($this) {
            self::ORDER => array_values(array_unique(array_merge(
                array_map(
                    static fn (OrderStatus $status) => $status->value,
                    array_filter(
                        OrderStatus::cases(),
                        static fn (OrderStatus $status) => $status->transitionPermission() === null
                    )
                ),
                array_map(
                    static fn (OrderStatus $status) => $status->value,
                    array_filter(
                        OrderStatus::cases(),
                        static fn (OrderStatus $status) => $status->carriesFailureReason()
                    )
                ),
            ))),
            self::RETURN => [
                ReturnStatus::CREATED->value,
                ReturnStatus::IN_DELIVERY_TO_VENDOR->value,
            ],
        };
    }

    /**
     * Every transition a batch could ever apply, before any permission check.
     *
     * @return array<int, array{from: string, to: string}>
     */
    public function transitionPairs(): array
    {
        $excluded = $this->excludedTargets();
        $pairs = [];

        foreach ($this->transitionMap() as $from => $targets) {
            foreach ($targets as $to) {
                if (in_array($to, $excluded, true)) {
                    continue;
                }

                $pairs[] = ['from' => $from, 'to' => $to];
            }
        }

        return $pairs;
    }
}
