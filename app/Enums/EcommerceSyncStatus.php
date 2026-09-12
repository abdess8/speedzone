<?php

namespace App\Enums;

enum EcommerceSyncStatus: string
{
    case Running = 'running';
    case Review = 'review';
    case Succeeded = 'succeeded';
    case Partial = 'partial';
    case Failed = 'failed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function label(): string
    {
        return __('integrations.sync.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Review => 'primary',
            self::Succeeded => 'success',
            self::Partial => 'warning',
            self::Failed => 'danger',
        };
    }
}
