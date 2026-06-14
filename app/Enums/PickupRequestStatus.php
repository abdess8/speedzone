<?php

namespace App\Enums;

enum PickupRequestStatus: string
{
    case WAITING_FOR_PICKUP = 'WAITING_FOR_PICKUP';
    case PICKED_UP = 'PICKED_UP';
    case IN_DEPOT = 'IN_DEPOT';
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
        return match ($this) {
            self::WAITING_FOR_PICKUP => 'Waiting for Pickup',
            self::PICKED_UP => 'Picked Up',
            self::IN_DEPOT => 'In Depot',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WAITING_FOR_PICKUP => 'info',
            self::PICKED_UP => 'primary',
            self::IN_DEPOT => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::WAITING_FOR_PICKUP => 'ri-time-line',
            self::PICKED_UP => 'ri-hand-coin-line',
            self::IN_DEPOT => 'ri-building-line',
            self::CANCELLED => 'ri-close-circle-line',
        };
    }

    /**
     * Map pickup status to the corresponding order lifecycle status.
     */
    public function orderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::WAITING_FOR_PICKUP => OrderStatus::WAITING_PICKUP,
            self::PICKED_UP => OrderStatus::PICKED_UP,
            self::IN_DEPOT => OrderStatus::IN_DEPOT,
            self::CANCELLED => null,
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
