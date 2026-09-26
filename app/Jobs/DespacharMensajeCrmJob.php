<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Models\CrmAutomatizacion;
use App\Models\EncuestaEnvio;
use App\Services\CrmEmailService;
use App\Services\CrmWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DespacharMensajeCrmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $canal,
        public string $destinatario,
        public string $contenido,
        public ?string $asunto = null,
        public ?string $templateName = null,
        public array $templateParams = [],
        public ?int $clienteId = null,
        public ?int $pedidoId = null,
        public ?int $reservaId = null,
        public ?int $automatizacionId = null,
        public ?int $sucursalId = null,
        public ?string $urlEncuesta = null,
        public ?int $encuestaEnvioId = null
    ) {}

    public function handle(CrmWhatsAppService $whatsAppService, CrmEmailService $emailService): void
    {
        Log::channel('single')->info("Iniciando despacho de mensaje CRM canal: {$this->canal} hacia {$this->destinatario}");

        if ($this->canal === 'whatsapp') {
            $whatsAppService->enviarMensaje(
                telefono: $this->destinatario,
                contenido: $this->contenido,
                templateName: $this->templateName,
                templateParams: $this->templateParams,
                clienteId: $this->clienteId,
                pedidoId: $this->pedidoId,
                reservaId: $this->reservaId,
                automatizacionId: $this->automatizacionId,
                sucursalId: $this->sucursalId
            );
        } elseif ($this->canal === 'email') {
            $cliente = $this->clienteId ? Cliente::find($this->clienteId) : null;
            $envio = $this->encuestaEnvioId ? EncuestaEnvio::find($this->encuestaEnvioId) : null;

            if ($cliente && $envio) {
                $emailService->enviarEncuesta(
                    cliente: $cliente,
                    envio: $envio,
                    urlEncuesta: $this->urlEncuesta ?? route('encuesta.responder', $envio->token),
                    asunto: $this->asunto,
                    contenidoHtml: $this->contenido,
                    automatizacionId: $this->automatizacionId,
                    sucursalId: $this->sucursalId
                );
            }
        }

        if ($this->automatizacionId) {
            CrmAutomatizacion::where('id', $this->automatizacionId)->increment('total_disparos');
        }
    }
}
