<?php

namespace App\Enums;

/**
 * Shopify filters a seller can pick as the import trigger.
 *
 * Fulfillment slugs go on `fulfillment_status` / the order's own
 * `fulfillment_status`; financial slugs on `financial_status`. Unfulfilled
 * is the default: those are the parcels that still need to leave.
 */
enum ShopifyImportStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Partial = 'partial';
    case Fulfilled = 'fulfilled';
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Paid = 'paid';
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Unpaid = 'unpaid';
    case Refunded = 'refunded';
    case Voided = 'voided';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function isFinancialStatus(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Pending,
            self::Authorized,
            self::Unpaid,
            self::Refunded,
            self::Voided,
        ], true);
    }

    public function isFulfillmentStatus(): bool
    {
        return in_array($this, [
            self::Unfulfilled,
            self::Partial,
            self::Fulfilled,
        ], true);
    }

    public function group(): string
    {
        if ($this->isFinancialStatus()) {
            return 'payment';
        }

        if ($this->isFulfillmentStatus()) {
            return 'fulfillment';
        }

        return 'order';
    }

    public function label(): string
    {
        return __('integrations.shopify.import_statuses.'.$this->value);
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
