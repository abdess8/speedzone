<?php

namespace App\Http\Resources;

use App\Enums\PickupRequestStatus;
use App\Models\PickupStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PickupStatusHistory
 */
class PickupStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $newStatus = $this->new_status instanceof PickupRequestStatus
            ? $this->new_status
            : PickupRequestStatus::from($this->new_status);

        $oldStatus = $this->old_status
            ? ($this->old_status instanceof PickupRequestStatus ? $this->old_status : PickupRequestStatus::from($this->old_status))
            : null;

        return [
            'id' => $this->id,
            'old_status' => $oldStatus?->value,
            'old_status_label' => $oldStatus?->label(),
            'new_status' => $newStatus->value,
            'new_status_label' => $newStatus->label(),
            'status_label' => $newStatus->label(),
            'status_color' => $newStatus->color(),
            'status_icon' => $newStatus->icon(),
            'comment' => $this->comment,
            'user' => $this->whenLoaded('changedBy', fn () => $this->changedBy ? [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->full_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
