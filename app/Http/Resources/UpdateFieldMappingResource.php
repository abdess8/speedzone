<?php

namespace App\Http\Resources;

use App\Models\UpdateFieldMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UpdateFieldMapping
 */
class UpdateFieldMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'speedzone_field' => $this->speedzone_field?->value ?? $this->speedzone_field,
            'partner_field' => $this->partner_field,
        ];
    }
}
