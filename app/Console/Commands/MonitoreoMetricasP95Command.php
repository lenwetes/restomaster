<?php

namespace App\Console\Commands;

use App\Http\Middleware\MonitoreoRendimientoSucursalesMiddleware;
use App\Models\Sucursal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Monitorea y calcula los tiempos de respuesta p95 reales de las 5 sucursales de RestoMaster')]
#[Signature('restomaster:metricas-p95')]
class MonitoreoMetricasP95Command extends Command
{
    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('  RestoMaster — Telemetría APM de Rendimiento p95 por Sucursal');
        $this->info('================================================================');

        $sucursales = Sucursal::orderBy('id')->get();
        if ($sucursales->isEmpty()) {
            $this->warn('No se encontraron sucursales registradas.');

            return Command::SUCCESS;
        }

        $filas = [];
        $hayIncumplimientoSla = false;

        foreach ($sucursales as $sucursal) {
            $metricas = MonitoreoRendimientoSucursalesMiddleware::calcularPercentiles($sucursal->id);

            $slaBadge = $metricas['sla_ok']
                ? '<info>✓ ÓPTIMO (SLA OK)</info>'
                : '<error>⚠ CRÍTICO (>800ms)</error>';

            if (! $metricas['sla_ok']) {
                $hayIncumplimientoSla = true;
            }

            $filas[] = [
                $sucursal->id,
                $sucursal->nombre,
                $metricas['total_muestras'],
                $metricas['p50'] > 0 ? "{$metricas['p50']} ms" : 'N/A',
                $metricas['p90'] > 0 ? "{$metricas['p90']} ms" : 'N/A',
                $metricas['p95'] > 0 ? "{$metricas['p95']} ms" : 'N/A',
                $metricas['p99'] > 0 ? "{$metricas['p99']} ms" : 'N/A',
                $slaBadge,
            ];
        }

        $this->table(
            ['ID', 'Sucursal', 'Muestras', 'p50 (Mediana)', 'p90', 'p95', 'p99', 'Estado SLA'],
            $filas
        );

        if ($hayIncumplimientoSla) {
            $this->warn('¡Alerta! Una o más sucursales superan el umbral SLA de 800ms en percentil 95.');
        } else {
            $this->info('Todas las sucursales evaluadas operan dentro de los parámetros de latencia óptimos.');
        }

        return Command::SUCCESS;
    }
}
