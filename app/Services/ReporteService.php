<?php

namespace App\Services;

use App\Models\AsientoContable;

class ReporteService
{
    /**
     * Estado de Resultados por rango de fechas desde los asientos contables.
     */
    public function estadoResultados(string $desde, string $hasta): array
    {
        $asientos = AsientoContable::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->get();

        $ingresos = $asientos->where('tipo', 'ingreso');
        $gastos = $asientos->where('tipo', 'gasto');

        $ventasNetas = (float) $ingresos
            ->where('cuenta', 'ventas_restaurante')
            ->sum('monto');

        $otrosIngresos = (float) $ingresos
            ->reject(fn ($asiento) => $asiento->cuenta === 'ventas_restaurante')
            ->sum('monto');

        $gastosOperativos = (float) $gastos
            ->where('cuenta', 'gastos_operativos')
            ->sum('monto');

        $otrosGastos = (float) $gastos
            ->reject(fn ($asiento) => $asiento->cuenta === 'gastos_operativos')
            ->sum('monto');

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
                    ->groupBy('cuenta')
                    ->map(fn ($grupo) => [
                        'cuenta' => $grupo->first()->cuenta,
                        'total' => (float) $grupo->sum('monto'),
                        'movimientos' => $grupo->count(),
                    ])
                    ->values()
                    ->sortByDesc('total')
                    ->values()
                    ->all(),
                'gastos' => $gastos
                    ->groupBy('cuenta')
                    ->map(fn ($grupo) => [
                        'cuenta' => $grupo->first()->cuenta,
                        'total' => (float) $grupo->sum('monto'),
                        'movimientos' => $grupo->count(),
                    ])
                    ->values()
                    ->sortByDesc('total')
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * Movimientos contables recientes (hasta 100, más recientes primero).
     */
    public function movimientosRecientes(int $limite = 100): \Illuminate\Database\Eloquent\Collection
    {
        return AsientoContable::with('usuario')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }
}