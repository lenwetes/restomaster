<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\EncuestaEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EncuestaClienteMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cliente $cliente,
        public EncuestaEnvio $envio,
        public string $urlEncuesta,
        public ?string $asuntoPersonalizado = null,
        public ?string $contenidoHtml = null
    ) {}

    public function envelope(): Envelope
    {
        $config = CrmConfiguracion::activa();
        $fromName = $config->email_remitente_nombre ?: config('mail.from.name', 'RestoMaster Experiencia');
        $fromEmail = $config->email_remitente_correo ?: config('mail.from.address', 'experiencia@restomaster.com');

        $asunto = $this->asuntoPersonalizado ?: "¿Cómo estuvo tu experiencia en RestoMaster, {$this->cliente->nombre}? 🍣";

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: $asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.crm.encuesta',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
