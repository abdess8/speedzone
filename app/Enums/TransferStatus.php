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

    /**
     * Corresponding status for the returns riding this manifest.
     *
     * A return only moves on the two legs that change its physical location;
     * while the manifest is still being filled it stays parked at the hub,
     * which is why CREATED and WAITING_DISPATCH map to nothing.
     */
    public function returnStatus(): ?ReturnStatus
    {
        return match ($this) {
            self::IN_TRANSIT => ReturnStatus::IN_TRANSIT_TO_DEPOT,
            self::RECEIVED => ReturnStatus::ARRIVED_VENDOR_HUB,
            self::CREATED, self::WAITING_DISPATCH, self::CANCELLED => null,
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::CREATED, self::WAITING_DISPATCH], true);
    }

    public function canAssignStaff(): bool
    {
        return in_array($this, [self::CREATED, self::WAITING_DISPATCH, self::IN_TRANSIT], true);
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
