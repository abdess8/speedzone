<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifySpeedZoneAccountEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $locale = $this->localeFor($notifiable);
        $this->locale($locale);

        return (new MailMessage)
            ->subject(__('seller_registration.emails.verification_subject', [], $locale))
            ->greeting(__('seller_registration.emails.verification_greeting', [], $locale))
            ->line(__('seller_registration.emails.verification_intro', [], $locale))
            ->action(
                __('seller_registration.emails.verification_button', [], $locale),
                $this->verificationUrl($notifiable)
            )
            ->line(__('seller_registration.emails.verification_ignore', [], $locale));
    }

    private function localeFor(object $notifiable): string
    {
        $locale = $notifiable->locale ?? config('app.locale', 'fr');

        return in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';
    }
}
