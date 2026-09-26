<?php

namespace App\Http\Controllers;

use App\Models\CrmConfiguracion;
use App\Models\CrmMensaje;
use App\Models\CrmMensajeLog;
use App\Services\Ai\CrmChatOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CrmWebhookController extends Controller
{
    /**
     * Handshake de verificación requerido por Meta Cloud API (GET).
     */
    public function verificar(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $config = CrmConfiguracion::activa();
        $expectedToken = $config->whatsapp_webhook_secret ?: config('services.whatsapp.webhook_verify_token', 'restomaster_crm_webhook');

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::channel('single')->info('Meta WhatsApp Webhook verificado exitosamente.');

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::channel('single')->warning('Meta WhatsApp Webhook verificación fallida: Token incorrecto.');

        return response()->json(['error' => 'Forbidden: Invalid verify token'], 403);
    }

    /**
     * Recepción de eventos de estado de entrega y lectura de Meta (POST).
     */
    public function recibir(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::channel('single')->info('Meta WhatsApp Webhook Recibido:', ['payload' => $payload]);

        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $statuses = $value['statuses'] ?? [];

                foreach ($statuses as $status) {
                    $wamid = $status['id'] ?? null;
                    $statusName = $status['status'] ?? null; // sent, delivered, read, failed

                    if (! $wamid || ! $statusName) {
                        continue;
                    }

                    $log = CrmMensajeLog::where('mensaje_id_externo', $wamid)->first();
                    if ($log) {
                        if ($statusName === 'delivered') {
                            $log->update(['estado' => 'entregado', 'entregado_en' => now()]);
                        } elseif ($statusName === 'read') {
                            $log->update(['estado' => 'leido', 'leido_en' => now()]);
                        } elseif ($statusName === 'failed') {
                            $errors = $status['errors'] ?? [];
                            $errMsg = ! empty($errors) ? ($errors[0]['title'] ?? 'Error de Meta') : 'Error al entregar';
                            $log->update(['estado' => 'fallido', 'error_mensaje' => $errMsg]);
                        }
                    }

                    // Actualizar también en crm_mensajes si coincide el wamid
                    CrmMensaje::where('wamid', $wamid)->update(['estado_entrega' => $statusName]);
                }

                // Procesamiento de Mensajes Entrantes de Clientes (Inbound WhatsApp)
                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];
                $contactName = $contacts[0]['profile']['name'] ?? null;

                if (! empty($messages)) {
                    /** @var CrmChatOrchestratorService $orchestrator */
                    $orchestrator = app(CrmChatOrchestratorService::class);

                    foreach ($messages as $msg) {
                        $from = $msg['from'] ?? null;
                        $wamid = $msg['id'] ?? null;
                        $body = $msg['text']['body'] ?? ($msg['button']['text'] ?? ($msg['interactive']['button_reply']['title'] ?? null));

                        if ($from && $body) {
                            $conversacion = $orchestrator->obtenerOCrearConversacion(
                                canal: 'whatsapp',
                                identificadorRemoto: $from,
                                nombre: $contactName
                            );

                            $orchestrator->procesarMensajeCliente($conversacion, $body, $wamid);
                        }
                    }
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
