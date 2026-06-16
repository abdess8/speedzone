<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\SupportTicket;

class TicketClosedNotification extends AppNotification
{
    public function __construct(public readonly SupportTicket $ticket) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::TicketClosed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->buildPayload([
            'title' => trans('notifications.titles.ticket_closed'),
            'message' => trans('notifications.messages.ticket_closed', [
                'reference' => $this->ticket->reference,
            ]),
            'reference' => $this->ticket->reference,
            'url' => route('support-tickets.show', $this->ticket->id),
            'ticket_id' => $this->ticket->id,
        ]);
    }
}
