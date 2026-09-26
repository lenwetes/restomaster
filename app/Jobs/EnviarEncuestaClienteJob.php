<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Services\EncuestaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnviarEncuestaClienteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $pedidoId) {}

    public function handle(EncuestaService $encuestaService): void
    {
        $pedido = Pedido::with('cliente')->find($this->pedidoId);

        if (! $pedido || ! $pedido->cliente instanceof Cliente) {
            return;
        }

        $cliente = $pedido->cliente;

        // Validar si el cliente tiene email
        if (empty($cliente->email)) {
            return;
        }

        $encuesta = $encuestaService->obtenerEncuestaActiva('post_pago', $pedido->sucursal_id);
        $envio = $encuestaService->generarEnvio($encuesta, $cliente, $pedido->id);

        $url = route('encuesta.responder', $envio->token);

        Log::info("Encuesta generada para cliente {$cliente->nombre} ({$cliente->email}) por pedido {$pedido->codigo}: {$url}");

        // En producción se enviaría un Mail::to($cliente->email)->send(new EncuestaClienteMail($envio, $url));
    }
}
