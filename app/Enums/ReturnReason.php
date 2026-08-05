<?php

namespace App\Enums;

enum ReturnReason: string
{
    case CUSTOMER_REFUSED = 'CUSTOMER_REFUSED';
    case CUSTOMER_UNREACHABLE = 'CUSTOMER_UNREACHABLE';
    case DELIVERY_FAILED = 'DELIVERY_FAILED';
    case CUSTOMER_REQUESTED_RETURN = 'CUSTOMER_REQUESTED_RETURN';
    case SELLER_REQUESTED = 'SELLER_REQUESTED';
    case ADMIN_DECISION = 'ADMIN_DECISION';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $reason) => $reason->value, self::cases());
    }

    public function label(): string
    {
        return __('return_reasons.'.$this->value);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            self::cases()
        );
    }
}
