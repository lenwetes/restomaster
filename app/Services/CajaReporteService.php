<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\TurnoCaja;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CajaReporteService
{
    /**
     * Resumen financiero y operacional consolidado para una caja específica.
     */
    public function resumenPorCaja(int $cajaId, ?string $desde = null, ?string $hasta = null): array
    {
        $caja = Caja::with('sucursal')->findOrFail($cajaId);

        $query = TurnoCaja::query()->where('caja_id', $cajaId);

        if ($desde) {
            $query->whereDate('apertura_en', '>=', Carbon::parse($desde));
        }

        if ($hasta) {
            $query->whereDate('apertura_en', '<=', Carbon::parse($hasta));
        }

        $turnos = $query->get();

        $totalVentasEfectivo = (float) $turnos->sum('total_ventas_efectivo');
        $totalVentasTarjeta = (float) $turnos->sum('total_ventas_tarjeta');
        $totalVentasTransferencia = (float) $turnos->sum('total_ventas_transferencia');
        $totalVentas = $totalVentasEfectivo + $totalVentasTarjeta + $totalVentasTransferencia;
        $totalIngresos = (float) $turnos->sum('total_ingresos');
        $totalGastos = (float) $turnos->sum('total_egresos');
        $neto = $totalIngresos - $totalGastos;

        $turnoActivo = $caja->turnoActivo();

        return [
            'caja' => [
                'id' => $caja->id,
                'nombre' => $caja->nombre,
                'codigo' => $caja->codigo,
                'tipo' => $caja->tipo ?? 'principal',
                'descripcion' => $caja->descripcion,
                'activa' => (bool) $caja->activa,
            ],
            'turnos_count' => $turnos->count(),
            'total_ventas' => $totalVentas,
            'ventas_efectivo' => $totalVentasEfectivo,
            'ventas_tarjeta' => $totalVentasTarjeta,
            'ventas_transferencia' => $totalVentasTransferencia,
            'total_ingresos' => $totalIngresos,
            'total_gastos' => $totalGastos,
            'neto' => $neto,
            'tiene_turno_activo' => $turnoActivo !== null,
            'turno_activo' => $turnoActivo ? [
                'id' => $turnoActivo->id,
                'cajero' => $turnoActivo->user?->name,
                'apertura_en' => $turnoActivo->apertura_en ? Carbon::parse($turnoActivo->apertura_en)->toIso8601String() : null,
                'monto_inicial' => (float) $turnoActivo->monto_inicial,
                'total_ventas' => (float) ($turnoActivo->total_ventas_efectivo + $turnoActivo->total_ventas_tarjeta + $turnoActivo->total_ventas_transferencia),
            ] : null,
        ];
    }

    /**
     * Comparativa financiera entre todas las cajas de una sucursal en un período.
     */
    public function compararCajas(int $sucursalId, ?string $desde = null, ?string $hasta = null): array
    {
        $cajas = Caja::where('sucursal_id', $sucursalId)->orderBy('id')->get();

        $comparativa = [];
        $granTotalVentas = 0.0;
        $granTotalGastos = 0.0;

        foreach ($cajas as $caja) {
            $resumen = $this->resumenPorCaja($caja->id, $desde, $hasta);
            $comparativa[] = $resumen;
            $granTotalVentas += $resumen['total_ventas'];
            $granTotalGastos += $resumen['total_gastos'];
        }

        return [
            'sucursal_id' => $sucursalId,
            'cajas' => $comparativa,
            'gran_total_ventas' => $granTotalVentas,
            'gran_total_gastos' => $granTotalGastos,
            'gran_total_neto' => $granTotalVentas - $granTotalGastos,
            'total_cajas' => $cajas->count(),
        ];
    }

    /**
     * Historial paginado o limitado de turnos para una caja.
     */
    public function historialTurnos(int $cajaId, int $limite = 30): Collection
    {
        return TurnoCaja::query()
            ->where('caja_id', $cajaId)
            ->with(['user', 'caja'])
            ->withCount('movimientos')
            ->orderBy('apertura_en', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Desglose de egresos/gastos registrados en una caja.
     */
    public function gastosPorCaja(int $cajaId, ?string $desde = null, ?string $hasta = null): array
    {
        $query = MovimientoCaja::query()
            ->whereHas('turnoCaja', fn ($q) => $q->where('caja_id', $cajaId))
            ->where('tipo', 'egreso')
            ->with(['usuario', 'turnoCaja']);

        if ($desde) {
            $query->whereDate('created_at', '>=', Carbon::parse($desde));
        }

        if ($hasta) {
            $query->whereDate('created_at', '<=', Carbon::parse($hasta));
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        return [
            'total_gastos' => (float) $movimientos->sum('monto'),
            'conteo' => $movimientos->count(),
            'movimientos' => $movimientos->map(fn ($m) => [
                'id' => $m->id,
                'monto' => (float) $m->monto,
                'concepto' => $m->concepto,
                'categoria' => $m->tipo,
                'tipo' => $m->tipo,
                'autorizado_por' => $m->autorizado_por,
                'fecha' => $m->created_at?->toIso8601String(),
                'usuario' => $m->usuario?->name,
            ])->all(),
        ];
    }
}
