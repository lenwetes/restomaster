<?php

namespace App\Services\Pasarela;

use App\Models\PagoPasarela;
use App\Models\Pedido;
use App\Services\PedidoService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasarelaPagoService
{
    /**
     * Genera un enlace de pago y código QR para cobro dinámico en mesa (Wompi / Bold).
     */
    public function generarQrCobroMesa(Pedido $pedido, string $proveedor = 'wompi'): PagoPasarela
    {
        $proveedor = strtolower($proveedor) === 'bold' ? 'bold' : 'wompi';
        $monto = (float) $pedido->total;
        $referencia = "RESTO-PED{$pedido->id}-".strtoupper(Str::random(6));

        $checkoutUrl = '';
        $firma = '';

        if ($proveedor === 'wompi') {
            $config = config('services.wompi');
            $pubKey = $config['public_key'] ?? 'pub_test_default';
            $integritySecret = $config['integrity_secret'] ?? 'test_integrity_secret';
            $montoCentavos = (int) round($monto * 100);

            // Firma de Integridad Wompi: SHA256(Referencia + MontoEnCentavos + Moneda + SecretoIntegridad)
            $cadenaFirma = "{$referencia}{$montoCentavos}COP{$integritySecret}";
            $firma = hash('sha256', $cadenaFirma);

            $checkoutUrl = "https://checkout.wompi.co/p/?public-key={$pubKey}&currency=COP&amount-in-cents={$montoCentavos}&reference={$referencia}&signature:integrity={$firma}";
        } else {
            // Bold Smart Link
            $config = config('services.bold');
            $apiKey = $config['api_key'] ?? 'bold_test_key';
            $secretKey = $config['secret_key'] ?? 'bold_secret_key';

            $cadenaFirma = "{$referencia}{$monto}COP{$secretKey}";
            $firma = hash('sha256', $cadenaFirma);

            $checkoutUrl = "https://checkout.bold.co/payment/{$referencia}?apiKey={$apiKey}";
        }

        // Generación del código QR en formato SVG Base64
        $qrSvg = null;
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(220, 1),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            $qrSvg = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($checkoutUrl));
        } catch (\Throwable $e) {
            Log::warning("Error generando QR pasarela para pedido {$pedido->id}: {$e->getMessage()}");
        }

        return PagoPasarela::create([
            'pedido_id' => $pedido->id,
            'sucursal_id' => $pedido->sucursal_id,
            'proveedor' => $proveedor,
            'referencia' => $referencia,
            'monto' => $monto,
            'moneda' => 'COP',
            'metodo_pasarela' => 'qr_mesa',
            'estado' => 'pendiente',
            'checkout_url' => $checkoutUrl,
            'qr_cadena' => $checkoutUrl,
            'qr_imagen' => $qrSvg,
            'firma_integridad' => $firma,
            'datos_transaccion' => [
                'mesa' => $pedido->mesa?->numero,
                'generado_en' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Envía una orden de cobro a un datáfono inteligente en mostrador o mesa (Bold Smart Terminal / Wompi).
     */
    public function enviarCobroDatafono(Pedido $pedido, string $terminalId, string $proveedor = 'bold'): PagoPasarela
    {
        $proveedor = strtolower($proveedor) === 'wompi' ? 'wompi' : 'bold';
        $monto = (float) $pedido->total;
        $referencia = "DATA-PED{$pedido->id}-".strtoupper(Str::random(6));

        $pago = PagoPasarela::create([
            'pedido_id' => $pedido->id,
            'sucursal_id' => $pedido->sucursal_id,
            'proveedor' => $proveedor,
            'referencia' => $referencia,
            'monto' => $monto,
            'moneda' => 'COP',
            'metodo_pasarela' => 'datafono_smart',
            'terminal_id' => $terminalId,
            'estado' => 'pendiente',
            'datos_transaccion' => [
                'terminal_id' => $terminalId,
                'solicitado_en' => now()->toIso8601String(),
            ],
        ]);

        $apiUrl = config("services.{$proveedor}.api_url");
        $apiKey = config("services.{$proveedor}.api_key") ?? config("services.{$proveedor}.private_key");

        if ($apiUrl && $apiKey && ! app()->environment('testing')) {
            try {
                Http::timeout(10)
                    ->withToken($apiKey)
                    ->post("{$apiUrl}/terminals/{$terminalId}/orders", [
                        'amount' => $monto,
                        'reference' => $referencia,
                        'currency' => 'COP',
                    ]);
            } catch (\Throwable $e) {
                Log::error("Fallo comunicación con datáfono inteligente {$terminalId}: {$e->getMessage()}");
            }
        }

        return $pago;
    }

    /**
     * Verifica la autenticidad criptográfica de un webhook entrante.
     */
    public function verificarFirmaWebhook(string $proveedor, array $payload, ?string $signatureHeader = null): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $proveedor = strtolower($proveedor);

        if ($proveedor === 'wompi') {
            $secret = config('services.wompi.events_secret');
            if (! $secret) {
                return true;
            }

            // Wompi calcula checksum SHA256 sobre las propiedades del evento + secret
            $signatureObj = $payload['signature'] ?? [];
            $properties = $signatureObj['properties'] ?? [];
            $receivedChecksum = $signatureObj['checksum'] ?? null;

            if (! $receivedChecksum || empty($properties)) {
                return false;
            }

            $concatenated = '';
            foreach ($properties as $propPath) {
                $parts = explode('.', $propPath);
                $curr = $payload;
                foreach ($parts as $p) {
                    $curr = $curr[$p] ?? '';
                }
                $concatenated .= $curr;
            }
            $concatenated .= $secret;

            return hash('sha256', $concatenated) === $receivedChecksum;
        }

        if ($proveedor === 'bold') {
            $secret = config('services.bold.secret_key');
            if (! $secret || ! $signatureHeader) {
                return true;
            }

            $computed = hash_hmac('sha256', json_encode($payload), $secret);

            return hash_equals($computed, $signatureHeader);
        }

        return false;
    }

    /**
     * Procesa la notificación de pago confirmada desde el webhook de la pasarela.
     */
    public function procesarWebhook(string $proveedor, array $payload): ?PagoPasarela
    {
        $proveedor = strtolower($proveedor);
        $referencia = null;
        $transaccionId = null;
        $estado = null;
        $monto = 0.0;

        if ($proveedor === 'wompi') {
            $data = $payload['data']['transaction'] ?? [];
            $referencia = $data['reference'] ?? null;
            $transaccionId = $data['id'] ?? null;
            $statusRaw = strtoupper($data['status'] ?? '');
            $estado = match ($statusRaw) {
                'APPROVED' => 'aprobado',
                'DECLINED', 'ERROR' => 'rechazada',
                'VOIDED' => 'anulada',
                default => 'pendiente',
            };
            $monto = ((float) ($data['amount_in_cents'] ?? 0)) / 100;
        } elseif ($proveedor === 'bold') {
            $referencia = $payload['reference'] ?? $payload['order_id'] ?? null;
            $transaccionId = $payload['transaction_id'] ?? $payload['id'] ?? null;
            $statusRaw = strtoupper($payload['status'] ?? '');
            $estado = match ($statusRaw) {
                'APPROVED', 'SUCCESS' => 'aprobado',
                'REJECTED', 'FAILED' => 'rechazada',
                default => 'pendiente',
            };
            $monto = (float) ($payload['amount'] ?? 0);
        }

        if (! $referencia) {
            return null;
        }

        $pago = PagoPasarela::where('referencia', $referencia)->first();
        if (! $pago) {
            return null;
        }

        $pago->update([
            'transaccion_id' => $transaccionId,
            'estado' => $estado,
            'datos_transaccion' => array_merge($pago->datos_transaccion ?? [], $payload),
            'pagado_en' => $estado === 'aprobado' ? now() : null,
        ]);

        // Si el pago fue aprobado y el pedido aún no está pagado, se procesa automáticamente
        if ($estado === 'aprobado' && $pago->pedido && $pago->pedido->estado !== 'pagado') {
            try {
                app(PedidoService::class)->cobrarPedido(
                    pedido: $pago->pedido,
                    metodoPago: "pasarela_{$proveedor}",
                    montoPagado: $pago->monto
                );
            } catch (\Throwable $e) {
                Log::error("Error cobrando pedido {$pago->pedido_id} post-webhook pasarela: {$e->getMessage()}");
            }
        }

        return $pago;
    }
}
