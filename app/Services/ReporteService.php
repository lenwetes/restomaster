<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\AsientoContable;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

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

    public function kpisRealtime(): array
    {
        $hoy = now()->toDateString();

        $pedidosHoy = Pedido::with(['items.producto'])
            ->where('estado', 'pagado')
            ->whereDate('pagado_en', $hoy)
            ->get();

        $ventas = (float) $pedidosHoy->sum('total');
        $transacciones = $pedidosHoy->count();
        $ticketPromedio = $transacciones > 0 ? round($ventas / $transacciones, 2) : 0.0;

        $costoVendido = $pedidosHoy->flatMap->items->sum(fn ($item) => (float) ($item->producto?->costo ?? 0) * $item->cantidad);
        $foodCost = $ventas > 0 ? round($costoVendido / $ventas * 100, 1) : 0.0;

        $mesasOcupadas = Mesa::whereIn('estado', [MesaEstado::OCUPADA->value, MesaEstado::POR_LIMPIAR->value])->count();

        $comandasActivas = Pedido::whereNotIn('estado', ['pagado', 'cancelado'])->count();

        $picosPorHora = $pedidosHoy
            ->groupBy(fn ($p) => Carbon::parse($p->pagado_en)->format('H'))
            ->map->count()
            ->sortKeysDesc()
            ->take(6);

        $topProductos = ItemPedido::query()
            ->whereHas('pedido', fn ($q) => $q->where('estado', 'pagado')->whereDate('pagado_en', $hoy))
            ->with('producto')
            ->get()
            ->groupBy('producto_id')
            ->map(fn ($items) => [
                'producto' => $items->first()->nombre_producto,
                'cantidad' => $items->sum('cantidad'),
                'ventas' => (float) $items->sum('subtotal'),
                'margen' => round((float) $items->sum('subtotal') - (float) $items->sum(fn ($i) => (float) ($i->producto?->costo ?? 0) * $i->cantidad), 2),
            ])
            ->sortByDesc('ventas')
            ->take(5)
            ->values()
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
            ->get(['id', 'total', 'pagado_en'])
            ->groupBy(fn ($p) => Carbon::parse($p->pagado_en)->toDateString())
            ->map(fn ($grupo) => [
                'fecha' => $grupo->first()->pagado_en->toDateString(),
                'ventas' => (float) $grupo->sum('total'),
                'transacciones' => $grupo->count(),
                'ticket_promedio' => round((float) $grupo->sum('total') / $grupo->count(), 2),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    public function ventasPorTipo(string $desde, string $hasta): array
    {
        return Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->get(['id', 'tipo', 'total'])
            ->groupBy('tipo')
            ->map(fn ($grupo) => [
                'tipo' => $grupo->first()->tipo,
                'ventas' => (float) $grupo->sum('total'),
                'transacciones' => $grupo->count(),
            ])
            ->sortByDesc('ventas')
            ->values()
            ->all();
    }

    public function ventasPorProducto(string $desde, string $hasta, int $limite = 10): array
    {
        return ItemPedido::query()
            ->with('producto')
            ->whereHas('pedido', fn ($q) => $q->where('estado', 'pagado')->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59']))
            ->get()
            ->groupBy('producto_id')
            ->map(fn ($items) => [
                'producto' => $items->first()->nombre_producto,
                'cantidad' => $items->sum('cantidad'),
                'ventas' => (float) $items->sum('subtotal'),
                'costo' => (float) $items->sum(fn ($i) => (float) ($i->producto?->costo ?? 0) * $i->cantidad),
            ])
            ->map(fn ($fila) => $fila + ['margen' => round($fila['ventas'] - $fila['costo'], 2)])
            ->sortByDesc('ventas')
            ->take($limite)
            ->values()
            ->all();
    }

    public function ventasPorTrabajador(string $desde, string $hasta): array
    {
        return Pedido::with('usuario')
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->get()
            ->groupBy('usuario_id')
            ->map(fn ($grupo) => [
                'trabajador' => $grupo->first()->usuario?->name ?? 'Sin asignar',
                'ventas' => (float) $grupo->sum('total'),
                'transacciones' => $grupo->count(),
            ])
            ->sortByDesc('ventas')
            ->values()
            ->all();
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
        return Pedido::with('cliente')
            ->where('estado', 'pagado')
            ->whereNotNull('cliente_id')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->get()
            ->groupBy('cliente_id')
            ->map(fn ($grupo) => [
                'cliente' => $grupo->first()->cliente?->nombre ?? 'Anónimo',
                'visitas' => $grupo->count(),
                'gastado' => (float) $grupo->sum('total'),
            ])
            ->sortByDesc('gastado')
            ->take($limite)
            ->values()
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
        $reservas = Reserva::whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)->get();
        $confirmadas = $reservas->whereIn('estado', ['confirmada', 'llego', 'finalizada'])->count();
        $canceladas = $reservas->where('estado', 'cancelada')->count();
        $noShows = $reservas->where('estado', 'no_mostro')->count();
        $cuentas = $confirmadas + $canceladas + $noShows;

        return [
            'total' => $reservas->count(),
            'confirmadas' => $confirmadas,
            'canceladas' => $canceladas,
            'no_shows' => $noShows,
            'cumplimiento_porcentaje' => $cuentas > 0 ? round($confirmadas / $cuentas * 100, 1) : 0.0,
        ];
    }

    private function resumenPeriodo(string $desde, string $hasta): array
    {
        $pedidos = Pedido::where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->get(['total']);

        $ventas = (float) $pedidos->sum('total');

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'ventas' => round($ventas, 2),
            'transacciones' => $pedidos->count(),
        ];
    }
}
