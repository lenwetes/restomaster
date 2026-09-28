<?php

namespace App\Services\Ai;

use App\Models\Caja;
use App\Models\CrmConfiguracion;
use App\Models\Encuesta;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Promocion;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminAiCopilotService
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Procesa la consulta de lenguaje natural del administrador/gerente con entendimiento
     * semántico avanzado, analítica profunda en base de datos, generación de gráficos y síntesis conversacional con Gemini.
     */
    public function procesarConsulta(string $mensaje, User $usuario): array
    {
        // 1. Verificación estricta de autorización de back-office
        if (! $usuario->isAdmin() && ! $usuario->isGerente()) {
            return [
                'tipo' => 'error_autorizacion',
                'mensaje' => 'Acceso denegado: El Copiloto Ejecutivo IA está restringido exclusivamente a Administradores y Gerentes de RestoMaster.',
            ];
        }

        $mensajeNormalizado = Str::lower(trim($mensaje));

        // 2. Intención: Inventario e Insumos Críticos
        if ($this->esIntencionInventario($mensajeNormalizado)) {
            return $this->ejecutarAuditoriaInventario();
        }

        // 3. Intención: Diseñar o Crear Encuesta
        if ($this->esIntencionEncuesta($mensajeNormalizado)) {
            return $this->ejecutarGeneracionEncuesta($mensaje);
        }

        // 4. Intención: Crear Promoción
        if ($this->esIntencionPromocion($mensajeNormalizado)) {
            return $this->ejecutarGeneracionPromocion($mensaje);
        }

        // 5. Intención: Generar Imagen / Infografía de Estadísticas
        if ($this->esConsultaImagenOInfografia($mensajeNormalizado)) {
            return $this->ejecutarGeneracionInfografia($usuario, $mensaje);
        }

        // 6. Extracción de Entidades Semánticas (Caja y Rango Temporal)
        $caja = $this->resolverCaja($mensajeNormalizado, $usuario->sucursal_id);
        $rangoFecha = $this->resolverRangoFecha($mensajeNormalizado);

        // 7. Consulta específica de Caja (ej. "ventas en caja 1", "caja barra ayer", "estado de caja 2")
        if ($caja !== null) {
            return $this->ejecutarConsultaVentasCaja($usuario, $caja, $rangoFecha, $mensaje);
        }

        // 8. Consulta de Métodos de Pago (ej. "cuánto en efectivo vs tarjeta", "desglose de pagos")
        if ($this->esConsultaMetodosPago($mensajeNormalizado)) {
            return $this->ejecutarConsultaMetodosPago($usuario, $rangoFecha, $mensaje);
        }

        // 9. Consulta de Rendimiento de Meseros / Personal (ej. "ventas por mesero", "atención")
        if ($this->esConsultaMeseros($mensajeNormalizado)) {
            return $this->ejecutarConsultaMeseros($usuario, $rangoFecha, $mensaje);
        }

        // 10. Consulta de Platos / Top Productos (ej. "platos más vendidos", "top 5 sushi")
        if ($this->esConsultaTopProductos($mensajeNormalizado)) {
            return $this->ejecutarConsultaTopProductos($usuario, $rangoFecha, 5, $mensaje);
        }

        // 11. Consulta con Período Temporal Específico (ej. "ventas del martes de esta semana", "ayer", "este mes")
        if ($rangoFecha !== null) {
            if ($rangoFecha['periodo'] === 'hoy') {
                return $this->ejecutarResumenVentas($usuario);
            }

            return $this->ejecutarConsultaVentasPeriodo($usuario, $rangoFecha, $mensaje);
        }

        // 12. Petición explícita de Gráficos (ej. "gráfico de ventas", "comparativa de facturación")
        if ($this->esConsultaGrafico($mensajeNormalizado)) {
            return $this->ejecutarConsultaGraficoGeneral($usuario, $rangoFecha, $mensaje);
        }

        // 13. Resumen general de ventas si menciona ventas/facturación/kpis sin filtro específico
        if ($this->esIntencionVentas($mensajeNormalizado)) {
            return $this->ejecutarResumenVentas($usuario);
        }

        // 14. Consulta Conversacional a Gemini con Conocimiento Completo en Vivo del Restaurante
        $respuestaConversacional = $this->ejecutarConsultaConversacionalLlm($mensaje, $usuario);
        if ($respuestaConversacional !== null) {
            return $respuestaConversacional;
        }

        // 15. Mensaje de orientación persuasiva si no encaja con ninguna herramienta ni LLM
        return $this->generarOrientacionPersuasiva();
    }

    /**
     * Obtiene la configuración de IA activa con fallback garantizado a la clave global del sistema.
     */
    public function obtenerConfiguracionIa(?int $sucursalId = null): ?CrmConfiguracion
    {
        $config = CrmConfiguracion::activa($sucursalId);
        if ($config->iaActiva() && ! empty($config->obtenerApiKeyIa())) {
            return $config;
        }

        // Fallback a configuración global si existe
        $global = CrmConfiguracion::whereNull('sucursal_id')->first();
        if ($global && $global->iaActiva() && ! empty($global->obtenerApiKeyIa())) {
            return $global;
        }

        return $config->iaActiva() ? $config : null;
    }

    /**
     * Resuelve menciones a Cajas registradoras (ej. "caja 1", "caja #01", "caja principal", "caja barra").
     */
    public function resolverCaja(string $texto, ?int $sucursalId = null): ?Caja
    {
        $query = Caja::query()->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId));

        // Patrón numérico: "caja 1", "caja #1", "caja 01", "caja 2"
        if (preg_match('/\bcajas?\s*(?:#|n[uú]mero|num\.?)?\s*0*([1-9]\d*)\b/iu', $texto, $matches)) {
            $num = (int) $matches[1];

            // Buscar por ID exacto
            $cajaPorId = (clone $query)->where('id', $num)->first();
            if ($cajaPorId) {
                return $cajaPorId;
            }

            // Buscar por código o nombre que contenga el número
            $codigoBuscado = sprintf('CAJ-%02d', $num);
            $cajaPorCodigo = (clone $query)->where('codigo', $codigoBuscado)
                ->orWhere('nombre', 'like', "%{$num}%")
                ->first();

            if ($cajaPorCodigo) {
                return $cajaPorCodigo;
            }
        }

        // Patrón semántico de tipo o nombre: "caja principal", "caja barra", "caja salon", "caja delivery"
        if (preg_match('/\bcajas?\s+(principal|barra|sal[oó]n|delivery|mostrador)\b/iu', $texto, $matches)) {
            $termino = Str::lower($matches[1]);
            $terminoSinTilde = Str::ascii($termino);

            return (clone $query)
                ->where(function ($q) use ($termino, $terminoSinTilde) {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ["%{$termino}%"])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', ["%{$terminoSinTilde}%"])
                        ->orWhereRaw('LOWER(tipo) LIKE ?', ["%{$termino}%"]);
                })
                ->first();
        }

        return null;
    }

    /**
     * Resuelve fechas y períodos temporales expresados en lenguaje natural humano en español.
     *
     * @return array{inicio: Carbon, fin: Carbon, etiqueta: string, periodo: string}|null
     */
    public function resolverRangoFecha(string $texto): ?array
    {
        $t = Str::lower($texto);

        // 1. "hoy"
        if (preg_match('/\b(hoy|el d[ií]a de hoy|al d[ií]a de hoy)\b/iu', $t)) {
            return [
                'inicio' => Carbon::today()->startOfDay(),
                'fin' => Carbon::today()->endOfDay(),
                'etiqueta' => 'Hoy ('.Carbon::today()->format('d/m/Y').')',
                'periodo' => 'hoy',
            ];
        }

        // 2. "ayer"
        if (preg_match('/\b(ayer|el d[ií]a de ayer|en el d[ií]a de ayer)\b/iu', $t)) {
            $ayer = Carbon::yesterday();

            return [
                'inicio' => $ayer->copy()->startOfDay(),
                'fin' => $ayer->copy()->endOfDay(),
                'etiqueta' => 'Ayer ('.$ayer->format('d/m/Y').')',
                'periodo' => 'ayer',
            ];
        }

        // 3. "anteayer" o "antier"
        if (preg_match('/\b(anteayer|antier)\b/iu', $t)) {
            $anteayer = Carbon::today()->subDays(2);

            return [
                'inicio' => $anteayer->copy()->startOfDay(),
                'fin' => $anteayer->copy()->endOfDay(),
                'etiqueta' => 'Anteayer ('.$anteayer->format('d/m/Y').')',
                'periodo' => 'anteayer',
            ];
        }

        // 4. Días específicos de la semana: "martes de esta semana", "dia martes", "el miercoles pasado", "viernes"
        $mapaDias = [
            'lunes' => 0,
            'martes' => 1,
            'miercoles' => 2,
            'miércoles' => 2,
            'jueves' => 3,
            'viernes' => 4,
            'sabado' => 5,
            'sábado' => 5,
            'domingo' => 6,
        ];

        if (preg_match('/(?:del?\s+)?(?:d[ií]a\s+)?(lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo)(?:\s+(?:de\s+)?(esta\s+semana|pasad[oa]|anterior))?/iu', $t, $m)) {
            $diaStr = Str::lower($m[1]);
            $offset = $mapaDias[$diaStr] ?? 0;
            $modificador = Str::lower($m[2] ?? '');

            $baseSemana = Str::contains($modificador, ['pasad', 'anterior'])
                ? Carbon::now()->subWeek()->startOfWeek()
                : Carbon::now()->startOfWeek();

            $fechaDestino = $baseSemana->copy()->addDays($offset);

            return [
                'inicio' => $fechaDestino->copy()->startOfDay(),
                'fin' => $fechaDestino->copy()->endOfDay(),
                'etiqueta' => ucfirst($diaStr).' ('.$fechaDestino->format('d/m/Y').')',
                'periodo' => 'dia_semana',
            ];
        }

        // 5. "esta semana"
        if (preg_match('/\b(esta semana|semana actual)\b/iu', $t)) {
            return [
                'inicio' => Carbon::now()->startOfWeek()->startOfDay(),
                'fin' => Carbon::now()->endOfWeek()->endOfDay(),
                'etiqueta' => 'Esta Semana ('.Carbon::now()->startOfWeek()->format('d/m').' — '.Carbon::now()->endOfWeek()->format('d/m').')',
                'periodo' => 'esta_semana',
            ];
        }

        // 6. "la semana pasada" / "semana anterior"
        if (preg_match('/\b(semana pasada|semana anterior|la semana pasada)\b/iu', $t)) {
            $inicio = Carbon::now()->subWeek()->startOfWeek()->startOfDay();
            $fin = Carbon::now()->subWeek()->endOfWeek()->endOfDay();

            return [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => 'Semana Pasada ('.$inicio->format('d/m').' — '.$fin->format('d/m').')',
                'periodo' => 'semana_pasada',
            ];
        }

        // 7. "este mes"
        if (preg_match('/\b(este mes|mes actual)\b/iu', $t)) {
            return [
                'inicio' => Carbon::now()->startOfMonth()->startOfDay(),
                'fin' => Carbon::now()->endOfMonth()->endOfDay(),
                'etiqueta' => 'Este Mes ('.Carbon::now()->format('m/Y').')',
                'periodo' => 'este_mes',
            ];
        }

        // 8. "el mes pasado"
        if (preg_match('/\b(mes pasado|mes anterior)\b/iu', $t)) {
            $inicio = Carbon::now()->subMonth()->startOfMonth()->startOfDay();
            $fin = Carbon::now()->subMonth()->endOfMonth()->endOfDay();

            return [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => 'Mes Pasado ('.$inicio->format('m/Y').')',
                'periodo' => 'mes_pasado',
            ];
        }

        // 9. "últimos N días"
        if (preg_match('/[uú]ltimos\s+(\d+)\s+d[ií]as/iu', $t, $m)) {
            $dias = (int) $m[1];
            $inicio = Carbon::now()->subDays($dias)->startOfDay();
            $fin = Carbon::now()->endOfDay();

            return [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => "Últimos {$dias} días",
                'periodo' => 'ultimos_dias',
            ];
        }

        return null;
    }

    /**
     * Consulta profunda de una Caja Registradora específica con desglose de turnos, pedidos y métodos de pago.
     */
    public function ejecutarConsultaVentasCaja(User $usuario, Caja $caja, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        // Determinar pedidos asociados a esta caja
        $pedidosQuery = Pedido::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->whereHas('turnoCaja', fn ($q) => $q->where('caja_id', $caja->id))
            ->where('estado', 'pagado');

        if ($rangoFecha) {
            $pedidosQuery->whereBetween('pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            $etiquetaFiltro = $rangoFecha['etiqueta'];
        } else {
            $etiquetaFiltro = 'Histórico Reciente / Último Turno';
        }

        $pedidos = $pedidosQuery->orderBy('pagado_en', 'asc')->get();

        $totalVentas = (float) $pedidos->sum('total');
        $totalComandas = $pedidos->count();
        $ticketPromedio = $totalComandas > 0 ? round($totalVentas / $totalComandas, 2) : 0.0;

        // Desglose de métodos de pago
        $porEfectivo = (float) $pedidos->where('metodo_pago', 'efectivo')->sum('total');
        $porTarjeta = (float) $pedidos->where('metodo_pago', 'tarjeta')->sum('total');
        $porTransferencia = (float) $pedidos->where('metodo_pago', 'transferencia')->sum('total');

        // Último turno de la caja
        $ultimoTurno = TurnoCaja::where('caja_id', $caja->id)->latest('id')->first();

        $ventasFmt = number_format($totalVentas, 0, ',', '.');
        $ticketFmt = number_format($ticketPromedio, 0, ',', '.');

        $mensajeBase = "📊 **Auditoría de Ventas — {$caja->nombre} ({$caja->codigo}):**\n\n"
            ."• **Período Consultado:** {$etiquetaFiltro}\n"
            ."• **Ventas Totales:** \${$ventasFmt} COP\n"
            ."• **Comandas / Tickets:** {$totalComandas} pedidos cerrados\n"
            ."• **Ticket Promedio:** \${$ticketFmt} COP\n\n"
            ."💳 **Desglose de Formas de Pago:**\n"
            .'• Efectivo: $'.number_format($porEfectivo, 0, ',', '.').' COP'.($totalVentas > 0 ? ' ('.round(($porEfectivo / $totalVentas) * 100).'%)' : '')."\n"
            .'• Tarjetas (Débito/Crédito): $'.number_format($porTarjeta, 0, ',', '.').' COP'.($totalVentas > 0 ? ' ('.round(($porTarjeta / $totalVentas) * 100).'%)' : '')."\n"
            .'• Transferencias / Digital: $'.number_format($porTransferencia, 0, ',', '.').' COP'.($totalVentas > 0 ? ' ('.round(($porTransferencia / $totalVentas) * 100).'%)' : '')."\n\n";

        if ($ultimoTurno) {
            $estadoTurno = $ultimoTurno->estado === 'abierto' ? '🟢 Turno Abierto Actualmente' : '🔴 Turno Cerrado';
            $fechaApertura = $ultimoTurno->apertura_en ? $ultimoTurno->apertura_en->format('d/m H:i') : 'N/A';
            $cajeroNombre = $ultimoTurno->cajero?->name ?? 'Asignado';
            $mensajeBase .= "🔐 **Estado del Turno en Caja:** {$estadoTurno}\n"
                ."• Cajero: {$cajeroNombre} | Apertura: {$fechaApertura} | Fondo: \$".number_format((float) $ultimoTurno->monto_inicial, 0, ',', '.')." COP\n";
        }

        // Construir datos de gráfico interactivo para la caja
        $grafico = [
            'tipo' => 'bar',
            'titulo' => "Facturación por Comanda — {$caja->nombre}",
            'subtitulo' => "Detalle de pedidos pagados ({$totalComandas} comandas)",
            'etiquetas' => $pedidos->take(8)->map(fn ($p) => $p->codigo)->all() ?: ['Sin pedidos'],
            'valores' => $pedidos->take(8)->map(fn ($p) => (float) $p->total)->all() ?: [0],
            'valores_formateados' => $pedidos->take(8)->map(fn ($p) => '$'.number_format((float) $p->total, 0, ',', '.'))->all() ?: ['$0'],
            'colores' => ['#e0442e', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'],
            'unidad' => 'COP',
        ];

        // Consultar a Gemini si está activo para enriquecer con comentarios estratégicos
        $contexto = [
            'caja_nombre' => $caja->nombre,
            'caja_codigo' => $caja->codigo,
            'periodo' => $etiquetaFiltro,
            'ventas_totales' => $totalVentas,
            'comandas' => $totalComandas,
            'ticket_promedio' => $ticketPromedio,
            'desglose_pagos' => [
                'efectivo' => $porEfectivo,
                'tarjeta' => $porTarjeta,
                'transferencia' => $porTransferencia,
            ],
            'ultimo_turno' => $ultimoTurno ? [
                'estado' => $ultimoTurno->estado,
                'cajero' => $ultimoTurno->cajero?->name,
                'fondo_inicial' => (float) $ultimoTurno->monto_inicial,
            ] : null,
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Ventas en '.$caja->nombre, $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'kpis' => [
                    'ventas' => $totalVentas,
                    'transacciones' => $totalComandas,
                    'ticket_promedio' => $ticketPromedio,
                    'variacion_ventas_pct' => 0.0,
                ],
                'grafico' => $grafico,
                'caja' => [
                    'id' => $caja->id,
                    'nombre' => $caja->nombre,
                    'codigo' => $caja->codigo,
                ],
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Módulo de Caja',
                'url' => '/caja',
                'icono' => 'point_of_sale',
            ],
        ];
    }

    /**
     * Consulta de ventas por período o día específico (ej. "ventas del martes de esta semana", "ayer").
     */
    public function ejecutarConsultaVentasPeriodo(User $usuario, array $rangoFecha, ?string $mensajeOriginal = null): array
    {
        $inicio = $rangoFecha['inicio'];
        $fin = $rangoFecha['fin'];
        $etiqueta = $rangoFecha['etiqueta'];

        // Consultar pedidos pagados en el rango
        $pedidos = Pedido::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$inicio, $fin])
            ->get();

        $totalVentas = (float) $pedidos->sum('total');
        $transacciones = $pedidos->count();
        $ticketPromedio = $transacciones > 0 ? round($totalVentas / $transacciones, 2) : 0.0;

        $ventasFmt = number_format($totalVentas, 0, ',', '.');
        $ticketFmt = number_format($ticketPromedio, 0, ',', '.');

        // Contexto semanal complementario
        $inicioSemana = Carbon::now()->startOfWeek()->startOfDay();
        $finSemana = Carbon::now()->endOfWeek()->endOfDay();

        $pedidosSemana = Pedido::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$inicioSemana, $finSemana])
            ->get(['total', 'pagado_en']);

        $diasSemanaLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $valoresDiasSemana = array_fill(0, 7, 0.0);

        foreach ($pedidosSemana as $p) {
            if ($p->pagado_en) {
                $idx = Carbon::parse($p->pagado_en)->dayOfWeekIso - 1;
                if (isset($valoresDiasSemana[$idx])) {
                    $valoresDiasSemana[$idx] += (float) $p->total;
                }
            }
        }

        $totalSemana = array_sum($valoresDiasSemana);
        $totalSemanaFmt = number_format($totalSemana, 0, ',', '.');

        $mensajeBase = "📅 **Reporte de Ventas — {$etiqueta}:**\n\n"
            ."• **Facturación:** \${$ventasFmt} COP\n"
            ."• **Comandas / Tickets:** {$transacciones} pedidos cerrados\n"
            ."• **Ticket Promedio:** \${$ticketFmt} COP\n\n";

        if ($totalVentas === 0.0) {
            $mensajeBase .= '💡 **Observación:** No se registraron pedidos cerrados en esa fecha específica. '
                ."Durante esta semana en curso se registra un acumulado total de **\${$totalSemanaFmt} COP** en el restaurante.\n\n";
        } else {
            $mensajeBase .= "🎉 **Operación activa:** Se registraron {$transacciones} servicios completados con éxito durante este período.\n\n";
        }

        // Gráfico semanal comparativo
        $grafico = [
            'tipo' => 'bar',
            'titulo' => "Flujo de Ventas de la Semana ({$inicioSemana->format('d/m')} — {$finSemana->format('d/m')})",
            'subtitulo' => 'Comparativa diaria de facturación semanal',
            'etiquetas' => $diasSemanaLabels,
            'valores' => $valoresDiasSemana,
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valoresDiasSemana),
            'colores' => array_map(function ($idx, $val) {
                return $val > 0 ? '#10b981' : '#6b7280';
            }, array_keys($valoresDiasSemana), $valoresDiasSemana),
            'unidad' => 'COP',
        ];

        $contexto = [
            'periodo_consultado' => $etiqueta,
            'ventas_periodo' => $totalVentas,
            'pedidos_periodo' => $transacciones,
            'ticket_promedio' => $ticketPromedio,
            'resumen_semanal' => [
                'lunes' => $valoresDiasSemana[0],
                'martes' => $valoresDiasSemana[1],
                'miercoles' => $valoresDiasSemana[2],
                'jueves' => $valoresDiasSemana[3],
                'viernes' => $valoresDiasSemana[4],
                'sabado' => $valoresDiasSemana[5],
                'domingo' => $valoresDiasSemana[6],
            ],
            'total_semana' => $totalSemana,
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Ventas de '.$etiqueta, $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'kpis' => [
                    'ventas' => $totalVentas,
                    'transacciones' => $transacciones,
                    'ticket_promedio' => $ticketPromedio,
                    'variacion_ventas_pct' => 0.0,
                ],
                'grafico' => $grafico,
                'periodo' => $etiqueta,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Módulo de Reportes',
                'url' => '/reportes',
                'icono' => 'analytics',
            ],
        ];
    }

    /**
     * Consulta de desglose por métodos de pago con gráfico doughnut/barras.
     */
    public function ejecutarConsultaMetodosPago(User $usuario, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        $query = Pedido::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->where('estado', 'pagado');

        if ($rangoFecha) {
            $query->whereBetween('pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            $etiqueta = $rangoFecha['etiqueta'];
        } else {
            $etiqueta = 'Semana Actual';
            $query->whereBetween('pagado_en', [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfWeek()->endOfDay()]);
        }

        $desglose = (clone $query)
            ->select('metodo_pago', DB::raw('SUM(total) as total_monto'), DB::raw('COUNT(*) as total_pedidos'))
            ->groupBy('metodo_pago')
            ->get();

        $totalGeneral = (float) $desglose->sum('total_monto');
        $pedidosTotal = (int) $desglose->sum('total_pedidos');

        $labels = [];
        $valores = [];
        $porcentajes = [];
        $colores = [
            'efectivo' => '#10b981',
            'tarjeta' => '#3b82f6',
            'transferencia' => '#8b5cf6',
            'mixto' => '#f59e0b',
        ];
        $coloresLista = [];

        $mensajeBase = "💳 **Desglose de Métodos de Pago — {$etiqueta}:**\n\n"
            .'• **Recaudo Total:** $'.number_format($totalGeneral, 0, ',', '.')." COP ({$pedidosTotal} comandas)\n\n";

        foreach ($desglose as $item) {
            $metodo = $item->metodo_pago ?: 'otros';
            $monto = (float) $item->total_monto;
            $pct = $totalGeneral > 0 ? round(($monto / $totalGeneral) * 100, 1) : 0.0;
            $nombreFmt = Str::headline($metodo);

            $labels[] = $nombreFmt;
            $valores[] = $monto;
            $porcentajes[] = $pct;
            $coloresLista[] = $colores[$metodo] ?? '#6b7280';

            $mensajeBase .= "• **{$nombreFmt}:** \$".number_format($monto, 0, ',', '.')." COP ({$pct}% — {$item->total_pedidos} tickets)\n";
        }

        if ($desglose->isEmpty()) {
            $labels = ['Sin transacciones'];
            $valores = [0];
            $porcentajes = [100];
            $coloresLista = ['#6b7280'];
            $mensajeBase .= "No se registran pagos en el período seleccionado.\n";
        }

        $grafico = [
            'tipo' => 'doughnut',
            'titulo' => "Participación por Forma de Pago — {$etiqueta}",
            'subtitulo' => 'Distribución porcentual de recaudos',
            'etiquetas' => $labels,
            'valores' => $valores,
            'porcentajes' => $porcentajes,
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores),
            'colores' => $coloresLista,
            'unidad' => 'COP',
        ];

        $contexto = [
            'periodo' => $etiqueta,
            'recaudo_total' => $totalGeneral,
            'desglose' => $desglose->toArray(),
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Métodos de pago en '.$etiqueta, $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'kpis' => [
                    'ventas' => $totalGeneral,
                    'transacciones' => $pedidosTotal,
                    'ticket_promedio' => $pedidosTotal > 0 ? round($totalGeneral / $pedidosTotal, 2) : 0.0,
                    'variacion_ventas_pct' => 0.0,
                ],
                'grafico' => $grafico,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Ver Cuadre de Caja',
                'url' => '/caja',
                'icono' => 'account_balance_wallet',
            ],
        ];
    }

    /**
     * Consulta de platos y categorías más vendidas.
     */
    public function ejecutarConsultaTopProductos(User $usuario, ?array $rangoFecha = null, int $limite = 5, ?string $mensajeOriginal = null): array
    {
        $query = ItemPedido::query()
            ->join('pedidos', 'items_pedido.pedido_id', '=', 'pedidos.id')
            ->join('productos', 'items_pedido.producto_id', '=', 'productos.id')
            ->where('pedidos.sucursal_id', $usuario->sucursal_id)
            ->where('pedidos.estado', 'pagado');

        if ($rangoFecha) {
            $query->whereBetween('pedidos.pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            $etiqueta = $rangoFecha['etiqueta'];
        } else {
            $etiqueta = 'Histórico Acumulado';
        }

        $top = (clone $query)
            ->select('productos.nombre as producto', DB::raw('SUM(items_pedido.cantidad) as total_cantidad'), DB::raw('SUM(items_pedido.subtotal) as total_ventas'))
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('total_ventas')
            ->limit($limite)
            ->get();

        $labels = [];
        $valores = [];
        $mensajeBase = "🍣 **Top {$limite} Platos Más Vendidos — {$etiqueta}:**\n\n";

        foreach ($top as $idx => $item) {
            $cant = (int) $item->total_cantidad;
            $monto = (float) $item->total_ventas;
            $labels[] = $item->producto;
            $valores[] = $monto;

            $medalla = match ($idx) {
                0 => '🥇',
                1 => '🥈',
                2 => '🥉',
                default => '•',
            };

            $mensajeBase .= "{$medalla} **{$item->producto}:** {$cant} unidades vendidas (\$".number_format($monto, 0, ',', '.')." COP)\n";
        }

        if ($top->isEmpty()) {
            $mensajeBase .= "No hay registros de ventas de ítems para este período.\n";
            $labels = ['Sin datos'];
            $valores = [0];
        }

        $grafico = [
            'tipo' => 'ranking',
            'titulo' => "Platos Estrella por Facturación — {$etiqueta}",
            'subtitulo' => 'Ranking de productos con mayor contribución',
            'etiquetas' => $labels,
            'valores' => $valores,
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores),
            'colores' => ['#e0442e', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6'],
            'unidad' => 'COP',
        ];

        $contexto = [
            'periodo' => $etiqueta,
            'top_platos' => $top->toArray(),
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Top platos más vendidos', $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'grafico' => $grafico,
                'top_productos' => $top->toArray(),
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Menú y Carta',
                'url' => '/menu',
                'icono' => 'restaurant_menu',
            ],
        ];
    }

    /**
     * Consulta de productividad y ventas por mesero.
     */
    public function ejecutarConsultaMeseros(User $usuario, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        $query = Pedido::query()
            ->join('users', 'pedidos.mesero_id', '=', 'users.id')
            ->where('pedidos.sucursal_id', $usuario->sucursal_id)
            ->where('pedidos.estado', 'pagado');

        if ($rangoFecha) {
            $query->whereBetween('pedidos.pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            $etiqueta = $rangoFecha['etiqueta'];
        } else {
            $etiqueta = 'Semana Actual';
            $query->whereBetween('pedidos.pagado_en', [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfWeek()->endOfDay()]);
        }

        $meseros = (clone $query)
            ->select('users.name as mesero', DB::raw('SUM(pedidos.total) as total_ventas'), DB::raw('COUNT(pedidos.id) as comandas'), DB::raw('SUM(COALESCE(pedidos.propina, 0)) as total_propinas'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_ventas')
            ->get();

        $labels = [];
        $valores = [];
        $mensajeBase = "👨‍🍳 **Rendimiento de Equipo de Salón / Meseros — {$etiqueta}:**\n\n";

        foreach ($meseros as $idx => $m) {
            $ventas = (float) $m->total_ventas;
            $comandas = (int) $m->comandas;
            $propinas = (float) $m->total_propinas;
            $labels[] = $m->mesero;
            $valores[] = $ventas;

            $medalla = match ($idx) {
                0 => '🌟',
                1 => '⭐',
                default => '•',
            };

            $mensajeBase .= "{$medalla} **{$m->mesero}:** \$".number_format($ventas, 0, ',', '.')." COP ({$comandas} comandas | Propinas: \$".number_format($propinas, 0, ',', '.').")\n";
        }

        if ($meseros->isEmpty()) {
            $mensajeBase .= "No se registran comandas asociadas a meseros en este período.\n";
            $labels = ['Sin datos'];
            $valores = [0];
        }

        $grafico = [
            'tipo' => 'bar',
            'titulo' => "Ventas por Mesero — {$etiqueta}",
            'subtitulo' => 'Facturación generada en salón',
            'etiquetas' => $labels,
            'valores' => $valores,
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores),
            'colores' => ['#e0442e', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'],
            'unidad' => 'COP',
        ];

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeBase,
            'datos' => [
                'grafico' => $grafico,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Módulo de Salón',
                'url' => '/pos',
                'icono' => 'table_bar',
            ],
        ];
    }

    /**
     * Gráfico general comparativo (ej. Ventas por Cajas o Días de la semana).
     */
    public function ejecutarConsultaGraficoGeneral(User $usuario, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        $cajas = Caja::where('sucursal_id', $usuario->sucursal_id)->where('activa', true)->get();
        $labels = [];
        $valores = [];

        foreach ($cajas as $caja) {
            $total = (float) Pedido::where('sucursal_id', $usuario->sucursal_id)
                ->where('estado', 'pagado')
                ->whereHas('turnoCaja', fn ($q) => $q->where('caja_id', $caja->id))
                ->sum('total');

            $labels[] = $caja->nombre;
            $valores[] = $total;
        }

        $grafico = [
            'tipo' => 'bar',
            'titulo' => 'Facturación Comparativa por Cajas',
            'subtitulo' => 'Rendimiento por terminal de cobro',
            'etiquetas' => $labels ?: ['Caja Principal'],
            'valores' => $valores ?: [0],
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores ?: [0]),
            'colores' => ['#e0442e', '#f59e0b', '#10b981', '#3b82f6'],
            'unidad' => 'COP',
        ];

        $mensaje = "📈 **Gráfico Ejecutivo de Facturación por Terminales de Cobro:**\n\n"
            .'He generado la visualización comparativa de ventas entre las distintas cajas de tu restaurante. '
            .'Revisa la gráfica interactiva a continuación:';

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensaje,
            'datos' => [
                'grafico' => $grafico,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Ver Reportes Avanzados',
                'url' => '/reportes',
                'icono' => 'monitoring',
            ],
        ];
    }

    /**
     * Generación de imagen o tarjeta infográfica ejecutiva de estadísticas.
     */
    public function ejecutarGeneracionInfografia(User $usuario, ?string $mensaje = null): array
    {
        $kpis = $this->dashboardService->kpisGenerales('hoy', $usuario->sucursal_id);
        $top = $this->dashboardService->topProductos('hoy', 3, $usuario->sucursal_id);

        $ventasFmt = number_format($kpis['ventas'], 0, ',', '.');
        $ticketFmt = number_format($kpis['ticket_promedio'], 0, ',', '.');
        $transacciones = $kpis['transacciones'];

        $mensajeRespuesta = "🖼️ **Infografía Ejecutiva Generada — RestoMaster Live Analytics:**\n\n"
            ."He compilado un informe visual de alto impacto con las estadísticas actuales del restaurante:\n\n"
            ."• 💵 **Facturación:** \${$ventasFmt} COP\n"
            ."• 🧾 **Tickets:** {$transacciones} pedidos cerrados\n"
            ."• 🎯 **Ticket Promedio:** \${$ticketFmt} COP\n\n"
            .'Puedes visualizar la tarjeta infográfica enriquecida en este panel o abrir el módulo de reportes para exportar a PDF / Excel.';

        return [
            'tipo' => 'infografia',
            'mensaje' => $mensajeRespuesta,
            'datos' => [
                'infografia' => [
                    'titulo' => 'Tablero Ejecutivo RestoMaster',
                    'subtitulo' => 'Reporte Operativo y Financiero en Vivo',
                    'fecha' => Carbon::now()->format('d/m/Y H:i'),
                    'ventas' => $kpis['ventas'],
                    'transacciones' => $transacciones,
                    'ticket_promedio' => $kpis['ticket_promedio'],
                    'top_plato' => $top[0]->producto ?? 'Sushi Clásico',
                ],
                'kpis' => $kpis,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Reportes para Exportar',
                'url' => '/reportes',
                'icono' => 'download',
            ],
        ];
    }

    /**
     * Consulta conversacional libre a Gemini cuando el mensaje es una pregunta estratégica o no estructurada.
     */
    public function ejecutarConsultaConversacionalLlm(string $mensaje, User $usuario): ?array
    {
        $config = $this->obtenerConfiguracionIa($usuario->sucursal_id);
        if (! $config || app()->environment('testing')) {
            return null;
        }

        $apiKey = $config->obtenerApiKeyIa();
        if (empty($apiKey) || str_starts_with($apiKey, 'dummy')) {
            return null;
        }

        $contexto = $this->generarContextoOperativoGeneral($usuario->sucursal_id);
        $modelo = $config->ia_modelo ?: 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        $systemInstruction = "Eres el Copiloto Ejecutivo IA de RestoMaster. Actúas con la misma calidez, inteligencia conversacional y precisión analítica de Gemini, especializado como Director de Operaciones y Analista Financiero del restaurante.\n"
            ."Tu misión es responder con empatía, cercanía ejecutiva, exactitud y tono profesional en español.\n"
            ."Usa los datos en vivo provistos en el contexto para responder preguntas sobre cajas, ventas, carta, insumos, clientes o recomendaciones operativas.\n"
            .'Utiliza formato Markdown limpio con negritas y emojis pertinentes para una lectura ágil en dispositivos móviles y de escritorio.';

        $prompt = "El Administrador pregunta: \"{$mensaje}\"\n\nContexto en vivo del restaurante en la base de datos:\n".json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $response = Http::withoutVerifying()
                ->retry(2, 300)
                ->timeout(10)
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 800,
                        'temperature' => 0.35,
                        'thinkingConfig' => ['thinkingBudget' => 0],
                    ],
                ]);

            if ($response->successful()) {
                $texto = $response->json('candidates.0.content.parts.0.text');
                if (! empty($texto)) {
                    return [
                        'tipo' => 'texto',
                        'mensaje' => trim($texto),
                        'accion_rapida' => [
                            'etiqueta' => 'Abrir Módulo de Reportes',
                            'url' => '/reportes',
                            'icono' => 'analytics',
                        ],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error en llamada conversacional libre de Copiloto a Gemini: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Consulta a Gemini para enriquecer con comentarios directivos los datos reales obtenidos de la base de datos.
     */
    protected function consultarLlmSiDisponible(string $preguntaUsuario, array $datosContexto, string $resumenBase, ?int $sucursalId = null): ?string
    {
        $config = $this->obtenerConfiguracionIa($sucursalId);
        if (! $config || app()->environment('testing')) {
            return null;
        }

        $apiKey = $config->obtenerApiKeyIa();
        if (empty($apiKey) || str_starts_with($apiKey, 'dummy')) {
            return null;
        }

        $modelo = $config->ia_modelo ?: 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        $systemInstruction = 'Eres el Copiloto Ejecutivo IA de RestoMaster. Actúas con la inteligencia analítica de Gemini para el restaurante. Comunica al Administrador los resultados con tono ejecutivo y cercano, destacando cifras clave en negrita y agregando observaciones operativas útiles. Usa Markdown limpio con emojis.';

        $prompt = "El Administrador preguntó: \"{$preguntaUsuario}\"\n\n"
            ."Datos reales extraídos de la base de datos de RestoMaster:\n"
            .json_encode($datosContexto, JSON_UNESCAPED_UNICODE)."\n\n"
            .'Instrucciones: Redacta una respuesta ejecutiva, empática y precisa que responda exactamente a su pregunta con base en estos datos reales. '
            .'Si la cifra de ventas es $0 en la fecha o caja específica solicitada, indícalo claramente con respeto y menciona qué días o turnos cercanos sí tuvieron actividad.';

        try {
            $response = Http::withoutVerifying()
                ->retry(2, 300)
                ->timeout(10)
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 800,
                        'temperature' => 0.3,
                        'thinkingConfig' => ['thinkingBudget' => 0],
                    ],
                ]);

            if ($response->successful()) {
                $texto = $response->json('candidates.0.content.parts.0.text');
                if (! empty($texto)) {
                    return trim($texto);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error consultando Gemini en AdminAiCopilotService: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Genera un volcado de contexto operativo para que el LLM tenga conocimiento global de la sucursal.
     */
    protected function generarContextoOperativoGeneral(?int $sucursalId = null): array
    {
        $kpisHoy = $this->dashboardService->kpisGenerales('hoy', $sucursalId);
        $kpisSemana = $this->dashboardService->kpisGenerales('semana', $sucursalId);
        $top = $this->dashboardService->topProductos('semana', 5, $sucursalId);
        $alertas = $this->dashboardService->insumosEnAlerta(5);
        $cajas = Caja::all(['id', 'nombre', 'codigo', 'tipo', 'activa']);
        $turnosRecientes = TurnoCaja::with('cajero')->latest('id')->take(3)->get(['id', 'caja_id', 'user_id', 'estado', 'apertura_en', 'monto_inicial']);

        return [
            'fecha_actual' => Carbon::now()->format('Y-m-d H:i (l)'),
            'cajas_en_sistema' => $cajas->toArray(),
            'total_cajas_activas' => $cajas->where('activa', true)->count(),
            'turnos_recientes' => $turnosRecientes->toArray(),
            'kpis_hoy' => $kpisHoy,
            'kpis_semana' => $kpisSemana,
            'top_productos_semana' => $top,
            'insumos_criticos_alerta' => $alertas['total_alertas'],
        ];
    }

    /**
     * Resumen de ventas de hoy con datos de gráfico y botón a reportes.
     */
    public function ejecutarResumenVentas(User $usuario): array
    {
        $kpis = $this->dashboardService->kpisGenerales('hoy', $usuario->sucursal_id);
        $topProductos = $this->dashboardService->topProductos('hoy', 5, $usuario->sucursal_id);
        $ventasPorHora = $this->dashboardService->ventasPorHora('hoy', $usuario->sucursal_id);

        $franjasGrafico = collect($ventasPorHora)
            ->filter(fn ($f) => $f['hora_num'] >= 11 && $f['hora_num'] <= 23)
            ->values()
            ->all();

        $ventasFormateadas = number_format($kpis['ventas'], 0, ',', '.');
        $ticketPromedioFormateado = number_format($kpis['ticket_promedio'], 0, ',', '.');
        $transacciones = $kpis['transacciones'];

        $mensaje = "📊 **Estadísticas de Ventas de Hoy:**\n\n"
            ."• **Ventas Totales:** \${$ventasFormateadas} COP\n"
            ."• **Comandas / Tickets:** {$transacciones} pedidos cerrados\n"
            ."• **Ticket Promedio:** \${$ticketPromedioFormateado} COP\n"
            .'• **Variación vs Período Anterior:** '.($kpis['variacion_ventas_pct'] >= 0 ? "+{$kpis['variacion_ventas_pct']}% 📈" : "{$kpis['variacion_ventas_pct']}% 📉")."\n\n";

        if (! empty($topProductos)) {
            $mensaje .= "🏆 **Platos más vendidos hoy:**\n";
            foreach (collect($topProductos)->take(3) as $idx => $p) {
                $subtotalFormateado = number_format((float) $p->total_ventas, 0, ',', '.');
                $mensaje .= ($idx + 1).". **{$p->producto}** ({$p->cantidad} uds — \${$subtotalFormateado})\n";
            }
        }

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensaje,
            'datos' => [
                'kpis' => $kpis,
                'top_productos' => $topProductos,
                'grafico_horas' => $franjasGrafico,
                'grafico' => [
                    'tipo' => 'bar',
                    'titulo' => 'Flujo de Facturación por Hora',
                    'subtitulo' => 'Franjas de servicio (11:00 — 23:00)',
                    'etiquetas' => collect($franjasGrafico)->map(fn ($f) => $f['hora'])->all(),
                    'valores' => collect($franjasGrafico)->map(fn ($f) => $f['ventas'])->all(),
                    'valores_formateados' => collect($franjasGrafico)->map(fn ($f) => '$'.number_format($f['ventas'], 0, ',', '.'))->all(),
                    'colores' => array_fill(0, count($franjasGrafico), '#e0442e'),
                    'unidad' => 'COP',
                ],
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Módulo de Reportes',
                'url' => '/reportes',
                'icono' => 'analytics',
            ],
        ];
    }

    /**
     * Auditoría de inventario crítico.
     */
    public function ejecutarAuditoriaInventario(): array
    {
        $alertas = $this->dashboardService->insumosEnAlerta(8);
        $totalAlertas = $alertas['total_alertas'];
        $costoReposicion = number_format($alertas['costo_reposicion_estimado'], 0, ',', '.');

        if ($totalAlertas === 0) {
            return [
                'tipo' => 'inventario',
                'mensaje' => "✅ **Inventario en Óptimas Condiciones:**\n\nNo se registran insumos con stock por debajo del mínimo de seguridad. Todos los ingredientes y materias primas están abastecidos adecuadamente.",
                'datos' => [
                    'total_alertas' => 0,
                    'costo_reposicion' => 0,
                    'insumos' => [],
                ],
                'accion_rapida' => [
                    'etiqueta' => 'Ver Inventario General',
                    'url' => '/inventario',
                    'icono' => 'inventory_2',
                ],
            ];
        }

        $mensaje = "⚠️ **Auditoría de Insumos Críticos ({$totalAlertas} en alerta):**\n\n"
            .'Se detectaron insumos que requieren reposición urgente para no afectar la operación de cocina y barra. '
            ."Costo estimado de reposición: **\${$costoReposicion} COP**.\n\n";

        $insumosResumen = [];
        foreach ($alertas['agotados']->take(4) as $item) {
            $mensaje .= "🔴 **AGOTADO:** {$item->nombre} (Stock: {$item->stock_actual} {$item->unidad_medida} | Mín: {$item->stock_minimo})\n";
            $insumosResumen[] = [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'stock_actual' => (float) $item->stock_actual,
                'stock_minimo' => (float) $item->stock_minimo,
                'unidad' => $item->unidad_medida,
                'estado' => 'agotado',
                'proveedor' => $item->proveedor_nombre ?? $item->proveedor?->nombre ?? 'Sin proveedor',
                'telefono' => $item->proveedor_telefono ?? $item->proveedor?->telefono ?? null,
            ];
        }

        foreach ($alertas['criticos']->take(4) as $item) {
            $mensaje .= "🟡 **BAJO STOCK:** {$item->nombre} (Stock: {$item->stock_actual} {$item->unidad_medida} | Mín: {$item->stock_minimo})\n";
            $insumosResumen[] = [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'stock_actual' => (float) $item->stock_actual,
                'stock_minimo' => (float) $item->stock_minimo,
                'unidad' => $item->unidad_medida,
                'estado' => 'critico',
                'proveedor' => $item->proveedor_nombre ?? $item->proveedor?->nombre ?? 'Sin proveedor',
                'telefono' => $item->proveedor_telefono ?? $item->proveedor?->telefono ?? null,
            ];
        }

        return [
            'tipo' => 'inventario',
            'mensaje' => $mensaje,
            'datos' => [
                'total_alertas' => $totalAlertas,
                'costo_reposicion' => $alertas['costo_reposicion_estimado'],
                'insumos' => $insumosResumen,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Ir al Módulo de Inventario',
                'url' => '/inventario',
                'icono' => 'inventory_2',
            ],
        ];
    }

    /**
     * Generación inteligente de encuesta de satisfacción según el contexto del administrador.
     */
    public function ejecutarGeneracionEncuesta(string $instruccion): array
    {
        $instruccionLower = Str::lower($instruccion);

        if (Str::contains($instruccionLower, ['delivery', 'domicilio', 'pedido a domicilio'])) {
            $nombre = 'Experiencia y Empaque en Delivery';
            $disparador = 'post_delivery';
            $delay = 1;
            $preguntas = [
                [
                    'id' => 1,
                    'tipo' => 'estrellas',
                    'texto' => '¿Cómo calificarías el tiempo de llegada y puntualidad de tu pedido?',
                    'requerida' => true,
                ],
                [
                    'id' => 2,
                    'tipo' => 'estrellas',
                    'texto' => '¿La temperatura y frescura del sushi y tus platos fue la adecuada?',
                    'requerida' => true,
                ],
                [
                    'id' => 3,
                    'tipo' => 'si_no',
                    'texto' => '¿El empaque llegó en perfecto estado, sellado y sin derrames?',
                    'requerida' => true,
                ],
                [
                    'id' => 4,
                    'tipo' => 'texto',
                    'texto' => '¿Algún comentario adicional para nuestro equipo de cocina y repartidores?',
                    'requerida' => false,
                ],
            ];
        } elseif (Str::contains($instruccionLower, ['reserva', 'mesa', 'cumpleaños', 'evento'])) {
            $nombre = 'Calidad de Servicio en Salón & Reservas';
            $disparador = 'post_reserva';
            $delay = 2;
            $preguntas = [
                [
                    'id' => 1,
                    'tipo' => 'estrellas',
                    'texto' => '¿Cómo calificarías la atención y amabilidad de tu mesero?',
                    'requerida' => true,
                ],
                [
                    'id' => 2,
                    'tipo' => 'estrellas',
                    'texto' => '¿Qué tan satisfecho quedaste con el sabor y presentación de los platos?',
                    'requerida' => true,
                ],
                [
                    'id' => 3,
                    'tipo' => 'si_no',
                    'texto' => '¿Tu mesa estuvo lista a la hora acordada en la reserva?',
                    'requerida' => true,
                ],
                [
                    'id' => 4,
                    'tipo' => 'texto',
                    'texto' => '¿Nos recomendarías con amigos o familiares para ocasiones especiales?',
                    'requerida' => false,
                ],
            ];
        } else {
            $nombre = 'Satisfacción General Gastronómica';
            $disparador = 'post_pago';
            $delay = 1;
            $preguntas = [
                [
                    'id' => 1,
                    'tipo' => 'estrellas',
                    'texto' => '¿Cómo calificarías tu experiencia general el día de hoy?',
                    'requerida' => true,
                ],
                [
                    'id' => 2,
                    'tipo' => 'estrellas',
                    'texto' => '¿Qué tan satisfecho estás con la relación precio / calidad?',
                    'requerida' => true,
                ],
                [
                    'id' => 3,
                    'tipo' => 'si_no',
                    'texto' => '¿Volverías a visitarnos próximamente?',
                    'requerida' => true,
                ],
                [
                    'id' => 4,
                    'tipo' => 'texto',
                    'texto' => '¿Qué podríamos mejorar para que tu próxima experiencia sea de 5 estrellas?',
                    'requerida' => false,
                ],
            ];
        }

        $mensaje = "📋 **He diseñado una nueva encuesta de satisfacción:**\n\n"
            ."• **Título:** {$nombre}\n"
            .'• **Disparador Automático:** '.Str::headline($disparador)." (envío a las {$delay}h del evento)\n"
            .'• **Preguntas Diseñadas:** '.count($preguntas)." preguntas optimizadas (estrellas, dicotómica y campo abierto).\n\n"
            .'¿Deseas guardarla automáticamente en la base de datos o abrir el Diseñador Visual de CRM?';

        return [
            'tipo' => 'encuesta',
            'mensaje' => $mensaje,
            'datos' => [
                'nombre' => $nombre,
                'disparador' => $disparador,
                'delay_horas' => $delay,
                'preguntas' => $preguntas,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Guardar Encuesta en CRM',
                'accion' => 'guardar_encuesta',
                'payload' => [
                    'nombre' => $nombre,
                    'disparador' => $disparador,
                    'delay_horas' => $delay,
                    'preguntas' => $preguntas,
                ],
                'url' => '/crm',
                'icono' => 'playlist_add_check',
            ],
        ];
    }

    /**
     * Generación inteligente de propuesta de promoción gastronómica.
     */
    public function ejecutarGeneracionPromocion(string $instruccion): array
    {
        $instruccionLower = Str::lower($instruccion);

        if (Str::contains($instruccionLower, ['2x1', 'dos por uno'])) {
            $nombre = 'Martes y Miércoles de 2x1 en Rolls Clásicos';
            $tipoDescuento = 'dos_por_uno';
            $valorDescuento = 50.00;
            $diasSemana = ['martes', 'miercoles'];
            $codigo = 'ROLLS2X1';
            $descripcion = 'Lleva 2 de tus rolls clásicos preferidos y paga solo uno. Válido en salón y pedidos para llevar.';
        } elseif (Str::contains($instruccionLower, ['delivery', 'domicilio', 'envio'])) {
            $nombre = 'Envío Gratis + 15% OFF en Pedidos Web';
            $tipoDescuento = 'porcentaje';
            $valorDescuento = 15.00;
            $diasSemana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            $codigo = 'DELIVERYTOP';
            $descripcion = '15% de descuento en toda la carta a domicilio y delivery sin costo para compras superiores a $50.000.';
        } else {
            $nombre = 'Noche Gourmet: 20% OFF en Omakase & Tragos de Autor';
            $tipoDescuento = 'porcentaje';
            $valorDescuento = 20.00;
            $diasSemana = ['jueves', 'viernes', 'sabado'];
            $codigo = 'GOURMET20';
            $descripcion = 'Disfruta de una experiencia sensorial inolvidable con 20% de descuento en selecciones premium.';
        }

        $mensaje = "🎁 **He redactado una propuesta de promoción gastronómica:**\n\n"
            ."• **Nombre:** {$nombre}\n"
            ."• **Cupón:** `{$codigo}`\n"
            .'• **Tipo:** '.Str::headline($tipoDescuento)." ({$valorDescuento}% de beneficio)\n"
            .'• **Días Activos:** '.implode(', ', array_map('ucfirst', $diasSemana))."\n"
            ."• **Descripción:** {$descripcion}\n\n"
            .'Puedes guardarla directamente para que aparezca en el Portal de Promociones y la Portada Web.';

        return [
            'tipo' => 'promocion',
            'mensaje' => $mensaje,
            'datos' => [
                'nombre' => $nombre,
                'codigo_cupon' => $codigo,
                'tipo_descuento' => $tipoDescuento,
                'valor_descuento' => $valorDescuento,
                'dias_semana' => $diasSemana,
                'descripcion' => $descripcion,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Guardar Promoción en el Sistema',
                'accion' => 'guardar_promocion',
                'payload' => [
                    'nombre' => $nombre,
                    'codigo_cupon' => $codigo,
                    'tipo_descuento' => $tipoDescuento,
                    'valor_descuento' => $valorDescuento,
                    'dias_semana' => $diasSemana,
                    'descripcion' => $descripcion,
                ],
                'url' => '/promociones/gestion',
                'icono' => 'local_offer',
            ],
        ];
    }

    /**
     * Guarda la encuesta generada por el Copiloto directamente en la base de datos.
     */
    public function guardarEncuesta(array $payload, ?int $sucursalId = null): Encuesta
    {
        return Encuesta::create([
            'sucursal_id' => $sucursalId,
            'nombre' => $payload['nombre'] ?? 'Encuesta Generada por Copiloto IA',
            'activa' => true,
            'disparador' => $payload['disparador'] ?? 'post_pago',
            'delay_horas' => (int) ($payload['delay_horas'] ?? 1),
            'preguntas' => $payload['preguntas'] ?? [],
        ]);
    }

    /**
     * Guarda la promoción generada por el Copiloto directamente en la base de datos.
     */
    public function guardarPromocion(array $payload, ?int $sucursalId = null): Promocion
    {
        $nombre = $payload['nombre'] ?? $payload['titulo'] ?? 'Promoción Copiloto IA';
        $slugBase = Str::slug($nombre);
        $slug = $slugBase;
        $c = 1;
        while (Promocion::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$c}";
            $c++;
        }

        return Promocion::create([
            'sucursal_id' => $sucursalId,
            'titulo' => $nombre,
            'slug' => $slug,
            'subtitulo' => 'Propuesta gourmet generada por Copiloto Ejecutivo IA',
            'descripcion' => $payload['descripcion'] ?? 'Promoción especial creada por el Copiloto Ejecutivo.',
            'terminos_condiciones' => 'Válido por tiempo limitado según disponibilidad.',
            'tipo_beneficio' => $payload['tipo_descuento'] ?? 'dos_por_uno',
            'descuento_porcentaje' => (float) ($payload['valor_descuento'] ?? 20.00),
            'fecha_inicio' => Carbon::today(),
            'fecha_fin' => Carbon::today()->addDays(30),
            'dias_semana' => $payload['dias_semana'] ?? ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
            'aplica_salon' => true,
            'aplica_delivery' => true,
            'mostrar_en_portada' => true,
            'activo' => true,
            'orden' => 10,
        ]);
    }

    protected function esConsultaMetodosPago(string $texto): bool
    {
        return (bool) preg_match('/\b(efectivo|tarjetas?|transferencias?|m[eé]todos?\s+de\s+pago|formas?\s+de\s+pago|cu[aá]nto\s+en\s+efectivo|desglose\s+de\s+pago)\b/iu', $texto);
    }

    protected function esConsultaMeseros(string $texto): bool
    {
        return (bool) preg_match('/\b(meseros?|camareros?|atenci[oó]n|rendimiento\s+meseros|ventas?\s+por\s+mesero)\b/iu', $texto);
    }

    protected function esConsultaTopProductos(string $texto): bool
    {
        return (bool) preg_match('/\b(platos?|productos?|m[aá]s\s+vendidos?|top|carta|rolls?|platos?\s+estrella)\b/iu', $texto);
    }

    protected function esConsultaGrafico(string $texto): bool
    {
        return (bool) preg_match('/\b(gr[aá]ficos?|gr[aá]fica|diagramas?|curva|visual|comparativa|estad[ií]sticas?)\b/iu', $texto);
    }

    protected function esConsultaImagenOInfografia(string $texto): bool
    {
        return (bool) preg_match('/\b(im[aá]gen(es)?|infograf[ií]a|generar\s+imagen|exportar\s+gr[aá]fico|tarjeta\s+visual)\b/iu', $texto);
    }

    protected function esIntencionVentas(string $texto): bool
    {
        if (preg_match('/\b(ventas?|facturaci[oó]n|ingresos?|tickets?|kpis?)\b/iu', $texto)) {
            return true;
        }

        return Str::contains($texto, [
            'estadistica', 'estadísticas', 'cuanto hemos vendido', 'cuánto hemos vendido',
            'como van las ventas', 'cómo van las ventas', 'grafico de ventas', 'gráfico de ventas',
        ]);
    }

    protected function esIntencionInventario(string $texto): bool
    {
        return Str::contains($texto, [
            'inventario', 'insumo', 'insumos', 'stock', 'critico', 'crítico', 'agotado',
            'que falta', 'qué falta', 'materia prima', 'reabastecer', 'compras', 'proveedor',
        ]);
    }

    protected function esIntencionEncuesta(string $texto): bool
    {
        return Str::contains($texto, [
            'encuesta', 'encuestas', 'satisfaccion', 'satisfacción', 'nps', 'preguntas',
            'diseñar encuesta', 'crear encuesta', 'calificar',
        ]);
    }

    protected function esIntencionPromocion(string $texto): bool
    {
        return Str::contains($texto, [
            'promocion', 'promoción', 'promo', 'promos', 'descuento', '2x1', 'dos por uno',
            'cupon', 'cupón', 'campaña', 'campaña de descuento',
        ]);
    }

    protected function generarOrientacionPersuasiva(): array
    {
        $mensaje = "👋 **Hola, soy el Copiloto Ejecutivo IA de RestoMaster.**\n\n"
            ."Comprendo preguntas analíticas en lenguaje humano natural con acceso total a la base de datos de tu restaurante:\n\n"
            ."• 📊 **Ventas por Caja:** *\"Ventas en caja 1\"* o *\"¿Cómo cerró la caja barra?\"*\n"
            ."• 📅 **Ventas por Período:** *\"Dame las ventas del día martes de esta semana\"* o *\"Facturación de ayer\"*\n"
            ."• 💳 **Formas de Pago:** *\"¿Cuánto se vendió en efectivo vs tarjeta?\"*\n"
            ."• 🍣 **Carta & Platos Estrella:** *\"Top 5 platos más vendidos\"*\n"
            ."• 👨‍🍳 **Personal de Salón:** *\"Rendimiento y ventas por mesero\"*\n"
            ."• ⚠️ **Control de Stock:** *\"¿Qué insumos críticos tenemos en inventario?\"*\n"
            ."• 📈 **Gráficos & Visual:** *\"Gráfico de ventas de la semana\"* o *\"Generar infografía\"*\n\n"
            .'¿Qué métrica o análisis operativo deseas consultar en este momento?';

        return [
            'tipo' => 'orientacion',
            'mensaje' => $mensaje,
        ];
    }
}
