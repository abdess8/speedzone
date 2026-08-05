<?php

namespace App\Http\Resources;

use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderReturn
 */
class OrderReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof ReturnStatus ? $this->status : ReturnStatus::from($this->status);
        $role = $this->initiated_by_role instanceof ReturnInitiatedByRole
            ? $this->initiated_by_role
            : ReturnInitiatedByRole::from($this->initiated_by_role);
        $reason = ReturnReason::tryFrom($this->reason);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'order_id' => $this->order_id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'initiated_by_role' => $role->value,
            'initiated_by_role_label' => $role->label(),
            'reason' => $this->reason,
            'reason_label' => $reason?->label() ?? $this->reason,
            'return_notes' => $this->return_notes,
            'return_address' => $this->return_address,
            'current_location_city_id' => $this->current_location_city_id,
            'updated_customer_name' => $this->updated_customer_name,
            'updated_customer_phone' => $this->updated_customer_phone,
            'updated_address' => $this->updated_address,
            'updated_city_id' => $this->updated_city_id,
            'assigned_to' => $this->assigned_to,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'assigned_driver' => $this->whenLoaded('assignedDriver', fn () => $this->assignedDriver
                ? UserSummaryResource::make($this->assignedDriver)->resolve($request)
                : null),
            'hand_back_city_id' => $this->handBackCityId(),
            'is_at_vendor_city' => $this->isAtVendorCity(),
            'can_edit_customer_data' => $this->canEditCustomerData(),
            'effective_customer_name' => $this->effectiveCustomerName(),
            'effective_customer_phone' => $this->effectiveCustomerPhone(),
            'effective_address' => $this->effectiveAddress(),
            'effective_city_id' => $this->effectiveCityId(),
            'scan_url' => $this->scanUrl(),
            'order' => $this->whenLoaded('order', fn () => $this->order
                ? OrderResource::make($this->order)->resolve($request)
                : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator
                ? UserSummaryResource::make($this->creator)->resolve($request)
                : null),
            'current_location_city' => $this->whenLoaded('currentLocationCity', fn () => $this->currentLocationCity ? [
                'id' => $this->currentLocationCity->id,
                'name' => $this->currentLocationCity->name,
                'code' => $this->currentLocationCity->code,
            ] : null),
            'updated_city' => $this->whenLoaded('updatedCity', fn () => $this->updatedCity ? [
                'id' => $this->updatedCity->id,
                'name' => $this->updatedCity->name,
                'code' => $this->updatedCity->code,
            ] : null),
            'status_history' => $this->whenLoaded(
                'statusHistories',
                fn () => ReturnStatusHistoryResource::collection($this->statusHistories)->resolve($request)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
