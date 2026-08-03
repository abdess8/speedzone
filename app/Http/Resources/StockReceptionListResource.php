<?php

namespace App\Http\Resources;

use App\Models\StockReception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of an inbound shipment for the list screen.
 *
 * Expects the aggregates the controller loads (`items_count`, and the three
 * `items_sum_*`), so the list does not open a query per slip.
 *
 * @mixin StockReception
 */
class StockReceptionListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->statusEnum();

        return [
            'id' => $this->id,
            'reference' => $this->reference,

            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'status_icon' => $status->icon(),

            'items_count' => (int) $this->items_count,
            'quantity_sent' => (int) $this->items_sum_quantity_sent,
            // Null rather than zero until somebody has counted: "nothing arrived"
            // and "not counted yet" are not the same fact, at either stage.
            'quantity_collected' => $this->items_sum_quantity_collected !== null
                ? (int) $this->items_sum_quantity_collected
                : null,
            'quantity_received' => $this->items_sum_quantity_received !== null
                ? (int) $this->items_sum_quantity_received
                : null,

            'pickup_city' => $this->store?->city?->name,
            'destination_city' => $this->destinationCity?->name,
            'shop' => $this->store?->name,

            'sent_at' => $this->sent_at?->toDateString(),
            'collected_at' => $this->collected_at?->toIso8601String(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'received_at' => $this->received_at?->toDateString(),

            'seller' => $this->seller?->full_name,
            'sender' => $this->sender?->full_name,
            'collector' => $this->collector?->full_name,
            'receiver' => $this->receiver?->full_name,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
