<?php

namespace App\Services;

use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\PedidoDevolucion;
use Carbon\Carbon;

class ReportesComparativosService
{
    /**
     * Agrupación automática según la longitud del rango.
     */
    public function resolverAgrupacion(string $desde, string $hasta): string
    {
        $dias = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;

        return match (true) {
            $dias <= 7 => 'dia',
            $dias <= 31 => 'semana',
            $dias <= 90 => 'quincena',
            default => 'mes',
        };
    }

    /**
     * Periodo anterior automático de igual duración, pegado al inicio de A.
     *
     * @return array{0: string, 1: string} [desdeB, hastaB]
     */
    public function periodoAnteriorAutomatico(string $desde, string $hasta): array
    {
        $inicio = Carbon::parse($desde);
        $duracion = $inicio->diffInDays(Carbon::parse($hasta)) + 1;
        $hastaB = $inicio->copy()->subDay();
        $desdeB = $hastaB->copy()->subDays($duracion - 1);

        return [$desdeB->toDateString(), $hastaB->toDateString()];
    }

    /**
     * Serie agregada del periodo con la agrupación automática.
     */
    public function seriePorPeriodo(string $desde, string $hasta, ?int $sucursalId = null): array
    {
        $agrupacion = $this->resolverAgrupacion($desde, $hasta);
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->endOfDay();

        $pedidos = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$inicio, $fin])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['total', 'pagado_en']);

        $buckets = $this->construirBuckets($inicio, $fin, $agrupacion);

        foreach ($pedidos as $pedido) {
            $clave = $this->claveBucket(Carbon::parse($pedido->pagado_en), $inicio, $agrupacion);
            if (isset($buckets[$clave])) {
                $buckets[$clave]['ventas'] += (float) $pedido->total;
                $buckets[$clave]['transacciones']++;
            }
        }

        $etiquetas = [];
        $ventas = [];
        $transacciones = [];
        foreach ($buckets as $bucket) {
            $etiquetas[] = $bucket['etiqueta'];
            $ventas[] = round($bucket['ventas'], 2);
            $transacciones[] = $bucket['transacciones'];
        }

        return [
            'agrupacion' => $agrupacion,
            'etiquetas' => $etiquetas,
            'ventas' => $ventas,
            'transacciones' => $transacciones,
            'acumulada' => $this->acumulada($ventas),
            'total' => round(array_sum($ventas), 2),
        ];
    }

    /**
     * KPIs de un periodo: ventas, comandas, ticket y devoluciones.
     */
    public function kpisPeriodo(string $desde, string $hasta, ?int $sucursalId = null): array
    {
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->endOfDay();

        $pedidos = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$inicio, $fin])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['id', 'total']);

        $ventas = (float) $pedidos->sum('total');
        $comandas = $pedidos->count();

        $devoluciones = (float) PedidoDevolucion::query()
            ->whereHas('pedido', fn ($q) => $q
                ->where('estado', 'pagado')
                ->whereBetween('pagado_en', [$inicio, $fin])
                ->when($sucursalId, fn ($sq) => $sq->where('sucursal_id', $sucursalId)))
            ->sum('monto_devuelto');

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'ventas' => round($ventas, 2),
            'comandas' => $comandas,
            'ticket_promedio' => $comandas > 0 ? round($ventas / $comandas, 2) : 0.0,
            'devoluciones' => round($devoluciones, 2),
        ];
    }

    /**
     * Comparativa A vs B con KPIs diferenciales.
     */
    public function comparar(string $desdeA, string $hastaA, string $desdeB, string $hastaB, ?int $sucursalId = null): array
    {
        $a = $this->kpisPeriodo($desdeA, $hastaA, $sucursalId);
        $b = $this->kpisPeriodo($desdeB, $hastaB, $sucursalId);

        return [
            'a' => $a,
            'b' => $b,
            'delta_ventas_pct' => $this->variacion($a['ventas'], $b['ventas']),
            'delta_comandas_pct' => $this->variacion($a['comandas'], $b['comandas']),
            'delta_ticket' => round($a['ticket_promedio'] - $b['ticket_promedio'], 2),
            'delta_devoluciones_pct' => $this->variacion($a['devoluciones'], $b['devoluciones']),
        ];
    }

    /**
     * Heatmap ventas por día de semana (Lun=0) × hora (0-23).
     */
    public function heatmapVentas(string $desde, string $hasta, ?int $sucursalId = null): array
    {
        $matriz = array_fill(0, 7, array_fill(0, 24, 0.0));

        $pedidos = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [Carbon::parse($desde)->startOfDay(), Carbon::parse($hasta)->endOfDay()])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get(['total', 'pagado_en']);

        foreach ($pedidos as $pedido) {
            $fecha = Carbon::parse($pedido->pagado_en);
            $matriz[$fecha->dayOfWeekIso - 1][(int) $fecha->hour] += (float) $pedido->total;
        }

        $maximo = 0.0;
        foreach ($matriz as $fila) {
            $maximo = max($maximo, ...array_values($fila));
        }

        return [
            'matriz' => $matriz,
            'maximo' => round($maximo, 2),
            'dias' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        ];
    }

    /**
     * Top de productos del periodo por facturación.
     */
    public function topPeriodo(string $desde, string $hasta, int $limite = 5, ?int $sucursalId = null): array
    {
        $limite = max(1, min(20, $limite));

        return ItemPedido::query()
            ->join('pedidos', 'items_pedido.pedido_id', '=', 'pedidos.id')
            ->where('pedidos.estado', 'pagado')
            ->whereBetween('pedidos.pagado_en', [Carbon::parse($desde)->startOfDay(), Carbon::parse($hasta)->endOfDay()])
            ->when($sucursalId, fn ($q) => $q->where('pedidos.sucursal_id', $sucursalId))
            ->select('items_pedido.nombre_producto as producto')
            ->selectRaw('SUM(items_pedido.cantidad) as cantidad, SUM(items_pedido.subtotal) as total_ventas')
            ->groupBy('items_pedido.producto_id', 'items_pedido.nombre_producto')
            ->orderByDesc('total_ventas')
            ->limit($limite)
            ->get()
            ->map(fn ($r) => [
                'producto' => $r->producto,
                'cantidad' => (int) $r->cantidad,
                'total_ventas' => (float) $r->total_ventas,
            ])
            ->all();
    }

    /**
     * Serie acumulativa día a día.
     */
    public function acumulada(array $ventas): array
    {
        $acumulado = 0.0;
        $serie = [];
        foreach ($ventas as $v) {
            $acumulado += (float) $v;
            $serie[] = round($acumulado, 2);
        }

        return $serie;
    }

    /**
     * Exporta la comparativa en CSV (con BOM y escape anti-inyección).
     */
    public function exportarCsv(array $comparativa): string
    {
        $filas = [
            ['Período', 'Desde', 'Hasta', 'Ventas', 'Comandas', 'Ticket Promedio', 'Devoluciones'],
            [
                'A (actual)', $comparativa['a']['desde'], $comparativa['a']['hasta'],
                $comparativa['a']['ventas'], $comparativa['a']['comandas'],
                $comparativa['a']['ticket_promedio'], $comparativa['a']['devoluciones'],
            ],
            [
                'B (comparación)', $comparativa['b']['desde'], $comparativa['b']['hasta'],
                $comparativa['b']['ventas'], $comparativa['b']['comandas'],
                $comparativa['b']['ticket_promedio'], $comparativa['b']['devoluciones'],
            ],
            [
                'Δ% (A vs B)', '', '',
                $comparativa['delta_ventas_pct'].'%', $comparativa['delta_comandas_pct'].'%',
                $comparativa['delta_ticket'], $comparativa['delta_devoluciones_pct'].'%',
            ],
        ];

        $csv = "\xEF\xBB\xBF";
        foreach ($filas as $fila) {
            $csv .= implode(';', array_map(function ($v) {
                $str = (string) $v;
                if ($str !== '' && in_array($str[0], ['=', '+', '-', '@'], true)) {
                    $str = "'".$str;
                }

                return '"'.str_replace('"', '""', $str).'"';
            }, $fila))."\r\n";
        }

        return $csv;
    }

    protected function variacion(float $actual, float $base): float
    {
        if ($base == 0.0) {
            return $actual > 0 ? 100.0 : 0.0;
        }

        return round(($actual - $base) / $base * 100, 1);
    }

    /**
     * @return array<string, array{etiqueta: string, ventas: float, transacciones: int}>
     */
    protected function construirBuckets(Carbon $inicio, Carbon $fin, string $agrupacion): array
    {
        $buckets = [];

        if ($agrupacion === 'mes') {
            $cursor = $inicio->copy()->startOfMonth();
            while ($cursor->lte($fin)) {
                $clave = $cursor->format('Y-m');
                $buckets[$clave] = [
                    'etiqueta' => ucfirst($cursor->locale('es')->monthName).' '.$cursor->format('Y'),
                    'ventas' => 0.0,
                    'transacciones' => 0,
                ];
                $cursor->addMonthNoOverflow();
            }

            return $buckets;
        }

        $diasPorBucket = match ($agrupacion) {
            'semana' => 7,
            'quincena' => 15,
            default => 1,
        };

        $cursor = $inicio->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            $clave = $cursor->toDateString();
            $buckets[$clave] = [
                'etiqueta' => $diasPorBucket === 1
                    ? $cursor->format('d M')
                    : $cursor->format('d/m').' +'.$diasPorBucket.'d',
                'ventas' => 0.0,
                'transacciones' => 0,
            ];
            $cursor->addDays($diasPorBucket);
        }

        return $buckets;
    }

    protected function claveBucket(Carbon $fecha, Carbon $inicio, string $agrupacion): string
    {
        if ($agrupacion === 'mes') {
            return $fecha->format('Y-m');
        }

        if ($agrupacion === 'dia') {
            return $fecha->toDateString();
        }

        $diasPorBucket = $agrupacion === 'semana' ? 7 : 15;
        $indice = (int) floor($inicio->copy()->startOfDay()->diffInDays($fecha->copy()->startOfDay()) / $diasPorBucket);

        return $inicio->copy()->addDays($indice * $diasPorBucket)->toDateString();
    }
}
