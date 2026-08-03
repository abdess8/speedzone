<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A picking slip: what the agent needs in hand to pack one box.
 *
 * Lines are always included, unlike the general order list — the whole point of
 * the queue is to read them off the shelf.
 *
 * @mixin Order
 */
class PreparationOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $this->customer_full_name,
            'customer_phone' => $this->customer_phone,
            'city' => $this->city?->name,
            'city_id' => $this->city_id,
            'sector' => $this->sector?->name,
            'hub_city' => $this->stockHubCity?->name,
            'hub_city_id' => $this->stock_hub_city_id,
            // Tells the agent, before packing, whether the box leaves on a local
            // round or waits for an inter-city transfer.
            'same_city' => $this->stock_hub_city_id !== null
                && (int) $this->stock_hub_city_id === (int) $this->city_id,
            'store' => $this->store?->name,
            'seller' => $this->seller?->full_name,
            'total_amount' => (float) $this->total_amount,
            'units' => (int) $this->items->sum('quantity'),
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'quantity' => (int) $item->quantity,
            ])->all(),
        ];
    }
}
