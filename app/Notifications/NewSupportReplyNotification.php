<?php

namespace App\Notifications;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the other party when a new reply is posted on a ticket.
 */
class NewSupportReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportMessage $message,
    ) {}

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
            'type' => 'support.ticket.reply',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'message_id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'url' => route('support-tickets.show', $this->ticket->id),
        ];
    }
}
