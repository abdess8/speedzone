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

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'number_of_packages' => (int) $this->number_of_packages,
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
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->full_name,
                'email' => $this->creator->email,
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
                fn () => TransferStatusHistoryResource::collection($this->statusHistories)->resolve($request)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
