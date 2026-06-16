<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to support staff / admins when a seller opens a new ticket.
 */
class NewSupportTicketNotification extends Notification
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
            'type' => 'support.ticket.created',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
            'created_by' => $this->ticket->created_by,
            'url' => route('support-tickets.show', $this->ticket->id),
        ];
    }
}
