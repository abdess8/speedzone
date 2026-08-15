<?php

namespace App\Http\Resources;

use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sector
 */
class SectorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'city_id' => $this->city_id,
            'name' => $this->name,
            'delivery_price' => (float) $this->delivery_price,
            'return_price' => (float) $this->return_price,
            'delivery_driver_price' => (float) $this->delivery_driver_price,
            'delivery_delay' => $this->delivery_delay,
            'is_active' => (bool) $this->is_active,
            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'region' => $this->city->region,
            ] : null),
            'orders_count' => $this->whenCounted('orders'),
            'drivers_count' => $this->whenCounted('drivers'),
            'drivers' => $this->whenLoaded(
                'drivers',
                fn () => UserSummaryResource::collection($this->drivers)->resolve($request)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
