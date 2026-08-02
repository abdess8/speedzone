<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case CREATED = 'CREATED';
    case IN_TRANSIT_TO_DEPOT = 'IN_TRANSIT_TO_DEPOT';
    case RECEIVED_AT_DEPOT = 'RECEIVED_AT_DEPOT';
    case IN_TRANSIT_TO_SELLER = 'IN_TRANSIT_TO_SELLER';
    case DELIVERED_TO_SELLER = 'DELIVERED_TO_SELLER';
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
        return __('return_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'secondary',
            self::IN_TRANSIT_TO_DEPOT => 'info',
            self::RECEIVED_AT_DEPOT => 'primary',
            self::IN_TRANSIT_TO_SELLER => 'warning',
            self::DELIVERED_TO_SELLER => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'ri-file-add-line',
            self::IN_TRANSIT_TO_DEPOT => 'ri-truck-line',
            self::RECEIVED_AT_DEPOT => 'ri-building-line',
            self::IN_TRANSIT_TO_SELLER => 'ri-arrow-go-back-line',
            self::DELIVERED_TO_SELLER => 'ri-checkbox-circle-line',
            self::CANCELLED => 'ri-close-circle-line',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::DELIVERED_TO_SELLER, self::CANCELLED], true);
    }

    public function allowsCustomerDataEdit(): bool
    {
        return in_array($this, [self::CREATED, self::IN_TRANSIT_TO_SELLER], true);
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
