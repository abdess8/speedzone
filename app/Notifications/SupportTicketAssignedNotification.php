<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a support agent when a ticket is assigned to them.
 */
class SupportTicketAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'support.ticket.assigned',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
            'assigned_to' => $this->ticket->assigned_to,
            'url' => route('support-tickets.show', $this->ticket->id),
        ];
    }
}
