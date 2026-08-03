<?php

namespace App\Http\Resources;

use App\Enums\StockReceptionStatus;
use App\Models\StockReceptionStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockReceptionStatusHistory
 */
class StockReceptionStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $newStatus = $this->new_status instanceof StockReceptionStatus
            ? $this->new_status
            : StockReceptionStatus::from((string) $this->new_status);

        $oldStatus = $this->old_status instanceof StockReceptionStatus
            ? $this->old_status
            : ($this->old_status ? StockReceptionStatus::from((string) $this->old_status) : null);

        return [
            'id' => $this->id,
            'old_status' => $oldStatus?->value,
            'old_status_label' => $oldStatus?->label(),
            'new_status' => $newStatus->value,
            'new_status_label' => $newStatus->label(),
            // Aliased to what the shared timeline reads, so this journal renders
            // with the same component as the order, pickup and transfer ones.
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
