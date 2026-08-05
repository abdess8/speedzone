<?php

namespace App\Http\Resources;

use App\Models\FieldMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FieldMapping
 */
class FieldMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'speedzone_field' => $this->speedzone_field?->value ?? $this->speedzone_field,
            'speedzone_field_label' => $this->speedzone_field?->label(),
            'partner_field' => $this->partner_field,
        ];
    }
}
