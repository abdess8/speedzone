<?php

namespace App\Enums;

/**
 * Shopify states SpeedZone can push when a parcel changes status.
 *
 * Fulfillment marks the order shipped. Tracking events update the customer
 * timeline after a fulfillment exists. Order actions cancel, close or reopen.
 */
enum ShopifyExportStatus: string
{
    case Fulfilled = 'fulfilled';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failure = 'failure';
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function isTrackingEvent(): bool
    {
        return in_array($this, [
            self::InTransit,
            self::OutForDelivery,
            self::Delivered,
            self::Failure,
        ], true);
    }

    public function group(): string
    {
        return match ($this) {
            self::Fulfilled => 'fulfillment',
            self::InTransit, self::OutForDelivery, self::Delivered, self::Failure => 'tracking',
            self::Open, self::Closed, self::Cancelled => 'order',
        };
    }

    public function label(): string
    {
        return __('integrations.shopify.export_statuses.'.$this->value);
    }

    /**
     * @return array<int, array{value: string, label: string, group: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'group' => $status->group(),
            ],
            self::cases()
        );
    }
}
