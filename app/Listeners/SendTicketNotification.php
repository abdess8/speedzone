<?php

namespace App\Listeners;

use App\Events\TicketClosed;
use App\Events\TicketCreated;
use App\Events\TicketMessageCreated;
use App\Models\User;
use App\Notifications\TicketClosedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketMessageNotification;
use App\Services\NotificationDispatcher;
use App\Support\SupportPermissions;

class SendTicketNotification
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handleCreated(TicketCreated $event): void
    {
        $staff = User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->whereIn('name', SupportPermissions::staffAccess()))
            ->get();

        if ($staff->isEmpty()) {
            return;
        }

        $this->dispatcher->send(
            $staff,
            new TicketCreatedNotification($event->ticket),
        );
    }

    public function handleMessage(TicketMessageCreated $event): void
    {
        $ticket = $event->ticket->loadMissing(['creator', 'assignee']);
        $sender = $event->sender;
        $recipients = collect();

        if ($ticket->created_by !== $sender->id && $ticket->creator) {
            $recipients->push($ticket->creator);
        }

        if ($ticket->assigned_to && $ticket->assigned_to !== $sender->id && $ticket->assignee) {
            $recipients->push($ticket->assignee);
        }

        if ($recipients->isEmpty()) {
            return;
        }

        $this->dispatcher->send(
            $recipients->unique('id'),
            new TicketMessageNotification($ticket, $event->message),
        );
    }

    public function handleClosed(TicketClosed $event): void
    {
        $ticket = $event->ticket->loadMissing('creator');

        if (! $ticket->creator) {
            return;
        }

        $this->dispatcher->send(
            $ticket->creator,
            new TicketClosedNotification($ticket),
        );
    }
}
