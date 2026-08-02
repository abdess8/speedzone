<?php

namespace App\Listeners;

use App\Events\SellerRejected;
use App\Mail\SellerRejectedMail;
use App\Services\MailDeliveryService;

class SendSellerRejectedEmail
{
    public function __construct(private readonly MailDeliveryService $mail) {}

    public function handle(SellerRejected $event): void
    {
        $this->mail->send(
            $event->user->email,
            new SellerRejectedMail($event->user, $event->reason),
        );
    }
}
