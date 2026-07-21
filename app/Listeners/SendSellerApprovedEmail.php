<?php

namespace App\Listeners;

use App\Events\SellerApproved;
use App\Mail\SellerApprovedMail;
use App\Services\MailDeliveryService;

class SendSellerApprovedEmail
{
    public function __construct(private readonly MailDeliveryService $mail) {}

    public function handle(SellerApproved $event): void
    {
        $this->mail->send($event->user->email, new SellerApprovedMail($event->user));
    }
}
