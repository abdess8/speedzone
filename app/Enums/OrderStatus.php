<?php

namespace App\Enums;

use App\Services\OrderTransitionService;

enum OrderStatus: string
{
    case CREATED = 'CREATED';
    // The stock entry point: an order picked from the vendor's shelves in our
    // depot never goes through a pickup, so it starts here instead of CREATED.
    case AWAITING_PREPARATION = 'AWAITING_PREPARATION';
    case PREPARED = 'PREPARED';
    case PICKUP_REQUESTED = 'PICKUP_REQUESTED';
    case WAITING_PICKUP = 'WAITING_PICKUP';
    case PICKED_UP = 'PICKED_UP';
    case IN_DEPOT = 'IN_DEPOT';
    case TRANSFER_CREATED = 'TRANSFER_CREATED';
    case IN_TRANSIT = 'IN_TRANSIT';
    case RECEIVED_IN_DESTINATION = 'RECEIVED_IN_DESTINATION';
    case IN_DELIVERY_CITY = 'IN_DELIVERY_CITY';
    case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    case DELIVERED = 'DELIVERED';
    // Legacy terminal failure. Nothing enters it any more: a delivery that ends
    // for good now lands on READY_TO_RETURN, and a delivery that may still be
    // retried stays on OUT_FOR_DELIVERY with an incremented attempt counter.
    case FAILED = 'FAILED';
    case REJECTED = 'REJECTED';
    case CANCELED = 'CANCELED';
    // The parcel is off the round and waiting for the reverse leg to be opened.
    // It is the hand-off point between delivery and the returns module, which
    // stamps RETURN_REQUESTED once an actual return record exists.
    case READY_TO_RETURN = 'READY_TO_RETURN';
    case RETURN_REQUESTED = 'RETURN_REQUESTED';
    case RETURN_IN_PROGRESS = 'RETURN_IN_PROGRESS';
    case RETURNED = 'RETURNED';

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
        return __('order_statuses.'.$this->value);
    }

    /**
     * Bootstrap contextual colour used by badges / the timeline.
     */
    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'secondary',
            self::AWAITING_PREPARATION, self::PICKUP_REQUESTED, self::WAITING_PICKUP => 'info',
            self::PREPARED, self::PICKED_UP, self::IN_DEPOT, self::TRANSFER_CREATED, self::IN_TRANSIT, self::RECEIVED_IN_DESTINATION, self::IN_DELIVERY_CITY => 'primary',
            self::OUT_FOR_DELIVERY => 'warning',
            self::DELIVERED => 'success',
            self::FAILED, self::REJECTED => 'danger',
            self::CANCELED => 'secondary',
            self::READY_TO_RETURN, self::RETURN_REQUESTED => 'warning',
            self::RETURN_IN_PROGRESS => 'info',
            self::RETURNED => 'dark',
        };
    }

    /**
     * Icon (Remix Icon) used in the tracking timeline.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'ri-file-add-line',
            self::AWAITING_PREPARATION => 'ri-inbox-line',
            self::PREPARED => 'ri-box-3-line',
            self::PICKUP_REQUESTED => 'ri-hand-heart-line',
            self::WAITING_PICKUP => 'ri-time-line',
            self::PICKED_UP => 'ri-hand-coin-line',
            self::IN_DEPOT => 'ri-building-line',
            self::TRANSFER_CREATED => 'ri-archive-line',
            self::IN_TRANSIT => 'ri-truck-line',
            self::RECEIVED_IN_DESTINATION => 'ri-map-pin-user-line',
            self::IN_DELIVERY_CITY => 'ri-map-pin-line',
            self::OUT_FOR_DELIVERY => 'ri-e-bike-2-line',
            self::DELIVERED => 'ri-checkbox-circle-line',
            self::FAILED => 'ri-close-circle-line',
            self::REJECTED => 'ri-forbid-line',
            self::CANCELED => 'ri-stop-circle-line',
            self::READY_TO_RETURN => 'ri-inbox-unarchive-line',
            self::RETURN_REQUESTED => 'ri-arrow-go-back-line',
            self::RETURN_IN_PROGRESS => 'ri-truck-line',
            self::RETURNED => 'ri-arrow-go-back-line',
        };
    }

    /**
     * What the step means on the ground, for the Help Center.
     */
    public function description(): string
    {
        return __('order_statuses.descriptions.'.$this->value);
    }

    /**
     * Human name of the role expected to stamp this status.
     */
    public function actorLabel(): string
    {
        return __('order_statuses.actors.'.$this->value);
    }

    /**
     * Permission that authorises moving an order *into* this status.
     *
     * Read by {@see OrderTransitionService} rather than mirrored there, so the
     * catalog and the runtime check can never disagree.
     *
     * Null means the status is never *chosen*: the return statuses are set by
     * the returns module as a side effect, awaiting-preparation is stamped at
     * creation on any order picked from stock, and FAILED is retired — the
     * delivery outcome flow routes a dead delivery to READY_TO_RETURN instead.
     */
    public function transitionPermission(): ?string
    {
        return match ($this) {
            self::RETURN_REQUESTED, self::RETURN_IN_PROGRESS, self::AWAITING_PREPARATION, self::FAILED => null,
            default => 'orders.transition.to_'.strtolower($this->value),
        };
    }

    /**
     * Whether landing on this status must be justified by an OrderFailureReason.
     */
    public function carriesFailureReason(): bool
    {
        return match ($this) {
            self::FAILED, self::READY_TO_RETURN => true,
            default => false,
        };
    }

    /**
     * The straight line from a placed order to a paid seller.
     *
     * @return array<int, self>
     */
    public static function successPath(): array
    {
        return [
            self::CREATED,
            self::PICKED_UP,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
        ];
    }

    /**
     * The same line up to the point the delivery fails for good, where the
     * reverse logistics workflow takes over.
     *
     * @return array<int, self>
     */
    public static function failurePath(): array
    {
        return [
            self::CREATED,
            self::PICKED_UP,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::READY_TO_RETURN,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function matrix(): array
    {
        return array_map(
            static fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->color(),
                'icon' => $status->icon(),
                'actor' => $status->actorLabel(),
                'permissions' => array_filter([$status->transitionPermission()]),
            ],
            self::cases()
        );
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ],
            self::cases()
        );
    }
}
