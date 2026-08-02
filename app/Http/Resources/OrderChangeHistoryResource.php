<?php

namespace App\Http\Resources;

use App\Models\OrderChangeHistory;
use App\Services\OrderAuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderChangeHistory
 */
class OrderChangeHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_name' => $this->field_name,
            'field_label' => OrderAuditService::fieldLabel($this->field_name),
            'is_automatic' => OrderAuditService::isAutomaticChange($this->field_name),
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'created_at' => $this->created_at?->toIso8601String(),
            'changed_by' => $this->whenLoaded('changedByUser', fn () => $this->changedByUser
                ? UserSummaryResource::make($this->changedByUser)->resolve($request)
                : null),
        ];
    }
}
