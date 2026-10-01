<?php

namespace App\Listeners;

use App\Events\PagoProcesadoPorCaja;
use App\Models\Pedido;
use App\Services\Dian\DianPosElectronicoService;
use Illuminate\Support\Facades\Log;

class EmitirFacturaElectronicaPosListener
{
    public function __construct(
        protected DianPosElectronicoService $dianService
    ) {}

    /**
     * Maneja el evento de pago procesado por caja para emitir el POS Electrónico DIAN con código CUFE.
     */
    public function handle(PagoProcesadoPorCaja $event): void
    {
        try {
            $pedido = Pedido::with(['items', 'cliente'])->find($event->pedidoId);
            if (! $pedido) {
                return;
            }

            $factura = $this->dianService->emitirPosElectronico($pedido);

            // Log de auditoría
            Log::info("POS Electrónico DIAN emitido para pedido #{$pedido->codigo}: Factura {$factura->numero_factura} - CUFE: {$factura->cufe}");
        } catch (\Throwable $e) {
            Log::error("Fallo al emitir POS Electrónico DIAN para pedido #{$event->pedidoId}: {$e->getMessage()}");
        }
    }
}
