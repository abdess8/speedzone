<?php

namespace App\Enums;

enum PartnerUpdateField: string
{
    case EXTERNAL_TRACKING_CODE = 'external_tracking_code';
    case TRACKING_NUMBER = 'tracking_number';
    case PARTNER_STATUS = 'partner_status';
    case STATUS_COMMENT = 'status_comment';
    case PROOF_IMAGE = 'proof_image';
    case DELIVERED_AT = 'delivered_at';
    case IS_DELIVERED_PARTIAL = 'is_delivered_partial';

    public function label(): string
    {
        return __('partner_update_fields.'.$this->value);
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
