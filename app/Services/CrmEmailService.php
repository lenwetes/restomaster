<?php

namespace App\Services;

use App\Mail\EncuestaClienteMailable;
use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmMensajeLog;
use App\Models\EncuestaEnvio;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CrmEmailService
{
    /**
     * Enviar correo de encuesta o notificación CRM.
     */
    public function enviarEncuesta(
        Cliente $cliente,
        EncuestaEnvio $envio,
        string $urlEncuesta,
        ?string $asunto = null,
        ?string $contenidoHtml = null,
        ?int $automatizacionId = null,
        ?int $sucursalId = null
    ): CrmMensajeLog {
        $config = CrmConfiguracion::activa($sucursalId);

        $log = CrmMensajeLog::create([
            'automatizacion_id' => $automatizacionId,
            'cliente_id' => $cliente->id,
            'pedido_id' => $envio->pedido_id,
            'reserva_id' => $envio->reserva_id,
            'canal' => 'email',
            'destinatario' => $cliente->email ?? 'sin-email@restomaster.com',
            'asunto' => $asunto ?: "¿Cómo estuvo tu experiencia en RestoMaster, {$cliente->nombre}?",
            'contenido_enviado' => $contenidoHtml ?: "Encuesta enviada con URL: {$urlEncuesta}",
            'estado' => 'pendiente',
            'metadata' => [
                'token' => $envio->token,
                'url_encuesta' => $urlEncuesta,
            ],
        ]);

        if (! $config->email_activo) {
            $log->update([
                'estado' => 'cancelado',
                'error_mensaje' => 'Canal de email desactivado en la configuración CRM.',
            ]);

            return $log;
        }

        if (empty($cliente->email) || ! filter_var($cliente->email, FILTER_VALIDATE_EMAIL)) {
            $log->update([
                'estado' => 'fallido',
                'error_mensaje' => 'El cliente no tiene un correo electrónico válido registrado.',
            ]);

            return $log;
        }

        try {
            $config->aplicarConfiguracionMailer();

            Mail::to($cliente->email)->send(
                new EncuestaClienteMailable(
                    cliente: $cliente,
                    envio: $envio,
                    urlEncuesta: $urlEncuesta,
                    asuntoPersonalizado: $asunto,
                    contenidoHtml: $contenidoHtml
                )
            );

            $log->update([
                'estado' => 'enviado',
                'enviado_en' => now(),
            ]);

            Log::channel('single')->info("Email CRM: Despachado exitosamente a {$cliente->email}");
        } catch (\Throwable $e) {
            $log->update([
                'estado' => 'fallido',
                'error_mensaje' => $e->getMessage(),
            ]);

            Log::channel('single')->error("Email CRM [Error de envío]: {$e->getMessage()}");
        }

        return $log;
    }

    /**
     * Enviar correo de prueba inmediato para verificar credenciales del motor.
     */
    public function enviarCorreoPrueba(string $destinatario, ?int $sucursalId = null): array
    {
        $config = CrmConfiguracion::activa($sucursalId);
        $config->aplicarConfiguracionMailer();

        try {
            Mail::raw(
                "¡Hola! Este es un correo de prueba oficial de RestoMaster CRM.\n\n".
                "Tu motor de correo ({$config->email_driver}) y credenciales SMTP están correctamente configurados y listos para despachar encuestas y notificaciones.\n\n".
                'Fecha y hora del despacho: '.now()->format('d/m/Y H:i:s'),
                function ($message) use ($destinatario) {
                    $message->to($destinatario)
                        ->subject('✅ Prueba Exitosa de Correo — RestoMaster CRM');
                }
            );

            return [
                'success' => true,
                'mensaje' => "¡Correo de prueba despachado con éxito a {$destinatario}!",
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'mensaje' => 'Error al enviar correo de prueba: '.$e->getMessage(),
            ];
        }
    }
}
