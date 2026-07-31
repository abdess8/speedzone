<?php

namespace App\Enums;

/**
 * Why a delivery attempt ended in OrderStatus::FAILED.
 *
 * Captured from the driver at the moment he marks the order as not delivered,
 * and mapped to a ReturnReason when a return is opened for the same order.
 */
enum OrderFailureReason: string
{
    case CUSTOMER_REFUSED = 'CUSTOMER_REFUSED';
    case CUSTOMER_UNREACHABLE = 'CUSTOMER_UNREACHABLE';
    case CUSTOMER_CANCELED = 'CUSTOMER_CANCELED';
    case WRONG_ADDRESS = 'WRONG_ADDRESS';
    case CUSTOMER_ABSENT = 'CUSTOMER_ABSENT';
    case POSTPONED = 'POSTPONED';
    case OTHER = 'OTHER';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $reason) => $reason->value, self::cases());
    }

    public function label(): string
    {
        return __('order_failure_reasons.'.$this->value);
    }

    /**
     * Bootstrap contextual colour used by the driver bottom sheet chips.
     */
    public function color(): string
    {
        return match ($this) {
            self::CUSTOMER_REFUSED, self::CUSTOMER_CANCELED => 'danger',
            self::CUSTOMER_UNREACHABLE, self::CUSTOMER_ABSENT => 'warning',
            self::WRONG_ADDRESS => 'info',
            self::POSTPONED => 'primary',
            self::OTHER => 'secondary',
        };
    }

    /**
     * Remix icon shown next to the reason in the driver UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CUSTOMER_REFUSED => 'ri-close-circle-line',
            self::CUSTOMER_UNREACHABLE => 'ri-phone-lock-line',
            self::CUSTOMER_CANCELED => 'ri-stop-circle-line',
            self::WRONG_ADDRESS => 'ri-map-pin-off-line',
            self::CUSTOMER_ABSENT => 'ri-user-unfollow-line',
            self::POSTPONED => 'ri-calendar-event-line',
            self::OTHER => 'ri-more-2-line',
        };
    }

    /**
     * Reason carried over when a return is opened for the failed order.
     */
    public function toReturnReason(): ReturnReason
    {
        return match ($this) {
            self::CUSTOMER_REFUSED, self::CUSTOMER_CANCELED => ReturnReason::CUSTOMER_REFUSED,
            self::CUSTOMER_UNREACHABLE, self::CUSTOMER_ABSENT => ReturnReason::CUSTOMER_UNREACHABLE,
            self::WRONG_ADDRESS, self::POSTPONED, self::OTHER => ReturnReason::DELIVERY_FAILED,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
                'color' => $reason->color(),
                'icon' => $reason->icon(),
            ],
            self::cases()
        );
    }
}
