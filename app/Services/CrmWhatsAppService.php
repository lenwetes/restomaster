<?php

namespace App\Services;

use App\Models\CrmConfiguracion;
use App\Models\CrmMensajeLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrmWhatsAppService
{
    /**
     * Enviar mensaje de WhatsApp (texto directo o plantilla) a un destinatario.
     */
    public function enviarMensaje(
        string $telefono,
        string $contenido,
        ?string $templateName = null,
        array $templateParams = [],
        ?int $clienteId = null,
        ?int $pedidoId = null,
        ?int $reservaId = null,
        ?int $automatizacionId = null,
        ?int $sucursalId = null
    ): CrmMensajeLog {
        $config = CrmConfiguracion::activa($sucursalId);
        $telefonoNormalizado = $this->normalizarTelefono($telefono);

        // Crear registro en log en estado pendiente
        $log = CrmMensajeLog::create([
            'automatizacion_id' => $automatizacionId,
            'cliente_id' => $clienteId,
            'pedido_id' => $pedidoId,
            'reserva_id' => $reservaId,
            'canal' => 'whatsapp',
            'destinatario' => $telefonoNormalizado,
            'asunto' => $templateName ? "Template: {$templateName}" : 'Mensaje Directo WhatsApp',
            'contenido_enviado' => $contenido,
            'estado' => 'pendiente',
            'metadata' => [
                'template_name' => $templateName,
                'template_params' => $templateParams,
                'proveedor' => $config->whatsapp_proveedor,
            ],
        ]);

        // Determinar si debemos despachar a la API real de Meta o simular
        $esSimulado = $this->debeSimular($config);

        if ($esSimulado) {
            $fakeMessageId = 'wamid.HBg'.Str::random(32);
            $log->update([
                'estado' => 'enviado',
                'mensaje_id_externo' => $fakeMessageId,
                'enviado_en' => now(),
                'entregado_en' => now()->addSeconds(2),
                'metadata' => array_merge($log->metadata ?? [], [
                    'simulado' => true,
                    'simulacion_nota' => 'Despachado en modo simulado/local con éxito.',
                ]),
            ]);

            Log::channel('single')->info('WhatsApp CRM [Simulado]: Mensaje despachado.', [
                'destinatario' => $telefonoNormalizado,
                'log_id' => $log->id,
                'contenido' => Str::limit($contenido, 100),
            ]);

            return $log;
        }

        // Envío real a Meta Cloud API v21.0
        try {
            $apiVersion = config('services.whatsapp.api_version', 'v21.0');
            $phoneNumberId = $config->whatsapp_phone_number_id ?: config('services.whatsapp.phone_number_id');
            $token = $config->whatsapp_access_token ?: config('services.whatsapp.token');

            $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $telefonoNormalizado,
            ];

            if ($templateName) {
                $payload['type'] = 'template';
                $payload['template'] = [
                    'name' => $templateName,
                    'language' => ['code' => 'es'],
                ];
                if (! empty($templateParams)) {
                    $payload['template']['components'] = [
                        [
                            'type' => 'body',
                            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $templateParams),
                        ],
                    ];
                }
            } else {
                $payload['type'] = 'text';
                $payload['text'] = [
                    'preview_url' => true,
                    'body' => $contenido,
                ];
            }

            $response = Http::withToken($token)
                ->timeout(12)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $wamid = $data['messages'][0]['id'] ?? 'wamid.'.Str::random(24);

                $log->update([
                    'estado' => 'enviado',
                    'mensaje_id_externo' => $wamid,
                    'enviado_en' => now(),
                    'metadata' => array_merge($log->metadata ?? [], ['meta_response' => $data]),
                ]);
            } else {
                $errorData = $response->json();
                $errorMsg = $errorData['error']['message'] ?? $response->body();

                $log->update([
                    'estado' => 'fallido',
                    'error_mensaje' => $errorMsg,
                    'metadata' => array_merge($log->metadata ?? [], ['error_detail' => $errorData]),
                ]);

                Log::channel('single')->error('WhatsApp CRM [Error API Meta]:', [
                    'destinatario' => $telefonoNormalizado,
                    'status' => $response->status(),
                    'error' => $errorMsg,
                ]);
            }
        } catch (\Throwable $e) {
            $log->update([
                'estado' => 'fallido',
                'error_mensaje' => $e->getMessage(),
            ]);

            Log::channel('single')->error('WhatsApp CRM [Excepción]: '.$e->getMessage());
        }

        return $log;
    }

    /**
     * Normaliza un número telefónico a formato internacional (E.164).
     */
    public function normalizarTelefono(string $telefono): string
    {
        // Remover espacios, guiones, paréntesis
        $limpio = preg_replace('/[^\d+]/', '', trim($telefono));

        // Si empieza con +, remover el + para la API de Meta que pide dígitos puros (ej: 573001234567)
        if (str_starts_with($limpio, '+')) {
            $limpio = substr($limpio, 1);
        }

        // Si no tiene código de país y tiene 10 dígitos (común en Colombia/México), prefijar 57
        if (strlen($limpio) === 10 && str_starts_with($limpio, '3')) {
            $limpio = '57'.$limpio;
        }

        return $limpio;
    }

    /**
     * Determina si debe operar en modo simulado.
     */
    protected function debeSimular(CrmConfiguracion $config): bool
    {
        if ($config->whatsapp_proveedor === 'simulado') {
            return true;
        }

        $token = $config->whatsapp_access_token ?: config('services.whatsapp.token');
        $phoneId = $config->whatsapp_phone_number_id ?: config('services.whatsapp.phone_number_id');

        if (empty($token) || empty($phoneId) || str_contains($token, 'sampleMeta') || app()->environment('testing')) {
            return true;
        }

        return false;
    }
}
