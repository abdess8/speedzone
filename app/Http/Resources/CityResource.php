<?php

namespace App\Http\Resources;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 */
class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'region' => $this->region,
            'is_active' => (bool) $this->is_active,
            'is_stock_hub' => (bool) $this->is_stock_hub,
            'sectors_count' => $this->whenCounted('sectors'),
            'active_sectors_count' => $this->whenCounted('activeSectors'),
            'sectors' => $this->whenLoaded(
                'sectors',
                fn () => SectorResource::collection($this->sectors)->resolve($request)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
