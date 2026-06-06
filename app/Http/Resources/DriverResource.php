<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Represents a driver together with the delivery sectors they are assigned to.
 *
 * @mixin User
 */
class DriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone_number,
            'sectors_count' => $this->whenCounted('sectors'),
            'sectors' => SectorResource::collection($this->whenLoaded('sectors')),
        ];
    }
}
