<?php

namespace App\Enums;

/**
 * Why one item of a batch was not moved.
 *
 * Reported per item rather than aggregated: "18 traitées, 2 refusées" is only
 * actionable if the operator can see that one parcel had already been delivered
 * by a colleague and the other left his perimeter.
 */
enum BulkStatusFailureReason: string
{
    case NOT_FOUND = 'NOT_FOUND';
    case INACCESSIBLE = 'INACCESSIBLE';
    case PERMISSION_DENIED = 'PERMISSION_DENIED';
    case TRANSITION_NOT_ALLOWED = 'TRANSITION_NOT_ALLOWED';
    case STATUS_CHANGED = 'STATUS_CHANGED';
    case BUSINESS_RULE = 'BUSINESS_RULE';

    public function label(): string
    {
        return __('bulk_status.failures.'.$this->value);
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
