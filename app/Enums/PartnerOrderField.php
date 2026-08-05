<?php

namespace App\Enums;

enum PartnerOrderField: string
{
    case EXTERNAL_TRACKING_CODE = 'external_tracking_code';
    case STATUS = 'status';
    case CUSTOMER_NAME = 'customer_name';
    case CUSTOMER_PHONE = 'customer_phone';
    case CUSTOMER_ADDRESS = 'customer_address';
    case CITY_NAME = 'city_name';
    case SECTOR_NAME = 'sector_name';
    case ORDER_AMOUNT = 'order_amount';
    case DELIVERY_PRICE = 'delivery_price';
    case NOTES = 'notes';
    case IS_FRAGILE = 'is_fragile';
    case CAN_BE_OPENED = 'can_be_opened';
    case OPTION_EXCHANGE = 'option_exchange';

    public function label(): string
    {
        return __('partner_order_fields.'.$this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $field) => ['value' => $field->value, 'label' => $field->label()],
            self::cases()
        );
    }
}
