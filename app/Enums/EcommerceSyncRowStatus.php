<?php

namespace App\Enums;

enum EcommerceSyncRowStatus: string
{
    case Pending = 'pending';
    case Imported = 'imported';
    case Failed = 'failed';
    case Skipped = 'skipped';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }
}
