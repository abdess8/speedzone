<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('seller_registration.emails.approved_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.seller-approved',
            with: [
                'user' => $this->user,
                'loginUrl' => route('login'),
            ],
        );
    }
}
