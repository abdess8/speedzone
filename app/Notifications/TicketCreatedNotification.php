<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\SupportTicket;

class TicketCreatedNotification extends AppNotification
{
    public function __construct(public readonly SupportTicket $ticket) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::TicketCreated;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->ticket->loadMissing('creator');

        $sellerName = $this->ticket->creator?->name ?? trans('notifications.unknown_user');
        $category = $this->ticket->category?->label() ?? $this->ticket->category;

        return $this->buildPayload([
            'title' => trans('notifications.titles.ticket_created'),
            'message' => trans('notifications.messages.ticket_created', [
                'reference' => $this->ticket->reference,
                'seller' => $sellerName,
            ]),
            'reference' => $this->ticket->reference,
            'seller' => $sellerName,
            'category' => $category,
            'url' => route('support-tickets.show', $this->ticket->id),
            'ticket_id' => $this->ticket->id,
        ]);
    }
}
