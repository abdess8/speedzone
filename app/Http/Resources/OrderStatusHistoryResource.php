<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PickupRequestStatus;
use App\Enums\TransferStatus;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderStatusHistory
 */
class OrderStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof OrderStatus ? $this->status : OrderStatus::from($this->status);

        return [
            'id' => $this->id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'status_icon' => $status->icon(),
            'comment' => $this->comment,
            'is_system' => (bool) $this->is_system,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->when(
                ! $this->is_system && $this->relationLoaded('user') && $this->user,
                fn () => UserSummaryResource::make($this->user)->resolve($request)
            ),
            'pickup_request' => $this->whenLoaded('pickupRequest', function () {
                if (! $this->pickupRequest) {
                    return null;
                }

                $pickupStatus = $this->pickupRequest->status instanceof PickupRequestStatus
                    ? $this->pickupRequest->status
                    : PickupRequestStatus::from($this->pickupRequest->status);

                return [
                    'id' => $this->pickupRequest->id,
                    'reference' => $this->pickupRequest->reference,
                    'status_label' => $pickupStatus->label(),
                    'status_color' => $pickupStatus->color(),
                ];
            }),
            'transfer' => $this->whenLoaded('transfer', function () {
                if (! $this->transfer) {
                    return null;
                }

                $transferStatus = $this->transfer->status instanceof TransferStatus
                    ? $this->transfer->status
                    : TransferStatus::from($this->transfer->status);

                return [
                    'id' => $this->transfer->id,
                    'reference' => $this->transfer->reference,
                    'status_label' => $transferStatus->label(),
                    'status_color' => $transferStatus->color(),
                ];
            }),
        ];
    }
}
