<?php

namespace App\Enums;

/**
 * YouCan states SpeedZone can push when a parcel changes status.
 *
 * YouCan keeps three independent slugs: general order, shipping, payment.
 * The Store Admin API uses a different PUT path for each group.
 */
enum YouCanExportStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Processed = 'processed';
    case CanceledBySeller = 'canceled-by-seller';
    case Unfulfilled = 'unfulfilled';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Fulfilled = 'fulfilled';
    case ShippingCanceled = 'canceled';
    case Pending = 'pending';
    case Paid = 'paid';
    case Captured = 'captured';
    case Unpaid = 'unpaid';
    case Refunded = 'refunded';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function group(): string
    {
        return match ($this) {
            self::Open, self::Closed, self::Processed, self::CanceledBySeller => 'order',
            self::Unfulfilled, self::Processing, self::Shipped, self::Fulfilled, self::ShippingCanceled => 'shipping',
            self::Pending, self::Paid, self::Captured, self::Unpaid, self::Refunded => 'payment',
        };
    }

    public function label(): string
    {
        return __('integrations.youcan.export_statuses.'.$this->value);
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
