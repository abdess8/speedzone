<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyOwlDeliveryAccountEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return parent::toMail($notifiable)
            ->subject(__('seller_registration.emails.verification_subject'));
    }
}
