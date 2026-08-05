<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\SupportMessage;
use App\Models\SupportTicket;

class TicketMessageNotification extends AppNotification
{
    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportMessage $message,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::TicketMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->message->loadMissing('sender');
        $senderName = $this->message->sender?->name ?? trans('notifications.unknown_user');
        $preview = str($this->message->message ?? '')->limit(120)->toString();

        return $this->buildPayload([
            'title' => trans('notifications.titles.ticket_message'),
            'message' => trans('notifications.messages.ticket_message', [
                'reference' => $this->ticket->reference,
            ]),
            'reference' => $this->ticket->reference,
            'sender' => $senderName,
            'preview' => $preview,
            'url' => route('support-tickets.show', $this->ticket->id),
            'ticket_id' => $this->ticket->id,
            'message_id' => $this->message->id,
        ]);
    }
}
