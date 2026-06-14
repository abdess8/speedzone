<?php

namespace App\Http\Resources;

use App\Enums\PickupRequestStatus;
use App\Models\PickupRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PickupRequest
 */
class PickupRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof PickupRequestStatus ? $this->status : PickupRequestStatus::from($this->status);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'pickup_address' => $this->pickup_address,
            'number_of_packages' => (int) $this->number_of_packages,
            'total_orders_amount' => (float) $this->total_orders_amount,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'assigned_to' => $this->assigned_to,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->full_name,
                'email' => $this->creator->email,
                'phone' => $this->creator->phone_number,
            ] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->full_name,
                'phone' => $this->assignee->phone_number,
            ] : null),
            'orders_count' => $this->whenCounted('orders'),
            'orders' => $this->whenLoaded(
                'orders',
                fn () => OrderResource::collection($this->orders)->resolve($request)
            ),
            'status_history' => $this->whenLoaded(
                'statusHistories',
                fn () => PickupStatusHistoryResource::collection($this->statusHistories)->resolve($request)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
