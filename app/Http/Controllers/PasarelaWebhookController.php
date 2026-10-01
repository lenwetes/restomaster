<?php

namespace App\Http\Controllers;

use App\Services\Pasarela\PasarelaPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PasarelaWebhookController extends Controller
{
    public function __construct(
        protected PasarelaPagoService $pasarelaService
    ) {}

    /**
     * Endpoint receptor de webhooks de Wompi y Bold.
     */
    public function handle(Request $request, string $proveedor): JsonResponse
    {
        $payload = $request->all();
        $signatureHeader = $request->header('x-bold-signature') ?? $request->header('x-signature');

        if (! in_array(strtolower($proveedor), ['wompi', 'bold'], true)) {
            return response()->json(['error' => 'Proveedor de pasarela no soportado.'], 400);
        }

        if (! $this->pasarelaService->verificarFirmaWebhook($proveedor, $payload, $signatureHeader)) {
            Log::warning("Firma de webhook inválida para proveedor {$proveedor}", ['ip' => $request->ip()]);

            return response()->json(['error' => 'Firma de autenticación inválida.'], 401);
        }

        $pago = $this->pasarelaService->procesarWebhook($proveedor, $payload);

        if (! $pago) {
            return response()->json(['status' => 'ignored', 'mensaje' => 'Referencia no encontrada o evento no relevante.'], 200);
        }

        return response()->json([
            'status' => 'success',
            'referencia' => $pago->referencia,
            'estado' => $pago->estado,
        ], 200);
    }
}
