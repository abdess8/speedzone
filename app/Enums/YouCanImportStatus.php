<?php

namespace App\Enums;

/**
 * YouCan slugs a seller can pick as the import trigger.
 *
 * Order slugs come from status_new; payment slugs from paymentStatus /
 * payment.status. "Paid" is a payment state, not an order state, and on COD
 * it is often still unpaid when the parcel should already leave.
 */
enum YouCanImportStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Processed = 'processed';
    case CanceledBySeller = 'canceled-by-seller';
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Captured = 'captured';
    case Refunded = 'refunded';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function isPaymentStatus(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Unpaid,
            self::Pending,
            self::Captured,
            self::Refunded,
        ], true);
    }

    public function group(): string
    {
        return $this->isPaymentStatus() ? 'payment' : 'order';
    }

    public function label(): string
    {
        return __('integrations.youcan.import_statuses.'.$this->value);
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
