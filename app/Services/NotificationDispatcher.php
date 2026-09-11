<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationDispatcher
{
    /**
     * Dispatch notifications without letting a delivery failure break the request.
     *
     * Recipients who cannot see the object, or who are not entitled to the
     * topic, are dropped here so a listener cannot leak an announcement by
     * building a list that is too wide.
     */
    public function send(mixed $notifiables, object $notification): void
    {
        $recipients = $this->recipients($notifiables, $notification);

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, $notification);
        } catch (\Throwable $e) {
            Log::warning('Notification dispatch failed: '.$e->getMessage());
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(mixed $notifiables, object $notification): Collection
    {
        $candidates = Collection::wrap($notifiables)
            ->flatten()
            ->filter(fn ($notifiable) => $notifiable instanceof User)
            ->unique(fn (User $user) => $user->id)
            ->values();

        if (! $notification instanceof AppNotification) {
            return $candidates;
        }

        return $candidates
            ->filter(fn (User $user) => $notification->reaches($user))
            ->values();
    }
}
