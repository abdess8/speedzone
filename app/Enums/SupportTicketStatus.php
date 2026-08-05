<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case WAITING_SELLER = 'WAITING_SELLER';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function label(): string
    {
        return __('support_ticket_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'primary',
            self::IN_PROGRESS => 'info',
            self::WAITING_SELLER => 'warning',
            self::RESOLVED => 'success',
            self::CLOSED => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OPEN => 'ri-inbox-line',
            self::IN_PROGRESS => 'ri-loader-2-line',
            self::WAITING_SELLER => 'ri-time-line',
            self::RESOLVED => 'ri-checkbox-circle-line',
            self::CLOSED => 'ri-lock-line',
        };
    }

    /**
     * A closed ticket is read-only: no replies, no status changes.
     */
    public function isClosed(): bool
    {
        return $this === self::CLOSED;
    }

    /**
     * Statuses an agent may transition a ticket to from the current one.
     *
     * @return array<int, self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::OPEN => [self::IN_PROGRESS, self::WAITING_SELLER, self::RESOLVED, self::CLOSED],
            self::IN_PROGRESS => [self::WAITING_SELLER, self::RESOLVED, self::CLOSED],
            self::WAITING_SELLER => [self::IN_PROGRESS, self::RESOLVED, self::CLOSED],
            self::RESOLVED => [self::IN_PROGRESS, self::CLOSED],
            self::CLOSED => [],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
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
