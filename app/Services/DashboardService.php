<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Resuelve los rangos de fecha actual y de comparación según el selector del dashboard.
     *
     * @return array{inicio: Carbon, fin: Carbon, inicio_ant: Carbon, fin_ant: Carbon, etiqueta: string}
     */
    public function resolverRango(string $periodo = 'hoy'): array
    {
        return match ($periodo) {
            'ayer' => [
                'inicio' => now()->subDay()->startOfDay(),
                'fin' => now()->subDay()->endOfDay(),
                'inicio_ant' => now()->subDays(2)->startOfDay(),
                'fin_ant' => now()->subDays(2)->endOfDay(),
                'etiqueta' => 'Ayer',
            ],
            'semana' => [
                'inicio' => now()->startOfWeek()->startOfDay(),
                'fin' => now()->endOfWeek()->endOfDay(),
                'inicio_ant' => now()->subWeek()->startOfWeek()->startOfDay(),
                'fin_ant' => now()->subWeek()->endOfWeek()->endOfDay(),
                'etiqueta' => 'Esta Semana',
            ],
            'mes' => [
                'inicio' => now()->startOfMonth()->startOfDay(),
                'fin' => now()->endOfMonth()->endOfDay(),
                'inicio_ant' => now()->subMonth()->startOfMonth()->startOfDay(),
                'fin_ant' => now()->subMonth()->endOfMonth()->endOfDay(),
                'etiqueta' => 'Este Mes',
            ],
            default => [
                'inicio' => now()->startOfDay(),
                'fin' => now()->endOfDay(),
                'inicio_ant' => now()->subDay()->startOfDay(),
                'fin_ant' => now()->subDay()->endOfDay(),
                'etiqueta' => 'Hoy',
            ],
        };
    }

    /**
     * KPIs ejecutivos principales con comparativa de crecimiento contra el período previo.
     */
    public function kpisGenerales(string $periodo = 'hoy', ?int $sucursalId = null): array
    {
        $rango = $this->resolverRango($periodo);

        // Período actual
        $queryActual = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$rango['inicio'], $rango['fin']])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId));

        $ventas = (float) (clone $queryActual)->sum('total');
        $transacciones = (int) (clone $queryActual)->count();
        $ticketPromedio = $transacciones > 0 ? round($ventas / $transacciones, 2) : 0.0;

        // Food Cost del período actual
        $costoVendido = (float) ItemPedido::query()
            ->whereHas('pedido', fn ($q) => $q->where('estado', 'pagado')->whereBetween('pagado_en', [$rango['inicio'], $rango['fin']])->when($sucursalId, fn ($sq) => $sq->where('sucursal_id', $sucursalId)))
            ->join('productos', 'items_pedido.producto_id', '=', 'productos.id')
            ->sum(DB::raw('COALESCE(productos.costo, 0) * items_pedido.cantidad'));

        $foodCostPct = $ventas > 0 ? round(($costoVendido / $ventas) * 100, 1) : 0.0;
        $margenBruto = max(0, $ventas - $costoVendido);
        $margenBrutoPct = $ventas > 0 ? round(($margenBruto / $ventas) * 100, 1) : 0.0;

        // Período anterior (para comparativas porcentuales de variación)
        $queryAnterior = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$rango['inicio_ant'], $rango['fin_ant']])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId));

        $ventasAnt = (float) (clone $queryAnterior)->sum('total');
        $transaccionesAnt = (int) (clone $queryAnterior)->count();

        $varVentasPct = $ventasAnt > 0
            ? round((($ventas - $ventasAnt) / $ventasAnt) * 100, 1)
            : ($ventas > 0 ? 100.0 : 0.0);

        $varTransaccionesPct = $transaccionesAnt > 0
            ? round((($transacciones - $transaccionesAnt) / $transaccionesAnt) * 100, 1)
            : ($transacciones > 0 ? 100.0 : 0.0);

        return [
            'periodo_etiqueta' => $rango['etiqueta'],
            'ventas' => $ventas,
            'ventas_anterior' => $ventasAnt,
            'variacion_ventas_pct' => $varVentasPct,
            'transacciones' => $transacciones,
            'transacciones_anterior' => $transaccionesAnt,
            'variacion_transacciones_pct' => $varTransaccionesPct,
            'ticket_promedio' => $ticketPromedio,
            'costo_vendido' => $costoVendido,
            'food_cost_pct' => $foodCostPct,
            'margen_bruto' => $margenBruto,
            'margen_bruto_pct' => $margenBrutoPct,
        ];
    }

    /**
     * Curva de ventas y transacciones por hora (flujo de horas punta: almuerzo, tarde, cena).
     *
     * @return array<int, array{hora: string, ventas: float, transacciones: int, pct_altura: int}>
     */
    public function ventasPorHora(string $periodo = 'hoy', ?int $sucursalId = null): array
    {
        $rango = $this->resolverRango($periodo);

        $pedidos = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$rango['inicio'], $rango['fin']])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['pagado_en', 'total']);

        // Franjas horarias operativas de 00:00 a 23:00 (incluye madrugada: bares venden pasada la medianoche)
        $franjas = [];
        for ($h = 0; $h <= 23; $h++) {
            $franjas[$h] = [
                'hora' => sprintf('%02d:00', $h),
                'hora_num' => $h,
                'ventas' => 0.0,
                'transacciones' => 0,
            ];
        }

        foreach ($pedidos as $p) {
            if ($p->pagado_en) {
                $h = (int) Carbon::parse($p->pagado_en)->format('H');
                if (isset($franjas[$h])) {
                    $franjas[$h]['ventas'] += (float) $p->total;
                    $franjas[$h]['transacciones']++;
                }
            }
        }

        $maxVenta = max(1.0, (float) collect($franjas)->max('ventas'));

        return array_map(function ($f) use ($maxVenta) {
            $f['pct_altura'] = (int) round(($f['ventas'] / $maxVenta) * 100);

            return $f;
        }, array_values($franjas));
    }

    /**
     * Ventas de los últimos 7 días con promedio diario y día de mayor facturación.
     *
     * @return array{dias: array<int, array{fecha: string, dia_nombre: string, ventas: float, transacciones: int, pct_altura: int}>, total_semana: float, promedio_diario: float, dia_pico: string}
     */
    public function tendenciaUltimos7Dias(?int $sucursalId = null): array
    {
        $hace7Dias = now()->subDays(6)->startOfDay();
        $hoyFin = now()->endOfDay();

        $pedidos = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$hace7Dias, $hoyFin])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['pagado_en', 'total']);

        $dias = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->toDateString();
            $dias[$key] = [
                'fecha' => $key,
                'dia_nombre' => ucfirst($date->locale('es')->isoFormat('ddd D')),
                'ventas' => 0.0,
                'transacciones' => 0,
            ];
        }

        foreach ($pedidos as $p) {
            if ($p->pagado_en) {
                $k = Carbon::parse($p->pagado_en)->toDateString();
                if (isset($dias[$k])) {
                    $dias[$k]['ventas'] += (float) $p->total;
                    $dias[$k]['transacciones']++;
                }
            }
        }

        $totalSemana = (float) collect($dias)->sum('ventas');
        $promedioDiario = round($totalSemana / 7, 2);
        $maxVenta = max(1.0, (float) collect($dias)->max('ventas'));
        $diaPico = collect($dias)->sortByDesc('ventas')->first()['dia_nombre'] ?? 'N/A';

        $diasList = array_map(function ($d) use ($maxVenta) {
            $d['pct_altura'] = (int) round(($d['ventas'] / $maxVenta) * 100);

            return $d;
        }, array_values($dias));

        return [
            'dias' => $diasList,
            'total_semana' => $totalSemana,
            'promedio_diario' => $promedioDiario,
            'dia_pico' => $diaPico,
        ];
    }

    /**
     * Mix de ventas por canales (Mesa, Delivery, Para Llevar) y métodos de pago (Efectivo, Tarjeta, Transferencia).
     */
    public function mixCanalesYMetodos(string $periodo = 'hoy', ?int $sucursalId = null): array
    {
        $rango = $this->resolverRango($periodo);

        $pedidos = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$rango['inicio'], $rango['fin']])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['tipo', 'metodo_pago', 'total']);

        $total = max(1.0, (float) $pedidos->sum('total'));

        // Canales
        $canales = [
            'mesa' => ['nombre' => 'Salón & Mesas', 'icono' => 'table_restaurant', 'color' => 'bg-rose-500', 'total' => 0.0, 'pedidos' => 0],
            'delivery' => ['nombre' => 'Delivery a Domicilio', 'icono' => 'two_wheeler', 'color' => 'bg-emerald-500', 'total' => 0.0, 'pedidos' => 0],
            'para_llevar' => ['nombre' => 'Para Llevar / Takeout', 'icono' => 'shopping_bag', 'color' => 'bg-amber-500', 'total' => 0.0, 'pedidos' => 0],
        ];

        // Métodos de pago
        $metodos = [
            'efectivo' => ['nombre' => 'Efectivo', 'icono' => 'payments', 'color' => 'bg-emerald-600', 'total' => 0.0],
            'tarjeta' => ['nombre' => 'Datáfono / Tarjeta', 'icono' => 'credit_card', 'color' => 'bg-sky-600', 'total' => 0.0],
            'transferencia' => ['nombre' => 'Transferencia / QR', 'icono' => 'qr_code_2', 'color' => 'bg-purple-600', 'total' => 0.0],
            'otros' => ['nombre' => 'Otros', 'icono' => 'account_balance', 'color' => 'bg-stone-500', 'total' => 0.0],
        ];

        foreach ($pedidos as $p) {
            $tipoKey = in_array($p->tipo, ['delivery', 'para_llevar'], true) ? $p->tipo : 'mesa';
            $canales[$tipoKey]['total'] += (float) $p->total;
            $canales[$tipoKey]['pedidos']++;

            $mpKey = match ($p->metodo_pago) {
                'efectivo' => 'efectivo',
                'tarjeta', 'tarjeta_debito', 'tarjeta_credito' => 'tarjeta',
                'transferencia', 'nequi', 'daviplata' => 'transferencia',
                default => 'otros',
            };
            $metodos[$mpKey]['total'] += (float) $p->total;
        }

        foreach ($canales as $k => $c) {
            $canales[$k]['pct'] = round(($c['total'] / $total) * 100, 1);
        }

        foreach ($metodos as $k => $m) {
            $metodos[$k]['pct'] = round(($m['total'] / $total) * 100, 1);
        }

        return [
            'canales' => $canales,
            'metodos' => $metodos,
            'total_general' => $total,
        ];
    }

    /**
     * Insumos críticos y agotados (Alerta primaria de inventario en tiempo real).
     *
     * @return array{agotados: Collection, criticos: Collection, total_alertas: int, costo_reposicion_estimado: float}
     */
    public function insumosEnAlerta(int $limite = 10): array
    {
        $insumos = Insumo::query()
            ->where('activo', true)
            ->where(function ($q) {
                $q->where('stock_actual', '<=', 0)
                    ->orWhereColumn('stock_actual', '<=', 'stock_minimo');
            })
            ->with('proveedor')
            ->orderByRaw('CASE WHEN stock_actual <= 0 THEN 0 ELSE 1 END')
            ->orderBy('stock_actual')
            ->limit($limite)
            ->get();

        $agotados = $insumos->where('stock_actual', '<=', 0)->values();
        $criticos = $insumos->where('stock_actual', '>', 0)->values();

        // Estimación de costo de reposición para llevar el stock actual a la capacidad óptima/mínima
        $costoReposicion = (float) $insumos->sum(function ($ins) {
            $déficit = max(0, ((float) $ins->stock_minimo * 1.5) - (float) $ins->stock_actual);

            return $déficit * (float) $ins->costo_unitario;
        });

        return [
            'agotados' => $agotados,
            'criticos' => $criticos,
            'total_alertas' => $insumos->count(),
            'costo_reposicion_estimado' => round($costoReposicion, 2),
        ];
    }

    /**
     * Top de platos más vendidos con volumen, ingresos generados y margen.
     */
    public function topProductos(string $periodo = 'hoy', int $limite = 5, ?int $sucursalId = null): array
    {
        $rango = $this->resolverRango($periodo);

        $items = ItemPedido::query()
            ->whereHas('pedido', fn ($q) => $q->where('estado', 'pagado')
                ->whereBetween('pagado_en', [$rango['inicio'], $rango['fin']])
                ->when($sucursalId, fn ($sq) => $sq->where('sucursal_id', $sucursalId)))
            ->join('productos', 'items_pedido.producto_id', '=', 'productos.id')
            ->groupBy('items_pedido.producto_id', 'items_pedido.nombre_producto', 'productos.area_cocina')
            ->selectRaw('
                items_pedido.producto_id,
                items_pedido.nombre_producto as producto,
                productos.area_cocina,
                SUM(items_pedido.cantidad) as cantidad,
                SUM(items_pedido.subtotal) as total_ventas,
                ROUND(SUM(items_pedido.subtotal) - SUM(COALESCE(productos.costo, 0) * items_pedido.cantidad), 2) as margen_total
            ')
            ->orderByDesc('total_ventas')
            ->limit($limite)
            ->get();

        $totalTopVentas = max(1.0, (float) $items->sum('total_ventas'));

        return $items->map(function ($it, $idx) use ($totalTopVentas) {
            return [
                'posicion' => $idx + 1,
                'producto' => $it->producto,
                'area_cocina' => $it->area_cocina ?? 'cocina',
                'cantidad' => (int) $it->cantidad,
                'total_ventas' => (float) $it->total_ventas,
                'margen_total' => (float) $it->margen_total,
                'pct_aporte' => round(((float) $it->total_ventas / $totalTopVentas) * 100, 1),
            ];
        })->all();
    }

    /**
     * Pulso operativo en tiempo real: Caja activa, Cocina KDS demorada, Mesas y Reservas del día.
     */
    public function pulsoOperativo(?int $sucursalId = null): array
    {
        // 1. Turno de Caja actual
        $turnoActivo = TurnoCaja::query()
            ->where('estado', 'abierto')
            ->with(['caja', 'cajero'])
            ->latest('apertura_en')
            ->first();

        // 2. Cocina KDS en vivo
        $comandasActivas = Pedido::query()
            ->whereNotIn('estado', ['pagado', 'cancelado'])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['id', 'codigo', 'estado', 'created_at', 'total']);

        $comandasDemoradas = $comandasActivas->filter(function ($c) {
            return $c->created_at && abs(now()->diffInMinutes($c->created_at)) > 20;
        })->count();

        // 3. Mesas y Aforo en sala
        $mesas = Mesa::query()
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['id', 'estado', 'capacidad']);

        $totalMesas = max(1, $mesas->count());
        $mesasOcupadas = $mesas->filter(function ($m) {
            $est = $m->estado instanceof MesaEstado ? $m->estado->value : (string) $m->estado;

            return $est === MesaEstado::OCUPADA->value;
        })->count();
        $pctAforo = (int) round(($mesasOcupadas / $totalMesas) * 100);
        $comensalesEnSala = $mesas->filter(function ($m) {
            $est = $m->estado instanceof MesaEstado ? $m->estado->value : (string) $m->estado;

            return $est === MesaEstado::OCUPADA->value;
        })->sum('capacidad');

        // 4. Reservas de hoy
        $reservasHoy = Reserva::query()
            ->whereDate('fecha', today())
            ->whereIn('estado', ['solicitada', 'confirmada'])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->orderBy('hora_llegada')
            ->limit(5)
            ->get(['id', 'nombre_contacto', 'hora_llegada', 'personas', 'estado']);

        // 5. Deliveries activos en ruta
        $deliveriesEnRuta = Pedido::query()
            ->where('tipo', 'delivery')
            ->whereIn('estado_delivery', ['en_camino', 'despachado', 'asignado'])
            ->whereNotIn('estado', ['pagado', 'cancelado'])
            ->count();

        return [
            'caja' => [
                'hay_turno_abierto' => $turnoActivo !== null,
                'caja_nombre' => $turnoActivo?->caja?->nombre ?? 'Caja Principal',
                'cajero_nombre' => $turnoActivo?->cajero?->name ?? 'Sin cajero',
                'apertura_en' => $turnoActivo?->apertura_en?->format('H:i') ?? '--:--',
                'monto_inicial' => (float) ($turnoActivo?->monto_inicial ?? 0),
                'ventas_efectivo' => (float) ($turnoActivo?->total_ventas_efectivo ?? 0),
                'ventas_digital' => (float) (($turnoActivo?->total_ventas_tarjeta ?? 0) + ($turnoActivo?->total_ventas_transferencia ?? 0)),
                'efectivo_esperado' => (float) ($turnoActivo?->monto_esperado_efectivo ?? 0),
            ],
            'kds' => [
                'total_activas' => $comandasActivas->count(),
                'demoradas' => $comandasDemoradas,
                'alerta_demora' => $comandasDemoradas > 0,
            ],
            'salon' => [
                'total_mesas' => $totalMesas,
                'mesas_ocupadas' => $mesasOcupadas,
                'pct_aforo' => $pctAforo,
                'comensales_en_sala' => $comensalesEnSala,
            ],
            'reservas_hoy' => $reservasHoy,
            'deliveries_en_ruta' => $deliveriesEnRuta,
        ];
    }

    /**
     * Ranking de ventas, productividad y propinas por mesero / personal en sala.
     */
    public function rankingMeseros(string $periodo = 'hoy', ?int $sucursalId = null): array
    {
        $rango = $this->resolverRango($periodo);

        $meseros = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'mesero'))
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->with(['mesasAsignadas'])
            ->get();

        $pedidosPagados = Pedido::query()
            ->where('estado', 'pagado')
            ->where(function ($q) {
                $q->whereNotNull('mesero_id')->orWhereNotNull('usuario_id');
            })
            ->whereBetween('pagado_en', [$rango['inicio'], $rango['fin']])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['id', 'mesero_id', 'usuario_id', 'total', 'propina']);

        $totalVentasGeneral = max(1.0, (float) $pedidosPagados->sum('total'));

        $ranking = $meseros->map(function ($mesero) use ($pedidosPagados, $totalVentasGeneral) {
            $pedidosM = $pedidosPagados->filter(fn ($p) => $p->mesero_id === $mesero->id || ($p->mesero_id === null && $p->usuario_id === $mesero->id));
            $totalVentas = (float) $pedidosM->sum('total');
            $totalPedidos = $pedidosM->count();
            $totalPropinas = (float) $pedidosM->sum('propina');
            $ticketPromedio = $totalPedidos > 0 ? round($totalVentas / $totalPedidos, 2) : 0.0;
            $mesasActivas = $mesero->mesasAsignadas->filter(function ($m) {
                $est = $m->estado instanceof MesaEstado ? $m->estado->value : (string) $m->estado;

                return $est === MesaEstado::OCUPADA->value;
            })->count();
            $pctAporte = round(($totalVentas / $totalVentasGeneral) * 100, 1);

            return [
                'id' => $mesero->id,
                'nombre' => $mesero->name,
                'email' => $mesero->email,
                'activo' => (bool) $mesero->activo,
                'mesas_activas' => $mesasActivas,
                'total_pedidos' => $totalPedidos,
                'total_ventas' => $totalVentas,
                'ticket_promedio' => $ticketPromedio,
                'total_propinas' => $totalPropinas,
                'pct_aporte' => $pctAporte,
            ];
        })->sortByDesc('total_ventas')->values();

        $rankingConPodio = $ranking->map(function ($item, $idx) {
            $item['posicion'] = $idx + 1;

            return $item;
        })->all();

        $promedioVenta = count($rankingConPodio) > 0
            ? round(collect($rankingConPodio)->avg('total_ventas'), 2)
            : 0.0;

        return [
            'meseros' => $rankingConPodio,
            'total_meseros' => count($rankingConPodio),
            'promedio_venta' => $promedioVenta,
            'total_propinas' => (float) collect($rankingConPodio)->sum('total_propinas'),
            'total_pedidos' => (int) collect($rankingConPodio)->sum('total_pedidos'),
        ];
    }
}
