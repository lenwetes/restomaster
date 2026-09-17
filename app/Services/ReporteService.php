<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\AsientoContable;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteService
{
    /**
     * Estado de Resultados por rango de fechas desde los asientos contables.
     */
    public function estadoResultados(string $desde, string $hasta): array
    {
        $totales = AsientoContable::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('tipo, cuenta, sum(monto) as total, count(*) as movimientos')
            ->groupBy('tipo', 'cuenta')
            ->get();

        $ingresos = $totales->where('tipo', 'ingreso');
        $gastos = $totales->where('tipo', 'gasto');

        $ventasNetas = (float) ($ingresos->firstWhere('cuenta', 'ventas_restaurante')?->total ?? 0);
        $otrosIngresos = (float) $ingresos->reject(fn ($r) => $r->cuenta === 'ventas_restaurante')->sum('total');

        $gastosOperativos = (float) ($gastos->firstWhere('cuenta', 'gastos_operativos')?->total ?? 0);
        $otrosGastos = (float) $gastos->reject(fn ($r) => $r->cuenta === 'gastos_operativos')->sum('total');

        return [
            'ingresos' => [
                'ventas_netas' => $ventasNetas,
                'otros_ingresos' => $otrosIngresos,
                'total' => round($ventasNetas + $otrosIngresos, 2),
            ],
            'gastos' => [
                'gastos_operativos' => $gastosOperativos,
                'otros_gastos' => $otrosGastos,
                'total' => round($gastosOperativos + $otrosGastos, 2),
            ],
            'resultado_neto' => round(($ventasNetas + $otrosIngresos) - ($gastosOperativos + $otrosGastos), 2),
            'detalle' => [
                'ingresos' => $ingresos
                    ->map(fn ($r) => [
                        'cuenta' => $r->cuenta,
                        'total' => (float) $r->total,
                        'movimientos' => (int) $r->movimientos,
                    ])
                    ->sortByDesc('total')
                    ->values()
                    ->all(),
                'gastos' => $gastos
                    ->map(fn ($r) => [
                        'cuenta' => $r->cuenta,
                        'total' => (float) $r->total,
                        'movimientos' => (int) $r->movimientos,
                    ])
                    ->sortByDesc('total')
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * Movimientos contables recientes (hasta 100, más recientes primero).
     */
    public function movimientosRecientes(int $limite = 100): Collection
    {
        return AsientoContable::with('usuario')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    public function kpisRealtime(?int $sucursalId = null): array
    {
        $hoy = now()->toDateString();
        $hoyInicio = $hoy.' 00:00:00';
        $hoyFin = $hoy.' 23:59:59';

        $pedidosQuery = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$hoyInicio, $hoyFin])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId));

        $ventas = (float) (clone $pedidosQuery)->sum('total');
        $transacciones = (int) (clone $pedidosQuery)->count();
        $ticketPromedio = $transacciones > 0 ? round($ventas / $transacciones, 2) : 0.0;

        $costoVendido = (float) ItemPedido::query()
            ->whereHas('pedido', fn ($q) => $q->where('estado', 'pagado')->whereBetween('pagado_en', [$hoyInicio, $hoyFin])->when($sucursalId, fn ($sq) => $sq->where('sucursal_id', $sucursalId)))
            ->join('productos', 'items_pedido.producto_id', '=', 'productos.id')
            ->sum(DB::raw('COALESCE(productos.costo, 0) * items_pedido.cantidad'));

        $foodCost = $ventas > 0 ? round($costoVendido / $ventas * 100, 1) : 0.0;

        $mesasOcupadas = Mesa::whereIn('estado', [MesaEstado::OCUPADA->value, MesaEstado::POR_LIMPIAR->value])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->count();

        $comandasActivas = Pedido::whereNotIn('estado', ['pagado', 'cancelado'])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->count();

        if (DB::getDriverName() === 'pgsql') {
            $picosPorHora = (clone $pedidosQuery)
                ->selectRaw("to_char(pagado_en AT TIME ZONE 'America/Bogota', 'HH24') as hora, count(*) as total")
                ->groupByRaw("to_char(pagado_en AT TIME ZONE 'America/Bogota', 'HH24')")
                ->orderByDesc('total')
                ->limit(6)
                ->get()
                ->mapWithKeys(fn ($r) => [((int) $r->hora) => (int) $r->total])
                ->sortKeysDesc()
                ->all();
        } else {
            $picosPorHora = (clone $pedidosQuery)
                ->selectRaw("strftime('%H', pagado_en) as hora, count(*) as total")
                ->groupByRaw("strftime('%H', pagado_en)")
                ->orderByDesc('total')
                ->limit(6)
                ->get()
                ->mapWithKeys(fn ($r) => [((int) $r->hora) => (int) $r->total])
                ->sortKeysDesc()
                ->all();
        }

        $topProductos = ItemPedido::query()
            ->whereHas('pedido', fn ($q) => $q->where('estado', 'pagado')->whereBetween('pagado_en', [$hoyInicio, $hoyFin])->when($sucursalId, fn ($sq) => $sq->where('sucursal_id', $sucursalId)))
            ->join('productos', 'items_pedido.producto_id', '=', 'productos.id')
            ->groupBy('items_pedido.producto_id', 'items_pedido.nombre_producto')
            ->selectRaw('
                items_pedido.nombre_producto as producto,
                SUM(items_pedido.cantidad) as cantidad,
                SUM(items_pedido.subtotal) as ventas,
                ROUND(SUM(items_pedido.subtotal) - SUM(COALESCE(productos.costo, 0) * items_pedido.cantidad), 2) as margen
            ')
            ->orderByDesc('ventas')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'producto' => $r->producto,
                'cantidad' => (int) $r->cantidad,
                'ventas' => (float) $r->ventas,
                'margen' => (float) $r->margen,
            ])
            ->all();

        return [
            'ventas_dia' => round($ventas, 2),
            'transacciones_dia' => $transacciones,
            'ticket_promedio' => $ticketPromedio,
            'food_cost_porcentaje' => $foodCost,
            'mesas_ocupadas' => $mesasOcupadas,
            'comandas_cocina_activas' => $comandasActivas,
            'picos_por_hora' => $picosPorHora,
            'top_productos_hoy' => $topProductos,
        ];
    }

    public function ventasPorPeriodo(string $desde, string $hasta): array
    {
        return Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->selectRaw('date(pagado_en) as dia, sum(total) as ventas, count(*) as transacciones')
            ->groupByRaw('date(pagado_en)')
            ->orderByDesc('dia')
            ->get()
            ->map(fn ($r) => [
                'fecha' => (string) $r->dia,
                'ventas' => (float) $r->ventas,
                'transacciones' => (int) $r->transacciones,
                'ticket_promedio' => $r->transacciones > 0 ? round((float) $r->ventas / (int) $r->transacciones, 2) : 0.0,
            ])
            ->values()
            ->all();
    }

    public function ventasPorTipo(string $desde, string $hasta): array
    {
        return Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->selectRaw('tipo, sum(total) as ventas, count(*) as transacciones')
            ->groupBy('tipo')
            ->orderByDesc('ventas')
            ->get()
            ->map(fn ($r) => [
                'tipo' => (string) $r->tipo,
                'ventas' => (float) $r->ventas,
                'transacciones' => (int) $r->transacciones,
            ])
            ->all();
    }

    public function ventasPorProducto(string $desde, string $hasta, int $limite = 10): array
    {
        return ItemPedido::query()
            ->selectRaw('items_pedido.producto_id, max(items_pedido.nombre_producto) as nombre_producto, sum(items_pedido.cantidad) as cantidad, sum(items_pedido.subtotal) as ventas')
            ->join('pedidos', 'items_pedido.pedido_id', '=', 'pedidos.id')
            ->where('pedidos.estado', 'pagado')
            ->whereBetween('pedidos.pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->groupBy('items_pedido.producto_id')
            ->orderByDesc('ventas')
            ->with('producto')
            ->take($limite)
            ->get()
            ->map(function ($r) {
                $ventas = (float) $r->ventas;
                $costoUnitario = (float) ($r->producto?->costo ?? 0);
                $costoTotal = round($costoUnitario * (float) $r->cantidad, 2);

                return [
                    'producto' => $r->nombre_producto,
                    'cantidad' => (int) $r->cantidad,
                    'ventas' => $ventas,
                    'costo' => $costoTotal,
                    'margen' => round($ventas - $costoTotal, 2),
                ];
            })
            ->values()
            ->all();
    }

    public function ventasPorTrabajador(string $desde, string $hasta): array
    {
        return Pedido::query()
            ->selectRaw('usuario_id, sum(total) as ventas, count(*) as transacciones')
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->groupBy('usuario_id')
            ->orderByDesc('ventas')
            ->with('usuario')
            ->get()
            ->map(fn ($r) => [
                'trabajador' => $r->usuario?->name ?? 'Sin asignar',
                'ventas' => (float) $r->ventas,
                'transacciones' => (int) $r->transacciones,
            ])
            ->values()
            ->all();
    }

    /**
     * Reporte detallado de rendimiento, propinas y facturación por mesero.
     */
    public function rendimientoMeseros(string $desde, string $hasta, ?int $meseroId = null): array
    {
        // 1. Obtener todos los meseros (rol mesero o con pedidos/mesas asignadas)
        $meserosQuery = User::whereHas('role', fn ($q) => $q->where('slug', 'mesero'))
            ->orWhereHas('pedidosAtendidos');

        if ($meseroId) {
            $meserosQuery->where('id', $meseroId);
        }

        $meseros = $meserosQuery->with(['mesasAsignadas'])->get();

        // 2. Pedidos cerrados (pagados) en el rango de fechas
        $pedidosPagados = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->where(function ($q) use ($meseroId) {
                if ($meseroId) {
                    $q->where('mesero_id', $meseroId);
                } else {
                    $q->whereNotNull('mesero_id');
                }
            })
            ->with(['mesero', 'mesa'])
            ->get();

        // 3. Comandas actualmente activas (en curso) por mesero
        $comandasActivas = Pedido::query()
            ->activos()
            ->whereNotNull('mesero_id')
            ->when($meseroId, fn ($q) => $q->where('mesero_id', $meseroId))
            ->get();

        $totalesGenerales = [
            'total_ventas_netas' => 0.0,
            'total_propinas' => 0.0,
            'total_con_propinas' => 0.0,
            'total_comandas' => 0,
            'total_mesas_activas' => Mesa::whereNotNull('mesero_id')->where('estado', MesaEstado::OCUPADA->value)->count(),
        ];

        $ranking = [];

        foreach ($meseros as $mesero) {
            $pedidosM = $pedidosPagados->where('mesero_id', $mesero->id);
            $activasM = $comandasActivas->where('mesero_id', $mesero->id);

            $ventasNetas = (float) $pedidosM->sum('total');
            $propinasRecaudadas = (float) $pedidosM->sum('propina');
            $propinasSugeridas = round($ventasNetas * 0.10, 2);
            $comandasCerradas = $pedidosM->count();
            $ticketPromedio = $comandasCerradas > 0 ? round($ventasNetas / $comandasCerradas, 2) : 0.0;
            $mesasActivas = $mesero->mesasAsignadas->where('estado', MesaEstado::OCUPADA->value)->count();
            $ventasEnCurso = (float) $activasM->sum('total');

            $efectividadPropina = $propinasSugeridas > 0 ? round(($propinasRecaudadas / $propinasSugeridas) * 100, 1) : 0.0;

            $totalesGenerales['total_ventas_netas'] += $ventasNetas;
            $totalesGenerales['total_propinas'] += $propinasRecaudadas;
            $totalesGenerales['total_con_propinas'] += ($ventasNetas + $propinasRecaudadas);
            $totalesGenerales['total_comandas'] += $comandasCerradas;

            $ranking[] = [
                'id' => $mesero->id,
                'nombre' => $mesero->name,
                'email' => $mesero->email,
                'activo' => (bool) $mesero->activo,
                'ventas_netas' => $ventasNetas,
                'propinas_recaudadas' => $propinasRecaudadas,
                'propinas_sugeridas' => $propinasSugeridas,
                'efectividad_propina' => $efectividadPropina,
                'total_con_propina' => round($ventasNetas + $propinasRecaudadas, 2),
                'comandas_cerradas' => $comandasCerradas,
                'ticket_promedio' => $ticketPromedio,
                'mesas_activas' => $mesasActivas,
                'ventas_en_curso' => $ventasEnCurso,
                'comandas_en_curso_conteo' => $activasM->count(),
            ];
        }

        // Ordenar ranking por ventas netas descendente
        usort($ranking, fn ($a, $b) => $b['ventas_netas'] <=> $a['ventas_netas']);

        $ticketPromedioGeneral = $totalesGenerales['total_comandas'] > 0
            ? round($totalesGenerales['total_ventas_netas'] / $totalesGenerales['total_comandas'], 2)
            : 0.0;

        return [
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'totales' => array_merge($totalesGenerales, [
                'ticket_promedio_general' => $ticketPromedioGeneral,
                'mesero_estrella' => $ranking[0]['nombre'] ?? 'Ninguno',
            ]),
            'meseros' => $ranking,
        ];
    }

    public function comparativaPeriodos(string $desde, string $hasta): array
    {
        $inicio = Carbon::parse($desde);
        $fin = Carbon::parse($hasta);
        $duracion = $inicio->diffInDays($fin) + 1;
        $anteriorDesde = $inicio->copy()->subDays($duracion)->toDateString();
        $anteriorHasta = $inicio->copy()->subDay()->toDateString();

        $actual = $this->resumenPeriodo($desde, $hasta);
        $anterior = $this->resumenPeriodo($anteriorDesde, $anteriorHasta);

        return [
            'periodo_actual' => $actual,
            'periodo_anterior' => $anterior,
            'variacion_ventas' => $anterior['ventas'] > 0 ? round(($actual['ventas'] - $anterior['ventas']) / $anterior['ventas'] * 100, 1) : 0.0,
            'variacion_transacciones' => $anterior['transacciones'] > 0 ? round(($actual['transacciones'] - $anterior['transacciones']) / $anterior['transacciones'] * 100, 1) : 0.0,
        ];
    }

    public function topClientes(string $desde, string $hasta, int $limite = 10): array
    {
        return Pedido::query()
            ->join('clientes', 'pedidos.cliente_id', '=', 'clientes.id')
            ->where('pedidos.estado', 'pagado')
            ->whereNotNull('pedidos.cliente_id')
            ->whereBetween('pedidos.pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->selectRaw('clientes.nombre as cliente, count(*) as visitas, sum(pedidos.total) as gastado')
            ->groupBy('clientes.id', 'clientes.nombre')
            ->orderByDesc('gastado')
            ->take($limite)
            ->get()
            ->map(fn ($r) => [
                'cliente' => $r->cliente ?? 'Anónimo',
                'visitas' => (int) $r->visitas,
                'gastado' => (float) $r->gastado,
            ])
            ->all();
    }

    public function tiemposEntrega(string $desde, string $hasta): array
    {
        $pedidos = Pedido::query()
            ->where('tipo', 'delivery')
            ->whereNotNull('hora_entrega')
            ->whereBetween('created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->get();

        if ($pedidos->isEmpty()) {
            return ['promedio_min' => 0, 'min_min' => 0, 'max_min' => 0, 'entregados' => 0];
        }

        $minutos = $pedidos->map(fn ($p) => Carbon::parse($p->created_at)->diffInMinutes(Carbon::parse($p->hora_entrega)));

        return [
            'promedio_min' => (int) round($minutos->avg()),
            'min_min' => (int) $minutos->min(),
            'max_min' => (int) $minutos->max(),
            'entregados' => $pedidos->count(),
        ];
    }

    public function resumenReservas(string $desde, string $hasta): array
    {
        $base = Reserva::whereBetween('fecha', [$desde, $hasta]);
        $total = (int) $base->count();
        $confirmadas = (int) (clone $base)->whereIn('estado', ['confirmada', 'llego', 'finalizada'])->count();
        $canceladas = (int) (clone $base)->where('estado', 'cancelada')->count();
        $noShows = (int) (clone $base)->where('estado', 'no_mostro')->count();
        $cuentas = $confirmadas + $canceladas + $noShows;

        return [
            'total' => $total,
            'confirmadas' => $confirmadas,
            'canceladas' => $canceladas,
            'no_shows' => $noShows,
            'cumplimiento_porcentaje' => $cuentas > 0 ? round($confirmadas / $cuentas * 100, 1) : 0.0,
        ];
    }

    private function resumenPeriodo(string $desde, string $hasta): array
    {
        $query = Pedido::where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59']);

        $ventas = (float) $query->sum('total');
        $transacciones = (int) $query->count();

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'ventas' => round($ventas, 2),
            'transacciones' => $transacciones,
        ];
    }
}
