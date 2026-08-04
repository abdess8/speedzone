<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwlDeliverySmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OWL Delivery SMTP Test',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>SMTP configuration is working correctly.</p>',
        );
    }
}
