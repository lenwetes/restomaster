<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkLoginMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $magicUrl,
        public string $email
    ) {}

    public function envelope(): Envelope
    {
        $fromName = config('mail.from.name', 'RestoMaster');
        $fromEmail = config('mail.from.address', 'no-reply@restomaster.com');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: '🍣 Tu enlace seguro de acceso a RestoMaster',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cliente.magic_link',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
