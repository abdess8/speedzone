<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PickupRequestStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof OrderStatus ? $this->status : OrderStatus::from($this->status);
        $payment = $this->payment_method instanceof PaymentMethod
            ? $this->payment_method
            : PaymentMethod::resolve((string) $this->payment_method);

        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'order_number' => $this->tracking_number,
            'tracking_url' => $this->trackingUrl(),

            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),

            'customer' => [
                'first_name' => $this->customer_first_name,
                'last_name' => $this->customer_last_name,
                'full_name' => $this->customer_full_name,
                'phone' => $this->customer_phone,
                'address' => $this->customer_address,
            ],

            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'region' => $this->city->region,
            ] : null),
            'city_id' => $this->city_id,

            'sector' => $this->whenLoaded('sector', fn () => $this->sector ? [
                'id' => $this->sector->id,
                'name' => $this->sector->name,
                'delivery_price' => (float) $this->sector->delivery_price,
            ] : null),
            'sector_id' => $this->sector_id,

            'seller' => $this->whenLoaded('seller', fn () => $this->seller ? [
                'id' => $this->seller->id,
                'name' => $this->seller->full_name,
                'phone' => $this->seller->phone_number,
                'profile_photo_url' => $this->seller->profile_photo_url,
                'photo_url' => $this->seller->photo_url,
                'has_profile_photo' => $this->seller->hasProfilePhoto(),
            ] : null),
            'seller_id' => $this->seller_id,

            'pickup_request' => $this->whenLoaded('pickupRequest', function () {
                if (! $this->pickupRequest) {
                    return null;
                }

                $status = $this->pickupRequest->status instanceof PickupRequestStatus
                    ? $this->pickupRequest->status
                    : PickupRequestStatus::from($this->pickupRequest->status);

                return [
                    'id' => $this->pickupRequest->id,
                    'reference' => $this->pickupRequest->reference,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_color' => $status->color(),
                    'pickup_address' => $this->pickupRequest->pickup_address,
                    'created_at' => $this->pickupRequest->created_at?->toIso8601String(),
                    'created_by' => $this->pickupRequest->relationLoaded('createdBy') && $this->pickupRequest->createdBy
                        ? [
                            'id' => $this->pickupRequest->createdBy->id,
                            'name' => $this->pickupRequest->createdBy->full_name,
                        ]
                        : null,
                    'assigned_driver' => $this->pickupRequest->relationLoaded('assignedDriver') && $this->pickupRequest->assignedDriver
                        ? [
                            'id' => $this->pickupRequest->assignedDriver->id,
                            'name' => $this->pickupRequest->assignedDriver->full_name,
                        ]
                        : null,
                ];
            }),

            'payment_method' => $payment->value,
            'payment_method_label' => $payment->label(),
            'payment_method_display' => $payment->displayLabel(),
            'payment_method_icon' => $payment->icon(),
            'payment_method_emoji' => $payment->emoji(),
            'payment_method_color' => $payment->color(),
            'cash_collection_required' => $payment->requiresCashCollection(),

            'order_amount' => $this->order_amount !== null ? (float) $this->order_amount : null,
            'order_value' => $this->order_value !== null ? (float) $this->order_value : null,
            'amount_to_collect' => $payment->amountToCollect(
                $this->order_amount !== null ? (float) $this->order_amount : null
            ),
            'delivery_price' => (float) $this->delivery_price,
            'total_amount' => (float) $this->total_amount,

            'notes' => $this->notes,
            'is_fragile' => (bool) $this->is_fragile,
            'can_be_opened' => (bool) $this->can_be_opened,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Resolve to a plain array (no "data" wrapper) so the frontend can
            // iterate the timeline entries directly.
            'status_history' => $this->whenLoaded(
                'statusHistories',
                fn () => OrderStatusHistoryResource::collection($this->statusHistories)->resolve($request)
            ),

            'change_history' => $this->whenLoaded(
                'changeHistories',
                fn () => OrderChangeHistoryResource::collection($this->changeHistories)->resolve($request)
            ),
        ];
    }
}
