<?php

namespace App\Http\Resources;

use App\Enums\SupportObjectType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportTicket
 */
class SupportTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof SupportTicketStatus
            ? $this->status
            : SupportTicketStatus::from($this->status);

        $category = $this->category instanceof SupportTicketCategory
            ? $this->category
            : SupportTicketCategory::from($this->category);

        $objectType = $this->object_type instanceof SupportObjectType
            ? $this->object_type
            : ($this->object_type ? SupportObjectType::tryFrom($this->object_type) : null);

        return [
            'id' => $this->id,
            'reference' => $this->reference,

            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'status_icon' => $status->icon(),
            'is_closed' => $status->isClosed(),
            'next_statuses' => array_map(
                static fn (SupportTicketStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $status->nextStatuses()
            ),

            'category' => $category->value,
            'category_label' => $category->label(),
            'category_icon' => $category->icon(),
            'category_color' => $category->color(),

            'object_type' => $objectType?->value,
            'object_type_label' => $objectType?->label(),
            'object_type_icon' => $objectType?->icon(),
            'object_id' => $this->object_id,
            // Set transiently by the controller (avoids N+1 on the index list).
            'object' => $this->object_summary ?? null,

            'subject' => $this->subject,
            'message' => $this->message,

            'creator' => $this->whenLoaded('creator', fn () => $this->creator
                ? UserSummaryResource::make($this->creator)->resolve($request)
                : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee
                ? UserSummaryResource::make($this->assignee)->resolve($request)
                : null),
            'assigned_to' => $this->assigned_to,

            'last_reply_at' => $this->last_reply_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->whenLoaded('closedBy', fn () => $this->closedBy
                ? UserSummaryResource::make($this->closedBy)->resolve($request)
                : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'messages_count' => $this->whenCounted('messages'),

            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'url' => '/storage/'.ltrim($a->file_path, '/'),
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values()->all()),

            'messages' => $this->whenLoaded('messages', fn () => SupportMessageResource::collection($this->messages)->resolve($request)),
        ];
    }
}
