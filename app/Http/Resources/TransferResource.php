<?php

namespace App\Http\Resources;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof TransferStatus ? $this->status : TransferStatus::from($this->status);
        $contentType = $this->contentType();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'content_type' => $contentType->value,
            'content_type_label' => $contentType->label(),
            'content_type_color' => $contentType->color(),
            'content_type_icon' => $contentType->icon(),
            'number_of_packages' => (int) $this->number_of_packages,
            'number_of_returns' => (int) $this->number_of_returns,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'from_city_id' => $this->from_city_id,
            'to_city_id' => $this->to_city_id,
            'created_by' => $this->created_by,
            'assigned_to' => $this->assigned_to,
            'scan_url' => $this->scanUrl(),
            'from_city' => $this->whenLoaded('fromCity', fn () => $this->fromCity ? [
                'id' => $this->fromCity->id,
                'name' => $this->fromCity->name,
                'code' => $this->fromCity->code,
            ] : null),
            'to_city' => $this->whenLoaded('toCity', fn () => $this->toCity ? [
                'id' => $this->toCity->id,
                'name' => $this->toCity->name,
                'code' => $this->toCity->code,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator
                ? UserSummaryResource::make($this->creator)->resolve($request)
                : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee
                ? UserSummaryResource::make($this->assignee)->resolve($request)
                : null),
            'orders_count' => $this->whenCounted('orders'),
            'orders' => $this->whenLoaded(
                'orders',
                fn () => OrderResource::collection($this->orders)->resolve($request)
            ),
            'returns_count' => $this->whenCounted('returns'),
            'returns' => $this->whenLoaded(
                'returns',
                fn () => OrderReturnResource::collection($this->returns)->resolve($request)
            ),
            'status_history' => $this->whenLoaded(
                'statusHistories',
                fn () => TransferStatusHistoryResource::collection($this->statusHistories)->resolve($request)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
