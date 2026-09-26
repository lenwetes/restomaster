<?php

namespace App\Services;

use App\Jobs\DespacharMensajeCrmJob;
use App\Models\Cliente;
use App\Models\CrmAutomatizacion;
use App\Models\CrmConfiguracion;
use App\Models\Pedido;
use App\Models\Reserva;
use Carbon\Carbon;

class CrmAutomatizacionService
{
    public function __construct(
        protected EncuestaService $encuestaService,
        protected CrmWhatsAppService $whatsAppService,
        protected CrmEmailService $emailService
    ) {}

    /**
     * Procesa la automatización post-cobro de pedido (Encuesta de satisfacción).
     */
    public function procesarCobroPedido(Pedido $pedido): void
    {
        $automatizacion = CrmAutomatizacion::with(['plantillaWhatsapp', 'plantillaEmail'])
            ->activas()
            ->where('evento_disparador', 'pedido_cobrado')
            ->first();

        if (! $automatizacion) {
            return;
        }

        $cliente = $pedido->cliente;
        if (! $cliente) {
            return;
        }

        $config = CrmConfiguracion::activa($pedido->sucursal_id);

        // Generar envío de encuesta
        $encuesta = $this->encuestaService->obtenerEncuestaActiva('post_pago', $pedido->sucursal_id);
        $envio = $this->encuestaService->generarEnvio($encuesta, $cliente, $pedido->id);
        $urlEncuesta = route('encuesta.responder', $envio->token);

        $delayMinutos = $automatizacion->delay_minutos ?? $config->delay_encuesta_minutos;

        // Variables dinámicas
        $variables = [
            'nombre' => $cliente->nombre,
            'restaurante' => 'RestoMaster',
            'url_encuesta' => $urlEncuesta,
            'codigo_pedido' => $pedido->codigo,
            'total' => '$'.number_format($pedido->total, 2),
        ];

        // 1. Canal WhatsApp si está habilitado en la regla y el cliente tiene teléfono
        if (in_array($automatizacion->canal, ['whatsapp', 'ambos']) && ! empty($cliente->telefono)) {
            $plantilla = $automatizacion->plantillaWhatsapp;
            $contenido = $plantilla
                ? $plantilla->renderizar($variables)
                : "¡Hola {$cliente->nombre}! 🍣 Gracias por visitarnos en RestoMaster. Califícanos aquí: {$urlEncuesta}";

            $job = new DespacharMensajeCrmJob(
                canal: 'whatsapp',
                destinatario: $cliente->telefono,
                contenido: $contenido,
                templateName: $plantilla?->whatsapp_template_name,
                templateParams: [$cliente->nombre, $urlEncuesta],
                clienteId: $cliente->id,
                pedidoId: $pedido->id,
                automatizacionId: $automatizacion->id,
                sucursalId: $pedido->sucursal_id,
                urlEncuesta: $urlEncuesta,
                encuestaEnvioId: $envio->id
            );

            if ($delayMinutos > 0) {
                dispatch($job)->delay(now()->addMinutes($delayMinutos));
            } else {
                dispatch($job);
            }
        }

        // 2. Canal Email si está habilitado en la regla o fallback si no hay teléfono
        $debeEnviarEmail = in_array($automatizacion->canal, ['email', 'ambos']) ||
            ($automatizacion->canal === 'whatsapp' && empty($cliente->telefono));

        if ($debeEnviarEmail && ! empty($cliente->email)) {
            $plantillaEmail = $automatizacion->plantillaEmail;
            $asunto = $plantillaEmail?->asunto
                ? $plantillaEmail->renderizar($variables)
                : "¿Cómo estuvo tu experiencia en RestoMaster, {$cliente->nombre}? 🍣";
            $contenidoHtml = $plantillaEmail?->renderizar($variables);

            $jobEmail = new DespacharMensajeCrmJob(
                canal: 'email',
                destinatario: $cliente->email,
                contenido: $contenidoHtml ?? '',
                asunto: $asunto,
                clienteId: $cliente->id,
                pedidoId: $pedido->id,
                automatizacionId: $automatizacion->id,
                sucursalId: $pedido->sucursal_id,
                urlEncuesta: $urlEncuesta,
                encuestaEnvioId: $envio->id
            );

            if ($delayMinutos > 0) {
                dispatch($jobEmail)->delay(now()->addMinutes($delayMinutos));
            } else {
                dispatch($jobEmail);
            }
        }
    }

    /**
     * Procesa la confirmación de una reserva enviando notificación WhatsApp inmediata.
     */
    public function procesarConfirmacionReserva(Reserva $reserva): void
    {
        $automatizacion = CrmAutomatizacion::with('plantillaWhatsapp')
            ->activas()
            ->where('evento_disparador', 'reserva_confirmada')
            ->first();

        if (! $automatizacion) {
            return;
        }

        $telefono = $reserva->telefono_contacto ?: $reserva->cliente?->telefono;
        if (empty($telefono)) {
            return;
        }

        $mesaNombre = $reserva->mesas->pluck('numero')->implode(', ') ?: 'Asignada al llegar';
        $nombreCliente = $reserva->nombre_contacto ?: ($reserva->cliente?->nombre ?: 'Estimado Cliente');

        $variables = [
            'nombre' => $nombreCliente,
            'restaurante' => 'RestoMaster',
            'fecha_reserva' => Carbon::parse($reserva->fecha)->format('d/m/Y'),
            'hora_reserva' => substr($reserva->hora_llegada, 0, 5),
            'personas' => $reserva->personas,
            'mesa' => $mesaNombre,
        ];

        $plantilla = $automatizacion->plantillaWhatsapp;
        $contenido = $plantilla
            ? $plantilla->renderizar($variables)
            : "¡Hola {$nombreCliente}! Tu reserva en RestoMaster el {$variables['fecha_reserva']} a las {$variables['hora_reserva']} está confirmada.";

        dispatch(new DespacharMensajeCrmJob(
            canal: 'whatsapp',
            destinatario: $telefono,
            contenido: $contenido,
            templateName: $plantilla?->whatsapp_template_name,
            templateParams: [$nombreCliente, $variables['fecha_reserva'], $variables['hora_reserva'], $mesaNombre],
            clienteId: $reserva->cliente_id,
            reservaId: $reserva->id,
            automatizacionId: $automatizacion->id,
            sucursalId: $reserva->sucursal_id
        ));
    }

    /**
     * Procesa el recordatorio 2 horas antes de la reserva.
     */
    public function procesarRecordatorioReserva(Reserva $reserva): void
    {
        $automatizacion = CrmAutomatizacion::with('plantillaWhatsapp')
            ->activas()
            ->where('evento_disparador', 'reserva_recordatorio_2h')
            ->first();

        if (! $automatizacion) {
            return;
        }

        $telefono = $reserva->telefono_contacto ?: $reserva->cliente?->telefono;
        if (empty($telefono)) {
            return;
        }

        $mesaNombre = $reserva->mesas->pluck('numero')->implode(', ') ?: 'Asignada';
        $nombreCliente = $reserva->nombre_contacto ?: ($reserva->cliente?->nombre ?: 'Estimado Cliente');

        $variables = [
            'nombre' => $nombreCliente,
            'restaurante' => 'RestoMaster',
            'hora_reserva' => substr($reserva->hora_llegada, 0, 5),
            'mesa' => $mesaNombre,
        ];

        $plantilla = $automatizacion->plantillaWhatsapp;
        $contenido = $plantilla
            ? $plantilla->renderizar($variables)
            : "¡Hola {$nombreCliente}! Te recordamos que hoy a las {$variables['hora_reserva']} te esperamos en RestoMaster.";

        dispatch(new DespacharMensajeCrmJob(
            canal: 'whatsapp',
            destinatario: $telefono,
            contenido: $contenido,
            templateName: $plantilla?->whatsapp_template_name,
            templateParams: [$nombreCliente, $variables['hora_reserva'], $mesaNombre],
            clienteId: $reserva->cliente_id,
            reservaId: $reserva->id,
            automatizacionId: $automatizacion->id,
            sucursalId: $reserva->sucursal_id
        ));
    }

    /**
     * Evalúa si la hora actual está dentro del horario anti-spam configurado.
     */
    public function estaEnHorarioPermitido(?int $sucursalId = null): bool
    {
        $config = CrmConfiguracion::activa($sucursalId);
        $ahora = now()->format('H:i');

        return $ahora >= $config->horario_envio_inicio && $ahora <= $config->horario_envio_fin;
    }
}
