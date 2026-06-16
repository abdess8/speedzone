<?php

namespace App\Listeners;

use App\Events\InvoiceGenerated;
use App\Notifications\InvoiceGeneratedNotification;
use App\Services\NotificationDispatcher;

class SendInvoiceNotification
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(InvoiceGenerated $event): void
    {
        $invoice = $event->invoice->loadMissing('seller');

        if (! $invoice->seller) {
            return;
        }

        $this->dispatcher->send(
            $invoice->seller,
            new InvoiceGeneratedNotification($invoice),
        );
    }
}
