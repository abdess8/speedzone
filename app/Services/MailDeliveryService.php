<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailDeliveryService
{
    /**
     * Send a mailable and log success or failure without breaking the request.
     */
    public function send(string $to, Mailable $mailable): bool
    {
        try {
            Mail::to($to)->send($mailable);

            Log::info('Email sent successfully.', [
                'to' => $to,
                'mailable' => $mailable::class,
                'mailer' => config('mail.default'),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Email delivery failed.', [
                'to' => $to,
                'mailable' => $mailable::class,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'error' => $e->getMessage(),
            ]);

            report($e);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function configurationSummary(): array
    {
        return [
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'encryption' => config('mail.mailers.smtp.encryption'),
            'username' => config('mail.mailers.smtp.username'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'queue_connection' => config('queue.default'),
        ];
    }
}
