<?php

namespace App\Enums;

enum EcommerceIntegrationStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Error = 'error';
    case Disconnected = 'disconnected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }
}
