<?php

namespace App\Http\Resources;

use App\Support\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : json_decode($this->data, true) ?? [];
        $display = NotificationPresenter::display($data);

        return [
            'id' => $this->id,
            'type' => $display['type'],
            'title' => $display['title'],
            'message' => $display['message'],
            'reference' => $data['reference'] ?? null,
            'url' => $data['url'] ?? null,
            'data' => $data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'is_read' => $this->read_at !== null,
        ];
    }
}
