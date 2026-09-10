<?php

namespace App\Jobs;

use App\Models\TrabajoImpresion;
use App\Services\ImpresionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ImprimirTicketVentaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public int $trabajoId) {}

    public function handle(ImpresionService $impresionService): void
    {
        $trabajo = TrabajoImpresion::with('impresora')->find($this->trabajoId);
        if (! $trabajo) {
            return;
        }

        $exito = $impresionService->procesarTrabajo($trabajo);

        if (! $exito && $this->attempts() < $this->tries) {
            $this->release($this->backoff);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $trabajo = TrabajoImpresion::find($this->trabajoId);
        if ($trabajo) {
            $trabajo->update([
                'estado' => 'error',
                'error_mensaje' => 'Superado el número máximo de reintentos en cola: '.($exception?->getMessage() ?? 'Error desconocido'),
            ]);
        }
    }
}
