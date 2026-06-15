<?php

namespace App\Http\Resources;

use App\Enums\TransferStatus;
use App\Models\TransferStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransferStatusHistory
 */
class TransferStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $newStatus = $this->new_status instanceof TransferStatus
            ? $this->new_status
            : TransferStatus::from($this->new_status);

        $oldStatus = $this->old_status
            ? ($this->old_status instanceof TransferStatus ? $this->old_status : TransferStatus::from($this->old_status))
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
