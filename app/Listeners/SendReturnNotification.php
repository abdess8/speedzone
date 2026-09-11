<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\ReturnInitiatedByRole;
use App\Events\ReturnRequested;
use App\Notifications\ReturnRequestedNotification;
use App\Services\NotificationDispatcher;
use App\Support\NotificationRecipients;

class SendReturnNotification
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(ReturnRequested $event): void
    {
        if ($event->role !== ReturnInitiatedByRole::SELLER) {
            return;
        }

        $admins = NotificationRecipients::query(NotificationType::ReturnRequested)->get();

        if ($admins->isEmpty()) {
            return;
        }

        $this->dispatcher->send(
            $admins,
            new ReturnRequestedNotification($event->return),
        );
    }
}
