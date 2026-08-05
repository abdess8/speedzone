<?php

namespace App\Http\Resources;

use App\Models\Alert;
use App\Support\AlertHtml;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'excerpt' => AlertHtml::toText($this->message),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_icon' => $this->type->icon(),
            'display_format' => $this->display_format->value,
            'format_label' => $this->display_format->label(),
            'is_dismissible' => $this->is_dismissible,
            'target_roles' => $this->target_roles ?? [],
            'target_cities' => $this->identifiers($this->target_cities),
            'target_user_ids' => $this->identifiers($this->target_user_ids),
            'end_date' => $this->end_date?->toIso8601String(),
            'is_active' => $this->is_active,
            'status' => $this->status(),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Cast a targeting list back to numbers so it compares against the ids the
     * pickers offer, while leaving the everyone marker alone: it travels in the
     * same array, and casting it would turn "all" into city 0 — an audience the
     * form cannot render and the validator rejects.
     *
     * @param  array<int, mixed>|null  $values
     * @return array<int, int|string>
     */
    private function identifiers(?array $values): array
    {
        return array_map(
            fn ($value) => $value === Alert::EVERYONE ? Alert::EVERYONE : (int) $value,
            $values ?? []
        );
    }
}
