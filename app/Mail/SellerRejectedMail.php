<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('seller_registration.emails.rejected_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.seller-rejected',
            with: [
                'user' => $this->user,
                'reason' => $this->reason,
            ],
        );
    }
}
