<?php

namespace App\Enums;

enum OrderStatus: string
{
    case CREATED = 'CREATED';
    case PICKUP_REQUESTED = 'PICKUP_REQUESTED';
    case WAITING_PICKUP = 'WAITING_PICKUP';
    case PICKED_UP = 'PICKED_UP';
    case IN_DEPOT = 'IN_DEPOT';
    case IN_TRANSIT = 'IN_TRANSIT';
    case IN_DELIVERY_CITY = 'IN_DELIVERY_CITY';
    case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';
    case RETURNED = 'RETURNED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }
}
