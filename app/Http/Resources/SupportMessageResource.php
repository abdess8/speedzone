<?php

namespace App\Http\Resources;

use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportMessage
 */
class SupportMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'attachment_url' => $this->attachment ? '/storage/'.ltrim($this->attachment, '/') : null,
            'attachment_name' => $this->attachment_name,
            'sender' => $this->whenLoaded('sender', fn () => $this->sender
                ? UserSummaryResource::make($this->sender)->resolve($request)
                : null),
            'sender_id' => $this->sender_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
