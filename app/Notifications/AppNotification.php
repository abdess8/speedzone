<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function notificationType(): NotificationType;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User || ! $this->shouldSendTo($notifiable)) {
            return [];
        }

        $channels = ['database'];

        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    /**
     * Whether this announcement should reach this user.
     *
     * Listeners pick a candidate list; this is the last word, so a vendor
     * who cannot open the users screen is never told a shop signed up even
     * if he was accidentally included upstream.
     */
    public function reaches(User $notifiable): bool
    {
        return $this->shouldSendTo($notifiable);
    }

    /**
     * Active account, can see the row, entitled to the topic, preference on.
     */
    protected function shouldSendTo(User $notifiable): bool
    {
        if (! $notifiable->isAccountActive()) {
            return false;
        }

        if (! $this->authorizes($notifiable)) {
            return false;
        }

        return app(NotificationPreferenceService::class)
            ->isEnabled($notifiable, $this->notificationType());
    }

    /**
     * Whether the recipient may see the object this notification is about.
     */
    protected function authorizes(User $notifiable): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildPayload(array $payload): array
    {
        return array_merge([
            'type' => $this->notificationType()->value,
        ], $payload);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
