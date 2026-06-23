<?php

namespace App\Enums;

enum SupportTicketCategory: string
{
    case DELIVERY_DELAY = 'DELIVERY_DELAY';
    case INFORMATION_REQUEST = 'INFORMATION_REQUEST';
    case CHANGE_INFORMATION = 'CHANGE_INFORMATION';
    case STATUS_CHANGE = 'STATUS_CHANGE';
    case CALCULATION_ERROR = 'CALCULATION_ERROR';
    case PAYMENT_DELAY = 'PAYMENT_DELAY';
    case INVOICE_ISSUE = 'INVOICE_ISSUE';
    case PICKUP_ISSUE = 'PICKUP_ISSUE';
    case OTHER = 'OTHER';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $category) => $category->value, self::cases());
    }

    public function label(): string
    {
        return __('support_ticket_categories.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::DELIVERY_DELAY => 'ri-truck-line',
            self::INFORMATION_REQUEST => 'ri-question-line',
            self::CHANGE_INFORMATION => 'ri-edit-2-line',
            self::STATUS_CHANGE => 'ri-exchange-line',
            self::CALCULATION_ERROR => 'ri-calculator-line',
            self::PAYMENT_DELAY => 'ri-money-dollar-circle-line',
            self::INVOICE_ISSUE => 'ri-bill-line',
            self::PICKUP_ISSUE => 'ri-archive-line',
            self::OTHER => 'ri-more-2-line',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DELIVERY_DELAY, self::PICKUP_ISSUE => 'warning',
            self::CALCULATION_ERROR, self::INVOICE_ISSUE, self::PAYMENT_DELAY => 'danger',
            self::STATUS_CHANGE, self::CHANGE_INFORMATION => 'info',
            default => 'secondary',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'icon' => $category->icon(),
                'color' => $category->color(),
            ],
            self::cases()
        );
    }
}
