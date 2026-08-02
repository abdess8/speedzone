<?php

namespace App\Http\Resources;

use App\Enums\ReturnStatus;
use App\Models\ReturnStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReturnStatusHistory
 */
class ReturnStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $newStatus = $this->new_status instanceof ReturnStatus
            ? $this->new_status
            : ReturnStatus::from($this->new_status);

        $oldStatus = $this->old_status
            ? ($this->old_status instanceof ReturnStatus ? $this->old_status : ReturnStatus::from($this->old_status))
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
            'user' => $this->whenLoaded('changedBy', fn () => $this->changedBy
                ? UserSummaryResource::make($this->changedBy)->resolve($request)
                : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
