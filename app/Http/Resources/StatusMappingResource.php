<?php

namespace App\Http\Resources;

use App\Models\StatusMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StatusMapping
 */
class StatusMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'speedzone_status' => $this->speedzone_status?->value,
            'speedzone_status_label' => $this->speedzone_status?->label(),
            'partner_status' => $this->partner_status,
        ];
    }
}
