<?php

namespace App\Listeners;

use App\Enums\UserStatus;
use App\Events\InvoiceGenerated;
use App\Notifications\InvoiceGeneratedNotification;
use App\Services\NotificationDispatcher;

class SendInvoiceNotification
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(InvoiceGenerated $event): void
    {
        $invoice = $event->invoice->loadMissing(['seller.roles.permissions', 'seller.permissions', 'seller.stores']);

        if (! $invoice->seller) {
            return;
        }

        $recipients = collect([$invoice->seller])
            ->merge(
                $invoice->seller->teamMembers()
                    ->where('status', UserStatus::Active->value)
                    ->with(['roles.permissions', 'permissions', 'stores'])
                    ->get()
            )
            ->unique('id')
            ->values();

        $this->dispatcher->send(
            $recipients,
            new InvoiceGeneratedNotification($invoice),
        );
    }
}
