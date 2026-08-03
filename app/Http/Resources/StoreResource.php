<?php

namespace App\Http\Resources;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Store
 */
class StoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'category' => $this->category,
            'website' => $this->website,
            'logo_url' => $this->logo_url,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'city_id' => $this->city_id,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $this->city->name,
            ]),
            'stock_hub_city_id' => $this->stock_hub_city_id,
            'stock_hub_city' => $this->whenLoaded('stockHubCity', fn () => $this->stockHubCity ? [
                'id' => $this->stockHubCity->id,
                'name' => $this->stockHubCity->name,
            ] : null),
            'address' => $this->address,
            'pickup_address_1' => $this->pickup_address_1,
            'pickup_address_2' => $this->pickup_address_2,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'orders_count' => $this->whenCounted('orders'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
