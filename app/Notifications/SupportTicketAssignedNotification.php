<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\SupportTicket;

class SupportTicketAssignedNotification extends AppNotification
{
    public function __construct(public readonly SupportTicket $ticket) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::System;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->buildPayload([
            'title' => trans('notifications.titles.ticket_assigned'),
            'message' => trans('notifications.messages.ticket_assigned', [
                'reference' => $this->ticket->reference,
            ]),
            'reference' => $this->ticket->reference,
            'url' => route('support-tickets.show', $this->ticket->id),
            'ticket_id' => $this->ticket->id,
        ]);
    }
}
