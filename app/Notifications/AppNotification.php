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
     * Entitlement, then preference.
     *
     * The recipient lists are built by the listeners, but they are chosen for
     * who can *act* on an event; who should be *told* about it is a separate
     * question, and one the sender must not be trusted to answer alone. The
     * last word therefore belongs to the recipient's own role.
     */
    protected function shouldSendTo(User $notifiable): bool
    {
        return app(NotificationPreferenceService::class)
            ->isEnabled($notifiable, $this->notificationType());
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
