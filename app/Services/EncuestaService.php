<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Encuesta;
use App\Models\EncuestaEnvio;
use App\Models\EncuestaRespuesta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EncuestaService
{
    public const PUNTOS_BASE_ENCUESTA = 50;

    public const PUNTOS_BONUS_CINCO_ESTRELLAS = 25;

    /**
     * Crear una nueva plantilla de encuesta.
     */
    public function crearEncuesta(array $datos): Encuesta
    {
        return Encuesta::create([
            'sucursal_id' => $datos['sucursal_id'] ?? null,
            'nombre' => $datos['nombre'],
            'activa' => $datos['activa'] ?? true,
            'disparador' => $datos['disparador'] ?? 'post_pago',
            'delay_horas' => (int) ($datos['delay_horas'] ?? 1),
            'preguntas' => $datos['preguntas'] ?? [
                ['tipo' => 'estrellas', 'pregunta' => '¿Cómo calificarías tu experiencia general en RestoMaster?'],
                ['tipo' => 'estrellas', 'pregunta' => '¿Qué tal te pareció el sabor y frescura de nuestros platos?'],
                ['tipo' => 'estrellas', 'pregunta' => '¿Cómo calificarías la atención de nuestro equipo?'],
                ['tipo' => 'texto', 'pregunta' => '¿Tienes algún comentario o sugerencia para mejorar?'],
            ],
        ]);
    }

    /**
     * Obtener o inicializar la encuesta activa por defecto para un disparador.
     */
    public function obtenerEncuestaActiva(string $disparador = 'post_pago', ?int $sucursalId = null): Encuesta
    {
        $encuesta = Encuesta::where('activa', true)
            ->where('disparador', $disparador)
            ->when($sucursalId, fn ($q) => $q->where(fn ($sq) => $sq->where('sucursal_id', $sucursalId)->orWhereNull('sucursal_id')))
            ->latest()
            ->first();

        if (! $encuesta) {
            $encuesta = $this->crearEncuesta([
                'sucursal_id' => $sucursalId,
                'nombre' => 'Encuesta de Satisfacción RestoMaster',
                'disparador' => $disparador,
                'delay_horas' => 1,
            ]);
        }

        return $encuesta;
    }

    /**
     * Generar un envío con token único para un cliente tras una visita o comanda.
     */
    public function generarEnvio(
        Encuesta $encuesta,
        Cliente $cliente,
        ?int $pedidoId = null,
        ?int $reservaId = null
    ): EncuestaEnvio {
        $token = Str::random(48);

        return EncuestaEnvio::create([
            'encuesta_id' => $encuesta->id,
            'cliente_id' => $cliente->id,
            'pedido_id' => $pedidoId,
            'reserva_id' => $reservaId,
            'token' => $token,
            'estado' => 'pendiente',
            'enviada_en' => now(),
            'expira_en' => now()->addDays(3),
        ]);
    }

    /**
     * Obtener el envío mediante el token público.
     */
    public function obtenerEnvioPorToken(string $token): ?EncuestaEnvio
    {
        return EncuestaEnvio::with(['encuesta', 'cliente', 'pedido'])
            ->where('token', $token)
            ->first();
    }

    /**
     * Procesar respuestas enviadas por el cliente, calcular rating y otorgar puntos.
     */
    public function responderEncuesta(EncuestaEnvio $envio, array $respuestas): array
    {
        if ($envio->estado === 'respondida') {
            throw new \DomainException('Esta encuesta ya ha sido respondida previamente.');
        }

        if ($envio->expira_en && $envio->expira_en->isPast()) {
            throw new \DomainException('El enlace de esta encuesta ha expirado.');
        }

        return DB::transaction(function () use ($envio, $respuestas) {
            $puntosOtorgados = self::PUNTOS_BASE_ENCUESTA;
            $estrellasGeneral = null;
            $tieneComentario = false;

            foreach ($respuestas as $indice => $valor) {
                $tipo = 'texto';
                $valEstrellas = null;
                $valTexto = null;
                $valBool = null;

                if (is_numeric($valor) && (int) $valor >= 1 && (int) $valor <= 5) {
                    $tipo = 'estrellas';
                    $valEstrellas = (int) $valor;
                    if ($estrellasGeneral === null) {
                        $estrellasGeneral = $valEstrellas;
                    }
                } elseif (is_bool($valor)) {
                    $tipo = 'si_no';
                    $valBool = $valor;
                } else {
                    $tipo = 'texto';
                    $valTexto = trim((string) $valor);
                    if (! empty($valTexto)) {
                        $tieneComentario = true;
                    }
                }

                EncuestaRespuesta::create([
                    'envio_id' => $envio->id,
                    'pregunta_indice' => (int) $indice,
                    'tipo_respuesta' => $tipo,
                    'valor_estrellas' => $valEstrellas,
                    'valor_texto' => $valTexto,
                    'valor_booleano' => $valBool,
                ]);
            }

            // Bonus adicional si otorgó 5 estrellas y dejó comentario
            if ($estrellasGeneral === 5 && $tieneComentario) {
                $puntosOtorgados += self::PUNTOS_BONUS_CINCO_ESTRELLAS;
            }

            // Actualizar estado del envío
            $envio->update([
                'estado' => 'respondida',
                'respondida_en' => now(),
            ]);

            // Actualizar métricas del cliente
            $cliente = $envio->cliente;
            if ($cliente) {
                $cliente->increment('encuestas_respondidas');

                // Recalcular rating promedio histórico
                $promedio = EncuestaRespuesta::whereHas('envio', fn ($q) => $q->where('cliente_id', $cliente->id))
                    ->whereNotNull('valor_estrellas')
                    ->avg('valor_estrellas');

                if ($promedio !== null) {
                    $cliente->update(['rating_promedio' => round($promedio, 2)]);
                }

                // Otorgar puntos mediante FidelizacionService
                app(FidelizacionService::class)->bonusPorEncuesta(
                    $cliente,
                    $puntosOtorgados,
                    "Recompensa por encuesta #{$envio->id} (+{$puntosOtorgados} pts)"
                );
            }

            return [
                'puntos_ganados' => $puntosOtorgados,
                'rating' => $estrellasGeneral,
                'cliente' => $cliente->fresh(),
            ];
        });
    }
}
