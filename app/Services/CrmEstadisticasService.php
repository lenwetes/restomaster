<?php

namespace App\Services;

use App\Models\CrmMensajeLog;
use App\Models\EncuestaEnvio;
use App\Models\EncuestaRespuesta;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CrmEstadisticasService
{
    /**
     * Obtiene el resumen general de KPIs de calidad y satisfacción del cliente.
     */
    public function obtenerKpis(?string $fechaInicio = null, ?string $fechaFin = null, ?int $sucursalId = null): array
    {
        $inicio = $fechaInicio ? Carbon::parse($fechaInicio)->startOfDay() : now()->subDays(30)->startOfDay();
        $fin = $fechaFin ? Carbon::parse($fechaFin)->endOfDay() : now()->endOfDay();

        // Query base para envíos
        $queryEnvios = EncuestaEnvio::query()
            ->whereBetween('created_at', [$inicio, $fin]);

        $totalEnviadas = (clone $queryEnvios)->count();
        $totalRespondidas = (clone $queryEnvios)->where('estado', 'respondida')->count();
        $tasaRespuesta = $totalEnviadas > 0 ? round(($totalRespondidas / $totalEnviadas) * 100, 1) : 0;

        // Query base para respuestas de estrellas
        $queryEstrellas = EncuestaRespuesta::query()
            ->where('tipo_respuesta', 'estrellas')
            ->whereNotNull('valor_estrellas')
            ->whereBetween('created_at', [$inicio, $fin]);

        $totalVotosEstrellas = (clone $queryEstrellas)->count();
        $promedioEstrellas = $totalVotosEstrellas > 0 ? round((clone $queryEstrellas)->avg('valor_estrellas'), 2) : 5.0;

        // CSAT: % de clientes que calificaron con 4 o 5 estrellas
        $votosSatisfechos = (clone $queryEstrellas)->whereIn('valor_estrellas', [4, 5])->count();
        $csat = $totalVotosEstrellas > 0 ? round(($votosSatisfechos / $totalVotosEstrellas) * 100, 1) : 100.0;

        // NPS: Promotores (5) - Detractores (1-2)
        $promotores = (clone $queryEstrellas)->where('valor_estrellas', 5)->count();
        $detractores = (clone $queryEstrellas)->whereIn('valor_estrellas', [1, 2])->count();
        $pctPromotores = $totalVotosEstrellas > 0 ? ($promotores / $totalVotosEstrellas) * 100 : 100;
        $pctDetractores = $totalVotosEstrellas > 0 ? ($detractores / $totalVotosEstrellas) * 100 : 0;
        $nps = round($pctPromotores - $pctDetractores, 0);

        // Desglose de estrellas (1 a 5)
        $distribucionEstrellas = [];
        for ($i = 5; $i >= 1; $i--) {
            $cantidad = (clone $queryEstrellas)->where('valor_estrellas', $i)->count();
            $porcentaje = $totalVotosEstrellas > 0 ? round(($cantidad / $totalVotosEstrellas) * 100, 1) : 0;
            $distribucionEstrellas[$i] = [
                'estrellas' => $i,
                'cantidad' => $cantidad,
                'porcentaje' => $porcentaje,
            ];
        }

        // Métricas de logs de canales CRM
        $logsQuery = CrmMensajeLog::query()->whereBetween('created_at', [$inicio, $fin]);
        $totalLogs = (clone $logsQuery)->count();
        $enviadosWa = (clone $logsQuery)->where('canal', 'whatsapp')->whereIn('estado', ['enviado', 'entregado', 'leido'])->count();
        $fallidosWa = (clone $logsQuery)->where('canal', 'whatsapp')->where('estado', 'fallido')->count();
        $enviadosEmail = (clone $logsQuery)->where('canal', 'email')->whereIn('estado', ['enviado', 'entregado', 'leido'])->count();
        $fallidosEmail = (clone $logsQuery)->where('canal', 'email')->where('estado', 'fallido')->count();

        return [
            'total_enviadas' => $totalEnviadas,
            'total_respondidas' => $totalRespondidas,
            'tasa_respuesta' => $tasaRespuesta,
            'promedio_estrellas' => $promedioEstrellas,
            'csat' => $csat,
            'nps' => $nps,
            'distribucion_estrellas' => $distribucionEstrellas,
            'total_votos' => $totalVotosEstrellas,
            'canales' => [
                'total_mensajes' => $totalLogs,
                'whatsapp_enviados' => $enviadosWa,
                'whatsapp_fallidos' => $fallidosWa,
                'email_enviados' => $enviadosEmail,
                'email_fallidos' => $fallidosEmail,
            ],
        ];
    }

    /**
     * Ranking de satisfacción de servicio por mesero.
     */
    public function rankingCalidadMeseros(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $inicio = $fechaInicio ? Carbon::parse($fechaInicio)->startOfDay() : now()->subDays(30)->startOfDay();
        $fin = $fechaFin ? Carbon::parse($fechaFin)->endOfDay() : now()->endOfDay();

        $meseros = User::whereHas('role', function ($q) {
            $q->whereIn('slug', ['mesero', 'camarero'])->orWhereIn('nombre', ['Mesero', 'Camarero']);
        })->get();

        if ($meseros->isEmpty()) {
            $meseros = User::take(10)->get();
        }

        $ranking = [];

        foreach ($meseros as $mesero) {
            $stats = DB::table('encuesta_respuestas')
                ->join('encuesta_envios', 'encuesta_respuestas.envio_id', '=', 'encuesta_envios.id')
                ->join('pedidos', 'encuesta_envios.pedido_id', '=', 'pedidos.id')
                ->where('pedidos.mesero_id', $mesero->id)
                ->where('encuesta_respuestas.tipo_respuesta', 'estrellas')
                ->whereNotNull('encuesta_respuestas.valor_estrellas')
                ->whereBetween('encuesta_respuestas.created_at', [$inicio, $fin])
                ->selectRaw('COUNT(*) as total_evaluaciones, AVG(valor_estrellas) as promedio, SUM(CASE WHEN valor_estrellas >= 4 THEN 1 ELSE 0 END) as votos_positivos')
                ->first();

            $total = $stats->total_evaluaciones ?? 0;
            $promedio = $total > 0 ? round((float) $stats->promedio, 2) : 5.0;
            $satisfaccion = $total > 0 ? round(((float) $stats->votos_positivos / $total) * 100, 1) : 100.0;

            $ranking[] = [
                'id' => $mesero->id,
                'nombre' => $mesero->name,
                'email' => $mesero->email,
                'evaluaciones' => $total,
                'promedio_estrellas' => $promedio,
                'porcentaje_satisfaccion' => $satisfaccion,
            ];
        }

        usort($ranking, fn ($a, $b) => $b['promedio_estrellas'] <=> $a['promedio_estrellas']);

        return $ranking;
    }

    /**
     * Muro de últimas opiniones con comentarios de texto y estrellas.
     */
    public function muroOpinionesRecientes(int $limite = 15): array
    {
        $respuestas = EncuestaRespuesta::with(['envio.cliente', 'envio.pedido.mesa'])
            ->where('tipo_respuesta', 'texto')
            ->whereNotNull('valor_texto')
            ->where('valor_texto', '!=', '')
            ->latest()
            ->take($limite)
            ->get();

        $opiniones = [];

        foreach ($respuestas as $resp) {
            $envio = $resp->envio;
            // Buscar si en el mismo envío hay respuesta de estrellas
            $estrellaResp = EncuestaRespuesta::where('envio_id', $envio->id)
                ->where('tipo_respuesta', 'estrellas')
                ->first();

            $opiniones[] = [
                'id' => $resp->id,
                'cliente' => $envio->cliente ? $envio->cliente->nombre : 'Cliente Anónimo',
                'comentario' => $resp->valor_texto,
                'estrellas' => $estrellaResp ? $estrellaResp->valor_estrellas : 5,
                'fecha' => $resp->created_at->format('d/m/Y H:i'),
                'hace_tiempo' => $resp->created_at->diffForHumans(),
                'mesa' => $envio->pedido && $envio->pedido->mesa ? 'Mesa '.$envio->pedido->mesa->numero : null,
                'total_pedido' => $envio->pedido ? '$'.number_format($envio->pedido->total, 2) : null,
            ];
        }

        return $opiniones;
    }
}
