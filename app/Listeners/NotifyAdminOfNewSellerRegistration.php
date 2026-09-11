<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\NewSellerRegistered;
use App\Notifications\NewSellerRegistrationNotification;
use App\Services\NotificationDispatcher;
use App\Support\NotificationRecipients;

class NotifyAdminOfNewSellerRegistration
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(NewSellerRegistered $event): void
    {
        $admins = NotificationRecipients::query(NotificationType::SellerRegistered)->get();

        if ($admins->isEmpty()) {
            return;
        }

        $this->dispatcher->send(
            $admins,
            new NewSellerRegistrationNotification($event->user),
        );
    }
}
