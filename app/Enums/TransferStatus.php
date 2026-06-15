<?php

namespace App\Enums;

enum TransferStatus: string
{
    case CREATED = 'CREATED';
    case WAITING_DISPATCH = 'WAITING_DISPATCH';
    case IN_TRANSIT = 'IN_TRANSIT';
    case RECEIVED = 'RECEIVED';
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
        return __('transfer_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'secondary',
            self::WAITING_DISPATCH => 'info',
            self::IN_TRANSIT => 'primary',
            self::RECEIVED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'ri-file-add-line',
            self::WAITING_DISPATCH => 'ri-time-line',
            self::IN_TRANSIT => 'ri-truck-line',
            self::RECEIVED => 'ri-checkbox-circle-line',
            self::CANCELLED => 'ri-close-circle-line',
        };
    }

    /**
     * Corresponding order status when the transfer reaches this state.
     */
    public function orderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::CREATED, self::WAITING_DISPATCH => OrderStatus::TRANSFER_CREATED,
            self::IN_TRANSIT => OrderStatus::IN_TRANSIT,
            self::RECEIVED => OrderStatus::RECEIVED_IN_DESTINATION,
            self::CANCELLED => null,
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::CREATED, self::WAITING_DISPATCH], true);
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
