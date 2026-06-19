<?php

namespace App\Enums;

enum OrderStatus: string
{
    case CREATED = 'CREATED';
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
    case FAILED = 'FAILED';
    case REJECTED = 'REJECTED';
    case CANCELED = 'CANCELED';
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
            self::PICKUP_REQUESTED, self::WAITING_PICKUP => 'info',
            self::PICKED_UP, self::IN_DEPOT, self::TRANSFER_CREATED, self::IN_TRANSIT, self::RECEIVED_IN_DESTINATION, self::IN_DELIVERY_CITY => 'primary',
            self::OUT_FOR_DELIVERY => 'warning',
            self::DELIVERED => 'success',
            self::FAILED, self::REJECTED => 'danger',
            self::CANCELED => 'secondary',
            self::RETURN_REQUESTED => 'warning',
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
            self::RETURN_REQUESTED => 'ri-arrow-go-back-line',
            self::RETURN_IN_PROGRESS => 'ri-truck-line',
            self::RETURNED => 'ri-arrow-go-back-line',
        };
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
