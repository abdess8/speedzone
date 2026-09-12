<?php

namespace App\Enums;

enum EcommerceSyncTrigger: string
{
    case Manual = 'manual';
    case Schedule = 'schedule';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $trigger) => $trigger->value, self::cases());
    }

    public function label(): string
    {
        return __('integrations.sync.trigger.'.$this->value);
    }
}
