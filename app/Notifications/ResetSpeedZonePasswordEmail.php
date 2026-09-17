<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetSpeedZonePasswordEmail extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $locale = $this->localeFor($notifiable);
        $this->locale($locale);

        $name = trim((string) ($notifiable->first_name ?: $notifiable->name));
        $greeting = $name !== ''
            ? __('mail.reset_greeting', ['name' => $name], $locale)
            : __('mail.hello', [], $locale);

        $expire = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('mail.reset_subject', [], $locale))
            ->greeting($greeting)
            ->line(__('mail.reset_intro', [], $locale))
            ->action(
                __('mail.reset_button', [], $locale),
                $this->resetUrl($notifiable)
            )
            ->line(__('mail.reset_expire', ['count' => $expire], $locale))
            ->line(__('mail.reset_ignore', [], $locale));
    }

    private function localeFor(object $notifiable): string
    {
        $locale = $notifiable->locale ?? 'fr';

        return in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';
    }
}
