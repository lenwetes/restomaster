<?php

namespace App\Services\Dian;

use App\Models\FacturaElectronica;
use App\Models\Pedido;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DianPosElectronicoService
{
    /**
     * Emite un Documento Equivalente Electrónico (Tiquete POS Electrónico) ante la DIAN
     * o proveedor tecnológico autorizado (Factus, Dataico, Siigo, etc.).
     */
    public function emitirPosElectronico(Pedido $pedido, array $datosCliente = []): FacturaElectronica
    {
        // 1. Idempotencia: si ya existe para este pedido, retornar la existente
        $existente = FacturaElectronica::where('pedido_id', $pedido->id)->first();
        if ($existente) {
            return $existente;
        }

        $config = config('services.dian', []);
        $proveedor = $config['proveedor'] ?? 'factus';
        $prefijo = $config['prefijo'] ?? 'POS';
        $nitEmisor = $config['nit_emisor'] ?? '901234567-8';
        $claveTecnica = $config['clave_tecnica'] ?? 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c';
        $ambiente = (string) ($config['ambiente'] ?? '2'); // 1=Prod, 2=Habilitacion/Sandbox

        // 2. Consecutivo único por sucursal y prefijo
        $ultimoConsecutivo = FacturaElectronica::where('prefijo', $prefijo)
            ->when($pedido->sucursal_id, fn ($q) => $q->where('sucursal_id', $pedido->sucursal_id))
            ->max('consecutivo') ?? 10000;

        $consecutivo = $ultimoConsecutivo + 1;
        $numeroFactura = "{$prefijo}-{$consecutivo}";

        $ahora = Carbon::now();
        $fecFac = $ahora->format('Y-m-d');
        $horFac = $ahora->format('H:i:s').'-05:00';

        $total = (float) $pedido->total;
        // En Colombia el Impuesto Nacional al Consumo (INC) para restaurantes es 8%
        $baseGravable = round($total / 1.08, 2);
        $impuestoInc = round($total - $baseGravable, 2);

        // Datos del adquiriente / cliente
        $clienteNit = $datosCliente['nit'] ?? $pedido->cliente?->identificacion ?? '222222222222';
        $clienteNombre = $datosCliente['nombre'] ?? $pedido->nombre_cliente ?? $pedido->cliente?->nombre ?? 'Consumidor Final';

        // 3. Cálculo de CUFE oficial según fórmula DIAN (SHA-384)
        $cufe = FacturaElectronica::calcularCufe(
            numFac: $numeroFactura,
            fecFac: $fecFac,
            horFac: $ahora->format('H:i:s'),
            valFac: $total,
            valImp: $impuestoInc,
            nitEmisor: preg_replace('/[^0-9]/', '', $nitEmisor),
            nitAdquirente: preg_replace('/[^0-9]/', '', $clienteNit),
            claveTecnica: $claveTecnica,
            tipoAmbiente: $ambiente
        );

        // 4. Cadena y generación de QR oficial DIAN
        $qrCadena = FacturaElectronica::construirCadenaQr(
            numFac: $numeroFactura,
            fecFac: $fecFac,
            horFac: $ahora->format('H:i:s'),
            valFac: $total,
            valImp: $impuestoInc,
            nitEmisor: $nitEmisor,
            nitAdquirente: $clienteNit,
            cufe: $cufe
        );

        $qrSvg = null;
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(200, 1),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            $qrSvg = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($qrCadena));
        } catch (\Throwable $e) {
            Log::warning("Error generando QR para factura {$numeroFactura}: {$e->getMessage()}");
        }

        // 5. Comunicación con Proveedor Tecnológico DIAN
        $estado = 'emitida';
        $respuestaProveedor = null;
        $errorMensaje = null;

        $apiUrl = $config['api_url'] ?? null;
        $apiToken = $config['token'] ?? null;

        if ($apiUrl && $apiToken && ! app()->environment('testing')) {
            try {
                $payloadEnvio = [
                    'prefix' => $prefijo,
                    'number' => $consecutivo,
                    'cufe' => $cufe,
                    'date' => $fecFac,
                    'time' => $horFac,
                    'payment_method' => $pedido->metodo_pago ?? 'efectivo',
                    'customer' => [
                        'identification' => $clienteNit,
                        'name' => $clienteNombre,
                    ],
                    'items' => $pedido->items->map(fn ($item) => [
                        'code' => (string) $item->producto_id,
                        'description' => $item->nombre_producto,
                        'quantity' => $item->cantidad,
                        'price' => (float) $item->precio_unitario,
                        'subtotal' => (float) $item->subtotal,
                        'tax_rate' => 8.0,
                    ])->toArray(),
                    'total' => $total,
                    'tax' => $impuestoInc,
                ];

                $response = Http::timeout(10)
                    ->withToken($apiToken)
                    ->post("{$apiUrl}/v1/bills/validate", $payloadEnvio);

                if ($response->successful()) {
                    $respuestaProveedor = $response->json();
                    $estado = 'emitida';
                } else {
                    $estado = 'contingencia';
                    $errorMensaje = "Proveedor DIAN respondió HTTP {$response->status()}: ".$response->body();
                    $respuestaProveedor = $response->json() ?? ['raw' => $response->body()];
                }
            } catch (\Throwable $e) {
                $estado = 'contingencia';
                $errorMensaje = "Excepción de comunicación con proveedor DIAN: {$e->getMessage()}";
                Log::error($errorMensaje);
            }
        } else {
            // Modo simulado / sandbox local / testing
            $respuestaProveedor = [
                'status' => 'success',
                'provider' => $proveedor,
                'mode' => 'simulado',
                'dian_resolution' => $config['resolucion_numero'] ?? '18764000001',
                'validation_date' => $ahora->toIso8601String(),
                'track_id' => 'TRK-'.strtoupper(substr(md5($cufe), 0, 16)),
            ];
        }

        // 6. Persistencia del documento electrónico
        return FacturaElectronica::create([
            'pedido_id' => $pedido->id,
            'sucursal_id' => $pedido->sucursal_id,
            'tipo_documento' => 'pos_electronico',
            'prefijo' => $prefijo,
            'consecutivo' => $consecutivo,
            'numero_factura' => $numeroFactura,
            'cufe' => $cufe,
            'qr_cadena' => $qrCadena,
            'qr_imagen_url' => $qrSvg,
            'estado' => $estado,
            'total' => $total,
            'impuesto' => $impuestoInc,
            'cliente_nit' => $clienteNit,
            'cliente_nombre' => $clienteNombre,
            'proveedor_tecnologico' => $proveedor,
            'respuesta_proveedor' => $respuestaProveedor,
            'error_mensaje' => $errorMensaje,
            'emitida_en' => $ahora,
        ]);
    }
}
