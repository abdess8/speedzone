<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationDispatcher
{
    /**
     * Dispatch notifications without letting a delivery failure break the request.
     */
    public function send(mixed $notifiables, object $notification): void
    {
        try {
            Notification::send($notifiables, $notification);
        } catch (\Throwable $e) {
            Log::warning('Notification dispatch failed: '.$e->getMessage());
        }
    }
}
