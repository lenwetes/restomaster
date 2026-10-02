<?php

use App\Services\Ai\AdminAiCopilotService;
use App\Services\ReportesComparativosService;
use App\Services\ReporteService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class () extends Component {
    public string $desde = '';

    public string $hasta = '';

    public string $pestana = 'graficas';

    public string $presetActivo = '';

    public bool $modoComparativo = false;

    public string $desdeB = '';

    public string $hastaB = '';

    public bool $compararAuto = true;

    public ?array $analisisIa = null;

    public string $errorIa = '';

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['desde', 'hasta'], true)) {
            $this->presetActivo = '';
        }

        if (in_array($property, ['modoComparativo', 'desde', 'hasta', 'compararAuto', 'desdeB', 'hastaB'], true)) {
            $this->analisisIa = null;
            $this->errorIa = '';
        }

        if ($property === 'modoComparativo' && $this->modoComparativo && $this->compararAuto) {
            $this->fijarPeriodoBAutomatico();
        }
    }

    public function usarPeriodoBAutomatico(): void
    {
        $this->compararAuto = true;
        $this->fijarPeriodoBAutomatico();
    }

    public function usarPeriodoBPersonalizado(): void
    {
        $this->compararAuto = false;
    }

    public function analizarConIa(): void
    {
        $user = Auth::user();
        abort_unless($user && ($user->isAdmin() || $user->isGerente()), 403);

        try {
            $this->analisisIa = app(AdminAiCopilotService::class)->analizarReporte([
                'desde' => $this->desde,
                'hasta' => $this->hasta,
                'comparar' => $this->modoComparativo,
                'desde_b' => $this->compararAuto ? null : ($this->desdeB ?: null),
                'hasta_b' => $this->compararAuto ? null : ($this->hastaB ?: null),
            ], $user);
            $this->errorIa = '';
        } catch (\Throwable $e) {
            $this->analisisIa = null;
            $this->errorIa = $e->getMessage();
        }

        $this->dispatch('notificacion', [
            'mensaje' => $this->analisisIa ? 'Análisis con IA generado.' : 'No se pudo generar el análisis.',
            'tipo' => $this->analisisIa ? 'success' : 'error',
        ]);

        if ($this->analisisIa) {
            $this->dispatch('ia-analisis-generado');
        }
    }

    public function setPeriodo(string $preset): void
    {
        switch ($preset) {
            case 'hoy':
                $this->desde = now()->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case 'ayer':
                $this->desde = now()->subDay()->toDateString();
                $this->hasta = now()->subDay()->toDateString();
                break;
            case 'semana_anterior':
                $this->desde = now()->subWeek()->startOfWeek()->toDateString();
                $this->hasta = now()->subWeek()->endOfWeek()->toDateString();
                break;
            case 'ultimos_15_dias':
                $this->desde = now()->subDays(14)->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case 'trimestre':
                $this->desde = now()->startOfQuarter()->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case 'semestre':
                $this->desde = (now()->month <= 6
                    ? now()->startOfYear()
                    : now()->setMonth(7)->startOfMonth())->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case 'anio':
                $this->desde = now()->startOfYear()->toDateString();
                $this->hasta = now()->toDateString();
                break;
        }

        $this->presetActivo = $preset;
    }

    public function with(): array
    {
        $service = app(ReporteService::class);
        $comparativos = app(ReportesComparativosService::class);
        $sucursalId = Auth::user()?->sucursal_id;

        return [
            'graficaVentas' => in_array($this->pestana, ['graficas', 'ventas', 'estado'], true)
                ? $service->datosGraficaVentas($this->desde, $this->hasta)
                : null,
            'comparativaVisual' => in_array($this->pestana, ['graficas', 'ventas'], true)
                ? $service->comparativaPeriodosVisual($this->desde, $this->hasta)
                : null,
            'distribucion' => in_array($this->pestana, ['graficas', 'ventas'], true)
                ? $service->distribucionCanalesYMetodos($this->desde, $this->hasta)
                : null,
            'datos' => match ($this->pestana) {
                'ventas' => [
                    'por_periodo' => $service->ventasPorPeriodo($this->desde, $this->hasta),
                    'por_tipo' => $service->ventasPorTipo($this->desde, $this->hasta),
                    'por_producto' => $service->ventasPorProducto($this->desde, $this->hasta, 10),
                    'por_trabajador' => $service->ventasPorTrabajador($this->desde, $this->hasta),
                    'comparativa' => $service->comparativaPeriodos($this->desde, $this->hasta),
                ],
                'clientes' => [
                    'topClientes' => $service->topClientes($this->desde, $this->hasta, 10),
                    'tiempos' => $service->tiemposEntrega($this->desde, $this->hasta),
                ],
                default => [],
            },
            'resumen_reservas' => $this->pestana === 'reservas'
                ? $service->resumenReservas($this->desde, $this->hasta)
                : null,
            'resumen_meseros' => $this->pestana === 'meseros'
                ? $service->rendimientoMeseros($this->desde, $this->hasta)
                : null,
            'resultado' => $this->pestana === 'estado'
                ? $service->estadoResultados($this->desde, $this->hasta)
                : null,
            'movimientos' => $this->pestana === 'estado'
                ? $service->movimientosRecientes(50)
                : collect(),
            'libroFiscal' => $this->pestana === 'contabilidad'
                ? app(\App\Services\ExportadorContableService::class)->generarLibroFiscalDian($this->desde, $this->hasta)
                : null,
            'serieAvanzada' => $this->pestana === 'graficas'
                ? $comparativos->seriePorPeriodo($this->desde, $this->hasta, $sucursalId)
                : null,
            'heatmapApex' => $this->pestana === 'graficas'
                ? $this->heatmapApex($comparativos->heatmapVentas($this->desde, $this->hasta, $sucursalId))
                : null,
            'top5' => $this->pestana === 'graficas'
                ? $comparativos->topPeriodo($this->desde, $this->hasta, 5, $sucursalId)
                : null,
            'comparativaAvanzada' => ($this->pestana === 'graficas' && $this->modoComparativo)
                ? $comparativos->comparar(
                    $this->desde,
                    $this->hasta,
                    ...$this->rangoComparacion($comparativos)
                ) + ['sucursal_id' => $sucursalId]
                : null,
        ];
    }

    protected function fijarPeriodoBAutomatico(): void
    {
        [$desdeB, $hastaB] = app(ReportesComparativosService::class)
            ->periodoAnteriorAutomatico($this->desde, $this->hasta);
        $this->desdeB = $desdeB;
        $this->hastaB = $hastaB;
    }

    /**
     * @return array{0: string, 1: string, 2: ?int}
     */
    protected function rangoComparacion(ReportesComparativosService $comparativos): array
    {
        if (! $this->compararAuto && $this->desdeB !== '' && $this->hastaB !== '') {
            return [$this->desdeB, $this->hastaB, Auth::user()?->sucursal_id];
        }

        [$desdeB, $hastaB] = $comparativos->periodoAnteriorAutomatico($this->desde, $this->hasta);

        return [$desdeB, $hastaB, Auth::user()?->sucursal_id];
    }

    protected function heatmapApex(array $heatmap): array
    {
        $horas = array_map(fn ($h) => $h . 'h', range(0, 23));
        $series = [];
        foreach ($heatmap['dias'] as $i => $dia) {
            $series[] = ['name' => $dia, 'data' => array_map(fn ($v) => round((float) $v, 2), $heatmap['matriz'][$i] ?? array_fill(0, 24, 0.0))];
        }

        return ['series' => $series, 'horas' => $horas, 'maximo' => $heatmap['maximo']];
    }
}; ?>

<x-slot name="header">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">monitoring</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Reportes y Analítica
                </h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">
                    REP-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Gráficas comparativas interactivas, estado de resultados y métricas del restaurante
            </p>
        </div>
    </div>
</x-slot>

<div class="space-y-6">
    <!-- Carga ApexCharts CDN -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    <!-- Range Filter -->
    <div class="bg-surface-container-lowest rounded-3xl p-5 border border-outline-variant/20 shadow-sm space-y-4">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="text-xs font-bold text-on-surface-variant">Desde:</label>
                <input type="date" wire:model.live="desde" class="mt-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
            </div>
            <div>
                <label class="text-xs font-bold text-on-surface-variant">Hasta:</label>
                <input type="date" wire:model.live="hasta" class="mt-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
            </div>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('reportes.pdf', ['reporte' => $pestana, 'desde' => $desde, 'hasta' => $hasta]) }}" class="rounded-xl bg-surface-container-high px-3 py-2 text-xs font-bold text-on-surface">PDF</a>
                <a href="{{ route('reportes.csv', ['reporte' => $pestana, 'desde' => $desde, 'hasta' => $hasta]) }}" class="rounded-xl bg-surface-container-high px-3 py-2 text-xs font-bold text-on-surface">CSV</a>
            </div>
        </div>

        <!-- Presets de Período Rápido -->
        <div class="flex flex-wrap items-center gap-1.5 pt-3 border-t border-outline-variant/15">
            <span class="text-[11px] font-bold text-on-surface-variant mr-1">Rango rápido:</span>
            @foreach ([
                'hoy' => 'Hoy',
                'ayer' => 'Ayer',
                'semana_anterior' => 'Semana Anterior',
                'ultimos_15_dias' => 'Últimos 15 Días',
                'trimestre' => 'Trimestre',
                'semestre' => 'Semestre',
                'anio' => 'Año',
            ] as $key => $label)
                <button type="button" wire:click="setPeriodo('{{ $key }}')"
                    aria-pressed="{{ $presetActivo === $key ? 'true' : 'false' }}"
                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors
                        {{ $presetActivo === $key
                            ? 'bg-primary text-on-primary shadow-sm'
                            : 'bg-surface-container-low text-on-surface hover:bg-primary/20 hover:text-primary' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2">
        @foreach (['graficas' => 'Gráficas Comparativas', 'estado' => 'Estado de resultados', 'contabilidad' => 'Contabilidad & DIAN', 'ventas' => 'Ventas', 'meseros' => 'Rendimiento Meseros', 'clientes' => 'Clientes & Delivery', 'reservas' => 'Reservas'] as $k => $label)
            <button wire:click="$set('pestana', '{{ $k }}')" class="rounded-full px-4 py-2 text-xs font-bold transition-colors
                {{ $pestana === $k ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant border border-outline-variant/20 hover:text-on-surface' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($pestana === 'graficas')
        <!-- Tab Gráficas Comparativas (F7-08) -->
        <div class="space-y-6"
             x-data="{
                charts: {},
                renderAll() {
                    if (typeof ApexCharts === 'undefined') return;

                    // 1. Gráfica de Área de Facturación Diaria
                    const elVentas = document.getElementById('chart-ventas-diarias');
                    if (elVentas) {
                        if (this.charts.ventas) this.charts.ventas.destroy();
                        this.charts.ventas = new ApexCharts(elVentas, {
                            chart: { type: 'area', height: 320, toolbar: { show: false }, background: 'transparent' },
                            theme: { mode: 'dark' },
                            series: [{ name: 'Ventas ($)', data: {{ json_encode($graficaVentas['ventas'] ?? []) }} }],
                            xaxis: { categories: {{ json_encode($graficaVentas['etiquetas'] ?? []) }}, labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
                            yaxis: { labels: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO'), style: { colors: '#94a3b8', fontSize: '11px' } } },
                            colors: ['#e0442e'],
                            stroke: { curve: 'smooth', width: 3 },
                            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.05 } },
                            dataLabels: { enabled: false },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.ventas.render();
                    }

                    // 2. Gráfica de Comparativa Período Actual vs Anterior
                    const elComp = document.getElementById('chart-comparativa-periodos');
                    if (elComp) {
                        if (this.charts.comp) this.charts.comp.destroy();
                        this.charts.comp = new ApexCharts(elComp, {
                            chart: { type: 'bar', height: 320, toolbar: { show: false }, background: 'transparent' },
                            theme: { mode: 'dark' },
                            series: [
                                { name: 'Período Actual', data: {{ json_encode($comparativaVisual['serie_actual'] ?? []) }} },
                                { name: 'Período Anterior', data: {{ json_encode($comparativaVisual['serie_anterior'] ?? []) }} }
                            ],
                            xaxis: { categories: {{ json_encode($comparativaVisual['etiquetas'] ?? []) }}, labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
                            yaxis: { labels: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO'), style: { colors: '#94a3b8', fontSize: '11px' } } },
                            colors: ['#e0442e', '#475569'],
                            plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
                            dataLabels: { enabled: false },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.comp.render();
                    }

                    // 3. Gráfica Donut de Canales
                    const elCanales = document.getElementById('chart-canales-venta');
                    if (elCanales) {
                        if (this.charts.canales) this.charts.canales.destroy();
                        this.charts.canales = new ApexCharts(elCanales, {
                            chart: { type: 'donut', height: 280, background: 'transparent' },
                            theme: { mode: 'dark' },
                            series: {{ json_encode($distribucion['canales']['series'] ?? []) }},
                            labels: {{ json_encode($distribucion['canales']['etiquetas'] ?? []) }},
                            colors: ['#e0442e', '#e8a020', '#2eb8b4', '#8b5cf6'],
                            legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.canales.render();
                    }

                    // 4b. Línea acumulativa de ventas (Fase 9.1)
                    const elAcum = document.getElementById('chart-ventas-acumuladas');
                    if (elAcum) {
                        if (this.charts.acum) this.charts.acum.destroy();
                        this.charts.acum = new ApexCharts(elAcum, {
                            chart: { type: 'line', height: 300, toolbar: { show: false }, background: 'transparent' },
                            theme: { mode: 'dark' },
                            series: [{ name: 'Acumulado ($)', data: {{ json_encode($serieAvanzada['acumulada'] ?? []) }} }],
                            xaxis: { categories: {{ json_encode($serieAvanzada['etiquetas'] ?? []) }}, labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
                            yaxis: { labels: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO'), style: { colors: '#94a3b8', fontSize: '11px' } } },
                            colors: ['#2eb8b4'],
                            stroke: { curve: 'smooth', width: 3 },
                            dataLabels: { enabled: false },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.acum.render();
                    }

                    // 4c. Heatmap día × hora (Fase 9.1)
                    const elHeat = document.getElementById('chart-heatmap-ventas');
                    if (elHeat) {
                        if (this.charts.heat) this.charts.heat.destroy();
                        this.charts.heat = new ApexCharts(elHeat, {
                            chart: { type: 'heatmap', height: 300, toolbar: { show: false }, background: 'transparent' },
                            theme: { mode: 'dark' },
                            series: {{ json_encode($heatmapApex['series'] ?? []) }},
                            xaxis: { categories: {{ json_encode($heatmapApex['horas'] ?? []) }}, labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
                            colors: ['#e0442e'],
                            plotOptions: { heatmap: { shadeIntensity: 0.6, radius: 4, colorScale: { ranges: [{ from: 0, to: 0, color: '#2e2018', name: 'Sin ventas' }, { from: 1, to: 1000000000, color: '#e0442e', name: 'Ventas' }] } } },
                            dataLabels: { enabled: false },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.heat.render();
                    }

                    // 4d. Gráfica Donut de Métodos
                    const elMetodos = document.getElementById('chart-metodos-pago');
                    if (elMetodos) {
                        if (this.charts.metodos) this.charts.metodos.destroy();
                        this.charts.metodos = new ApexCharts(elMetodos, {
                            chart: { type: 'donut', height: 280, background: 'transparent' },
                            theme: { mode: 'dark' },
                            series: {{ json_encode($distribucion['metodos']['series'] ?? []) }},
                            labels: {{ json_encode($distribucion['metodos']['etiquetas'] ?? []) }},
                            colors: ['#10b981', '#3b82f6', '#f59e0b', '#ec4899'],
                            legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.metodos.render();
                    }

                    // 5a. Gráfica de Tendencia Temporal del Análisis IA
                    const elIaTendencia = document.getElementById('chart-ia-tendencia');
                    if (elIaTendencia) {
                        if (this.charts.iaTendencia) this.charts.iaTendencia.destroy();
                        this.charts.iaTendencia = new ApexCharts(elIaTendencia, {
                            chart: { type: 'area', height: 260, toolbar: { show: false }, background: 'transparent' },
                            theme: { mode: 'dark' },
                            series: [
                                {
                                    name: {{ json_encode($analisisIa['datos']['graficas']['tendencia_temporal']['label_a'] ?? 'Período A') }},
                                    data: {{ json_encode($analisisIa['datos']['graficas']['tendencia_temporal']['serie_a'] ?? []) }}
                                }
                                @if(!empty($analisisIa['datos']['graficas']['tendencia_temporal']['serie_b']))
                                , {
                                    name: {{ json_encode($analisisIa['datos']['graficas']['tendencia_temporal']['label_b'] ?? 'Período B') }},
                                    data: {{ json_encode($analisisIa['datos']['graficas']['tendencia_temporal']['serie_b'] ?? []) }}
                                }
                                @endif
                            ],
                            xaxis: {
                                categories: {{ json_encode($analisisIa['datos']['graficas']['tendencia_temporal']['etiquetas'] ?? []) }},
                                labels: { style: { colors: '#94a3b8', fontSize: '10px' } }
                            },
                            yaxis: {
                                labels: {
                                    formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO'),
                                    style: { colors: '#94a3b8', fontSize: '10px' }
                                }
                            },
                            colors: ['#2eb8b4', '#e0442e'],
                            stroke: { curve: 'smooth', width: 2.5 },
                            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.05 } },
                            dataLabels: { enabled: false },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.iaTendencia.render();
                    }

                    // 5b. Gráfica de Platos Líderes / Top Productos IA
                    const elIaTop = document.getElementById('chart-ia-top-productos');
                    if (elIaTop) {
                        if (this.charts.iaTop) this.charts.iaTop.destroy();
                        this.charts.iaTop = new ApexCharts(elIaTop, {
                            chart: { type: 'bar', height: 260, toolbar: { show: false }, background: 'transparent' },
                            theme: { mode: 'dark' },
                            plotOptions: {
                                bar: {
                                    horizontal: true,
                                    borderRadius: 6,
                                    barHeight: '60%',
                                    distributed: true
                                }
                            },
                            series: [{
                                name: 'Facturación ($)',
                                data: {{ json_encode($analisisIa['datos']['graficas']['top_productos']['ventas'] ?? []) }}
                            }],
                            xaxis: {
                                categories: {{ json_encode($analisisIa['datos']['graficas']['top_productos']['nombres'] ?? []) }},
                                labels: {
                                    formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO'),
                                    style: { colors: '#94a3b8', fontSize: '10px' }
                                }
                            },
                            yaxis: {
                                labels: { style: { colors: '#f1f5f9', fontSize: '11px', fontWeight: 600 } }
                            },
                            colors: ['#e0442e', '#e8a020', '#2eb8b4', '#8b5cf6', '#ec4899'],
                            legend: { show: false },
                            dataLabels: {
                                enabled: true,
                                formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO'),
                                style: { fontSize: '10px', colors: ['#fff'] }
                            },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.iaTop.render();
                    }

                    // 5c. Gráfica Comparativa de KPIs Clave IA
                    const elIaKpis = document.getElementById('chart-ia-comparativa-kpis');
                    if (elIaKpis) {
                        if (this.charts.iaKpis) this.charts.iaKpis.destroy();
                        this.charts.iaKpis = new ApexCharts(elIaKpis, {
                            chart: { type: 'bar', height: 240, toolbar: { show: false }, background: 'transparent' },
                            theme: { mode: 'dark' },
                            plotOptions: {
                                bar: {
                                    horizontal: false,
                                    columnWidth: '45%',
                                    borderRadius: 6
                                }
                            },
                            series: [
                                {
                                    name: {{ json_encode($analisisIa['datos']['graficas']['comparativa_kpis']['label_a'] ?? 'Período A') }},
                                    data: {{ json_encode($analisisIa['datos']['graficas']['comparativa_kpis']['valores_a'] ?? []) }}
                                },
                                {
                                    name: {{ json_encode($analisisIa['datos']['graficas']['comparativa_kpis']['label_b'] ?? 'Período B') }},
                                    data: {{ json_encode($analisisIa['datos']['graficas']['comparativa_kpis']['valores_b'] ?? []) }}
                                }
                            ],
                            xaxis: {
                                categories: {{ json_encode($analisisIa['datos']['graficas']['comparativa_kpis']['etiquetas'] ?? []) }},
                                labels: { style: { colors: '#94a3b8', fontSize: '11px', fontWeight: 600 } }
                            },
                            yaxis: {
                                labels: {
                                    formatter: (val) => Number(val).toLocaleString('es-CO'),
                                    style: { colors: '#94a3b8', fontSize: '10px' }
                                }
                            },
                            colors: ['#2eb8b4', '#64748b'],
                            legend: { position: 'top', labels: { colors: '#94a3b8' } },
                            dataLabels: { enabled: false },
                            tooltip: { y: { formatter: (val) => Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.iaKpis.render();
                    }

                    // 5d. Gráfica de Distribución de Canales IA
                    const elIaCanales = document.getElementById('chart-ia-canales');
                    if (elIaCanales) {
                        if (this.charts.iaCanales) this.charts.iaCanales.destroy();
                        this.charts.iaCanales = new ApexCharts(elIaCanales, {
                            chart: { type: 'donut', height: 240, background: 'transparent' },
                            theme: { mode: 'dark' },
                            series: {{ json_encode($analisisIa['datos']['graficas']['canales']['series'] ?? []) }},
                            labels: {{ json_encode($analisisIa['datos']['graficas']['canales']['etiquetas'] ?? []) }},
                            colors: ['#e0442e', '#e8a020', '#2eb8b4', '#8b5cf6'],
                            legend: { position: 'bottom', labels: { colors: '#94a3b8', fontSize: '10px' } },
                            tooltip: { y: { formatter: (val) => '$ ' + Number(val).toLocaleString('es-CO') } }
                        });
                        this.charts.iaCanales.render();
                    }
                }
             }"
             x-init="$nextTick(() => renderAll())"
             x-effect="renderAll()"
             @ia-analisis-generado.window="$nextTick(() => setTimeout(() => renderAll(), 60))">

            <!-- KPI Cards Resumen Gráficas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant block">Facturación Total (Ventas netas)</span>
                    <div class="text-2xl font-black text-on-surface mt-1">
                        ${{ number_format((float) ($graficaVentas['total_periodo'] ?? 0), 0, ',', '.') }}
                    </div>
                    <span class="text-[10px] text-emerald-400 font-bold block mt-1">Período seleccionado</span>
                </div>

                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant block">Promedio Diario</span>
                    <div class="text-2xl font-black text-on-surface mt-1">
                        ${{ number_format((float) ($graficaVentas['promedio_diario'] ?? 0), 0, ',', '.') }}
                    </div>
                    <span class="text-[10px] text-on-surface-variant font-bold block mt-1">Por día en el rango</span>
                </div>

                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant block">Variación vs Anterior</span>
                    @php $crec = (float) ($comparativaVisual['crecimiento'] ?? 0); @endphp
                    <div class="text-2xl font-black {{ $crec >= 0 ? 'text-emerald-400' : 'text-rose-400' }} mt-1 flex items-center gap-1">
                        <span>{{ $crec >= 0 ? '+'.$crec : $crec }}%</span>
                        <span class="material-symbols-outlined text-lg">{{ $crec >= 0 ? 'trending_up' : 'trending_down' }}</span>
                    </div>
                    <span class="text-[10px] text-on-surface-variant font-bold block mt-1">Vs. ${{ number_format((float) ($comparativaVisual['total_anterior'] ?? 0), 0, ',', '.') }}</span>
                </div>

                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant block">Días en Análisis</span>
                    <div class="text-2xl font-black text-on-surface mt-1">
                        {{ count($graficaVentas['fechas'] ?? []) }} días
                    </div>
                    <span class="text-[10px] text-secondary font-bold block mt-1">Rango continuo</span>
                </div>
            </div>

            <!-- Gráficas Principales: Área y Barras Dobles -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Evolución Diaria -->
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-primary">show_chart</span>
                                Evolución Diaria de Ventas
                            </h3>
                            <p class="text-[11px] text-on-surface-variant mt-0.5">Ingresos cobrados por día en el período seleccionado</p>
                        </div>
                    </div>
                    <div id="chart-ventas-diarias" class="min-h-[320px]"></div>
                </div>

                <!-- Comparativa Período Actual vs Anterior -->
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-secondary">compare_arrows</span>
                                Comparativa: Actual vs Anterior
                            </h3>
                            <p class="text-[11px] text-on-surface-variant mt-0.5">Ventas comparadas día por día con la ventana previa</p>
                        </div>
                    </div>
                    <div id="chart-comparativa-periodos" class="min-h-[320px]"></div>
                </div>
            </div>

            <!-- Comparativa avanzada + Análisis IA (Fase 9) -->
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-secondary">compare_arrows</span>
                            Modo Comparativo Avanzado
                        </h3>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">Período A (azul) vs Período B (gris) · Agrupación: {{ $serieAvanzada['agrupacion'] ?? '—' }}</p>
                    </div>
                    <label class="inline-flex items-center gap-2 rounded-xl bg-surface-container-low border border-outline-variant/20 px-3.5 py-2 text-xs font-bold text-on-surface cursor-pointer min-h-[44px]">
                        <input type="checkbox" wire:model.live="modoComparativo" class="rounded text-primary focus:ring-0 w-5 h-5" />
                        <span>Comparar periodos</span>
                    </label>
                </div>

                @if ($modoComparativo)
                    <div class="flex flex-wrap items-end gap-3 rounded-2xl bg-surface-container-low border border-outline-variant/20 p-4">
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="usarPeriodoBAutomatico" class="rounded-xl px-3.5 py-2 text-xs font-bold min-h-[44px] {{ $compararAuto ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant' }}">
                                Mismo período anterior
                            </button>
                            <button type="button" wire:click="usarPeriodoBPersonalizado" class="rounded-xl px-3.5 py-2 text-xs font-bold min-h-[44px] {{ ! $compararAuto ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant' }}">
                                Período personalizado
                            </button>
                        </div>
                        @if (! $compararAuto)
                            <div>
                                <label class="text-xs font-bold text-on-surface-variant">Desde B:</label>
                                <input type="date" wire:model.live="desdeB" class="mt-1 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0 min-h-[44px]" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-on-surface-variant">Hasta B:</label>
                                <input type="date" wire:model.live="hastaB" class="mt-1 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0 min-h-[44px]" />
                            </div>
                        @endif
                        <span class="flex-1"></span>
                        <a href="{{ route('reportes.comparativa-csv', array_filter(['desde' => $desde, 'hasta' => $hasta, 'comparar' => 1, 'desde_b' => $compararAuto ? null : $desdeB, 'hasta_b' => $compararAuto ? null : $hastaB])) }}" class="rounded-xl bg-surface-container-high px-3.5 py-2 text-xs font-bold text-on-surface min-h-[44px] inline-flex items-center">CSV</a>
                        <a href="{{ route('reportes.informe-ejecutivo', array_filter(['desde' => $desde, 'hasta' => $hasta, 'comparar' => 1, 'desde_b' => $compararAuto ? null : $desdeB, 'hasta_b' => $compararAuto ? null : $hastaB])) }}" class="rounded-xl bg-secondary px-3.5 py-2 text-xs font-bold text-white min-h-[44px] inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                            PDF Ejecutivo
                        </a>
                    </div>

                    @if ($comparativaAvanzada)
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            @foreach ([
                                ['Δ% ventas brutas', $comparativaAvanzada['delta_ventas_pct'].'%', $comparativaAvanzada['delta_ventas_pct'] >= 0],
                                ['Δ% comandas', $comparativaAvanzada['delta_comandas_pct'].'%', $comparativaAvanzada['delta_comandas_pct'] >= 0],
                                ['Δ ticket promedio', '$ '.number_format($comparativaAvanzada['delta_ticket'], 0, ',', '.'), $comparativaAvanzada['delta_ticket'] >= 0],
                                ['Δ% devoluciones', $comparativaAvanzada['delta_devoluciones_pct'].'%', $comparativaAvanzada['delta_devoluciones_pct'] <= 0],
                            ] as [$label, $valor, $positivo])
                                <div class="rounded-2xl bg-surface-container-low border border-outline-variant/20 p-4">
                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant block">{{ $label }}</span>
                                    <span class="text-xl font-black {{ $positivo ? 'text-emerald-400' : 'text-rose-400' }} block mt-1">{{ $valor }}</span>
                                    <span class="text-[10px] text-on-surface-variant font-bold block mt-0.5">A: ${{ number_format($comparativaAvanzada['a']['ventas'], 0, ',', '.') }} · B: ${{ number_format($comparativaAvanzada['b']['ventas'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-outline-variant/15">
                    <button type="button" wire:click="analizarConIa" class="rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-on-primary shadow-md hover:bg-primary/90 active:scale-95 transition cursor-pointer min-h-[44px] min-w-[44px] inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">psychology</span>
                        <span>🧠 Analizar con IA</span>
                    </button>
                    <span class="text-[11px] text-on-surface-variant">Resumen ejecutivo, tendencia y 3 recomendaciones.</span>
                </div>

                @if ($errorIa !== '')
                    <div class="rounded-2xl border border-error/40 bg-error/10 p-4 text-xs font-bold text-error">{{ $errorIa }}</div>
                @endif

                @if ($analisisIa)
                    <div class="rounded-3xl border border-secondary/35 bg-surface-container-low/95 p-6 space-y-5 shadow-xl backdrop-blur-md relative overflow-hidden"
                         x-init="$nextTick(() => setTimeout(() => renderAll(), 60))">
                        
                        <!-- Top subtle accent line -->
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-secondary via-primary to-amber-400"></div>

                        <!-- Header & Badges -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-outline-variant/15 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-secondary/20 flex items-center justify-center text-secondary border border-secondary/30 shadow-inner">
                                    <span class="material-symbols-outlined text-[24px]">smart_toy</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-base font-black text-on-surface tracking-tight">Diagnóstico Ejecutivo de Analítica IA</h4>
                                        @php
                                            $tendencia = $analisisIa['datos']['tendencia'] ?? 'meseta';
                                            $badgeClase = match($tendencia) {
                                                'crecimiento' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                                'caída' => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
                                                default => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                                            };
                                        @endphp
                                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider border {{ $badgeClase }}">
                                            {{ $tendencia }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant font-medium mt-0.5">Modelado predictivo, comparativa de períodos y síntesis de patrones comerciales</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-xl bg-secondary/15 px-3 py-1.5 text-xs font-black text-secondary border border-secondary/30 font-mono">
                                    Δ Ventas: {{ $analisisIa['datos']['delta_ventas_pct'] ?? 0 }}%
                                </span>
                                <a href="{{ route('reportes.informe-ejecutivo', array_filter(['desde' => $desde, 'hasta' => $hasta, 'comparar' => 1, 'desde_b' => $compararAuto ? null : $desdeB, 'hasta_b' => $compararAuto ? null : $hastaB])) }}" 
                                   class="rounded-xl bg-secondary/20 hover:bg-secondary hover:text-white px-3 py-1.5 text-xs font-bold text-secondary border border-secondary/30 transition flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]">download</span>
                                    <span>Exportar PDF</span>
                                </a>
                            </div>
                        </div>

                        <!-- Síntesis Ejecutiva -->
                        <div class="rounded-2xl bg-surface-container-lowest/80 border border-outline-variant/20 p-4 text-xs text-on-surface leading-relaxed whitespace-pre-line font-medium shadow-inner">
                            {{ $analisisIa['mensaje'] }}
                        </div>

                        <!-- 📊 SECCIÓN DE GRÁFICAS GENERADAS POR IA -->
                        <div class="space-y-4 pt-1">
                            <div class="flex items-center justify-between border-b border-outline-variant/15 pb-2">
                                <h5 class="text-xs font-black uppercase tracking-wider text-secondary flex items-center gap-1.5 font-mono">
                                    <span class="material-symbols-outlined text-[18px]">query_stats</span>
                                    <span>Gráficas del Análisis IA Generadas en Tiempo Real</span>
                                </h5>
                                <span class="text-[11px] text-on-surface-variant font-mono">ApexCharts interactivo</span>
                            </div>

                            <!-- Fila 1 de Gráficas IA: Tendencia Temporal + Top Platos Impulsores -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <!-- Gráfica 1: Curva de Ventas Período A vs Comparación B -->
                                <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-4 shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2 text-xs font-black text-on-surface">
                                            <span class="material-symbols-outlined text-[18px] text-secondary">show_chart</span>
                                            <span>Curva de Ventas (Evolución Período A vs Período B)</span>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-on-surface-variant mb-2">Trayectoria día a día comparada</p>
                                    <div id="chart-ia-tendencia" class="min-h-[260px]"></div>
                                </div>

                                <!-- Gráfica 2: Top 5 Platos Líderes Impulsores -->
                                <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-4 shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2 text-xs font-black text-on-surface">
                                            <span class="material-symbols-outlined text-[18px] text-primary">leaderboard</span>
                                            <span>Top Platos Impulsores del Período</span>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-on-surface-variant mb-2">Platos que explican la tendencia detectada</p>
                                    <div id="chart-ia-top-productos" class="min-h-[260px]"></div>
                                </div>
                            </div>

                            <!-- Fila 2 de Gráficas IA: Balance KPIs + Mix Canales -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <!-- Gráfica 3: Balance Comparativo de Métricas Clave -->
                                <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-4 shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2 text-xs font-black text-on-surface">
                                            <span class="material-symbols-outlined text-[18px] text-amber-400">stacked_bar_chart</span>
                                            <span>Balance Comparativo de KPIs (Nominal A vs B)</span>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-on-surface-variant mb-2">Facturación, Ticket Promedio y Volumen de Comandas</p>
                                    <div id="chart-ia-comparativa-kpis" class="min-h-[240px]"></div>
                                </div>

                                <!-- Gráfica 4: Mix de Canales de Venta -->
                                <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-4 shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2 text-xs font-black text-on-surface">
                                            <span class="material-symbols-outlined text-[18px] text-teal-400">pie_chart</span>
                                            <span>Distribución por Canales de Venta</span>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-on-surface-variant mb-2">Proporción de Sala, Delivery, QR de Mesa y Para Llevar</p>
                                    <div id="chart-ia-canales" class="min-h-[240px]"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Recomendaciones Operativas Sugeridas -->
                        <div class="pt-3 border-t border-outline-variant/15 space-y-2">
                            <h5 class="text-xs font-black uppercase tracking-wider text-secondary flex items-center gap-1.5 font-mono">
                                <span class="material-symbols-outlined text-[18px]">lightbulb</span>
                                <span>Plan de Acción Operativo Recomendado</span>
                            </h5>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                @foreach ($analisisIa['datos']['recomendaciones'] ?? [] as $i => $rec)
                                    <div class="p-3.5 rounded-2xl bg-surface-container-lowest border border-outline-variant/20 flex items-start gap-3 shadow-xs">
                                        <span class="w-6 h-6 rounded-xl bg-secondary/20 text-secondary text-xs font-black flex items-center justify-center shrink-0 border border-secondary/30 mt-0.5">
                                            {{ $i + 1 }}
                                        </span>
                                        <span class="text-xs text-on-surface leading-relaxed font-medium">{{ $rec }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                @endif
            </div>

            <!-- Acumulada + Heatmap + Top 5 (Fase 9.1) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                    <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-secondary">trending_up</span>
                        Línea Acumulativa de Ventas
                    </h3>
                    <p class="text-[11px] text-on-surface-variant mt-0.5 mb-4">Acumulado día a día ({{ $serieAvanzada['agrupacion'] ?? '' }})</p>
                    <div id="chart-ventas-acumuladas" class="min-h-[300px]"></div>
                </div>
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                    <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-amber-500">grid_on</span>
                        Heatmap Día × Hora
                    </h3>
                    <p class="text-[11px] text-on-surface-variant mt-0.5 mb-4">Intensidad de ventas por franja horaria</p>
                    <div id="chart-heatmap-ventas" class="min-h-[300px]"></div>
                </div>
            </div>

            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-[18px] text-primary">emoji_events</span>
                    Top 5 del Periodo
                </h3>
                <div class="space-y-2">
                    @forelse ($top5 ?? [] as $i => $plato)
                        <div class="flex items-center gap-3 rounded-2xl bg-surface-container-low border border-outline-variant/20 px-4 py-2.5">
                            <span class="text-sm font-black text-primary w-6">{{ $i + 1 }}</span>
                            <span class="flex-1 text-xs font-bold text-on-surface break-words">{{ $plato['producto'] }}</span>
                            <span class="text-xs font-mono text-on-surface-variant">{{ $plato['cantidad'] }} uds</span>
                            <span class="text-xs font-black text-on-surface">${{ number_format($plato['total_ventas'], 0, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-on-surface-variant">Sin ventas en el periodo seleccionado.</p>
                    @endforelse
                </div>
            </div>

            <!-- Gráficas Secundarias: Donuts Canales y Métodos de Pago -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Canales de Venta -->
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-amber-500">pie_chart</span>
                                Ventas por Canal
                            </h3>
                            <p class="text-[11px] text-on-surface-variant mt-0.5">Distribución entre Salón, Delivery y Autoservicio QR</p>
                        </div>
                    </div>
                    <div id="chart-canales-venta" class="min-h-[280px]"></div>
                </div>

                <!-- Métodos de Pago -->
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-emerald-500">account_balance_wallet</span>
                                Métodos de Pago
                            </h3>
                            <p class="text-[11px] text-on-surface-variant mt-0.5">Efectivo, Tarjetas, Transferencias bancarias y Mixto</p>
                        </div>
                    </div>
                    <div id="chart-metodos-pago" class="min-h-[280px]"></div>
                </div>
            </div>
        </div>
    @endif

    @if ($pestana === 'ventas')
        <!-- Ventas tab -->
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-secondary">insights</span>
                Comparativa con período anterior
            </h3>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-surface-container-low p-4">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Actual</span>
                    <p class="mt-1 text-lg font-black text-on-surface">${{ number_format((float) $datos['comparativa']['periodo_actual']['ventas'], 0, ',','.') }}</p>
                    <p class="text-[11px] text-on-surface-variant">{{ $datos['comparativa']['periodo_actual']['transacciones'] }} transacciones</p>
                </div>
                <div class="rounded-2xl bg-surface-container-low p-4">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Anterior</span>
                    <p class="mt-1 text-lg font-black text-on-surface">${{ number_format((float) $datos['comparativa']['periodo_anterior']['ventas'], 0, ',','.') }}</p>
                    <p class="text-[11px] text-on-surface-variant">{{ $datos['comparativa']['periodo_anterior']['transacciones'] }} transacciones</p>
                </div>
                <div class="rounded-2xl bg-surface-container-low p-4">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Variación ventas</span>
                    <p class="mt-1 text-lg font-black {{ $datos['comparativa']['variacion_ventas'] >= 0 ? 'text-secondary' : 'text-error' }}">
                        {{ $datos['comparativa']['variacion_ventas'] >= 0 ? '+' : '' }}{{ $datos['comparativa']['variacion_ventas'] }}%
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                    <span class="material-symbols-outlined text-[18px] text-primary">star</span>
                    Top productos
                </h3>
                <div class="space-y-2">
                    @forelse ($datos['por_producto'] as $fila)
                        <div class="flex items-center justify-between rounded-2xl bg-surface-container-low px-4 py-3">
                            <div>
                                <span class="text-xs font-bold text-on-surface block">{{ $fila['producto'] }}</span>
                                <span class="text-[10px] font-mono text-on-surface-variant">{{ $fila['cantidad'] }} vendidos · margen ${{ number_format((float) $fila['margen'], 0, ',','.') }}</span>
                            </div>
                            <span class="text-sm font-black text-secondary">${{ number_format((float) $fila['ventas'], 0, ',','.') }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-on-surface-variant italic py-4">Sin ventas en el período.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                    <span class="material-symbols-outlined text-[18px] text-primary">payments</span>
                    Ventas por canal
                </h3>
                <div class="space-y-2">
                    @forelse ($datos['por_tipo'] as $fila)
                        <div class="flex items-center justify-between rounded-2xl bg-surface-container-low px-4 py-3">
                            <span class="text-xs font-bold text-on-surface capitalize">{{ $fila['tipo'] }}</span>
                            <span class="text-sm font-black text-secondary">${{ number_format((float) $fila['ventas'], 0, ',','.') }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-on-surface-variant italic py-4">Sin ventas en el período.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-primary">calendar_month</span>
                Ventas por día
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-outline-variant/15 text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                            <th class="py-2.5 px-3">Fecha</th>
                            <th class="py-2.5 px-3 text-right">Ventas</th>
                            <th class="py-2.5 px-3 text-right">Transacciones</th>
                            <th class="py-2.5 px-3 text-right">Ticket promedio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10 font-medium">
                        @forelse ($datos['por_periodo'] as $fila)
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="py-2.5 px-3 font-mono text-on-surface-variant">{{ $fila['fecha'] }}</td>
                                <td class="py-2.5 px-3 text-right font-black text-secondary">${{ number_format((float) $fila['ventas'], 0, ',','.') }}</td>
                                <td class="py-2.5 px-3 text-right">{{ $fila['transacciones'] }}</td>
                                <td class="py-2.5 px-3 text-right">${{ number_format((float) $fila['ticket_promedio'], 0, ',','.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-on-surface-variant">Sin ventas en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($pestana === 'meseros')
        <!-- Meseros Tab -->
        @if ($resumen_meseros)
            <!-- Bento KPIs Meseros -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ventas Salón</span>
                    <span class="mt-2 flex items-center gap-1.5 text-2xl font-black text-on-surface">
                        <span class="material-symbols-outlined text-[22px] text-primary">point_of_sale</span>
                        ${{ number_format((float) $resumen_meseros['totales']['total_ventas_netas'], 0, ',', '.') }}
                    </span>
                    <p class="mt-1 text-[11px] text-on-surface-variant font-mono">{{ $resumen_meseros['totales']['total_comandas'] }} comandas cerradas</p>
                </div>
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Propinas Recaudadas</span>
                    <span class="mt-2 flex items-center gap-1.5 text-2xl font-black text-secondary">
                        <span class="material-symbols-outlined text-[22px]">volunteer_activism</span>
                        ${{ number_format((float) $resumen_meseros['totales']['total_propinas'], 0, ',', '.') }}
                    </span>
                    <p class="mt-1 text-[11px] text-on-surface-variant">Servicio voluntario comensales</p>
                </div>
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ticket Promedio</span>
                    <span class="mt-2 flex items-center gap-1.5 text-2xl font-black text-on-surface">
                        <span class="material-symbols-outlined text-[22px] text-tertiary">analytics</span>
                        ${{ number_format((float) $resumen_meseros['totales']['ticket_promedio_general'], 0, ',', '.') }}
                    </span>
                    <p class="mt-1 text-[11px] text-on-surface-variant">Promedio por mesa cobrada</p>
                </div>
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Mesero Estrella</span>
                    <span class="mt-2 flex items-center gap-1.5 text-xl font-black text-primary truncate">
                        <span class="material-symbols-outlined text-[22px] text-amber-500">military_tech</span>
                        <span class="truncate">{{ $resumen_meseros['totales']['mesero_estrella'] }}</span>
                    </span>
                    <p class="mt-1 text-[11px] text-on-surface-variant">Líder en ventas del período</p>
                </div>
            </div>

            <!-- Tabla Detallada Rendimiento por Mesero -->
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] text-primary">badge</span>
                            Tabla de Desempeño y Liquidación de Propinas
                        </h3>
                        <p class="text-[11px] text-on-surface-variant">
                            Métricas de ventas atribuidas, comandas atendidas y propinas voluntarias recaudadas por mesero
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-surface-container-low text-on-surface border border-outline-variant/20">
                            <span class="h-2 w-2 rounded-full bg-secondary"></span>
                            <span>{{ $resumen_meseros['totales']['total_mesas_activas'] }} Mesas Ocupadas en Sala</span>
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant/15 text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                                <th class="py-3 px-3">Mesero</th>
                                <th class="py-3 px-3 text-center">Mesas Activas</th>
                                <th class="py-3 px-3 text-right">Comandas</th>
                                <th class="py-3 px-3 text-right">Ventas Netas</th>
                                <th class="py-3 px-3 text-right">Propinas</th>
                                <th class="py-3 px-3 text-center">% Efectividad</th>
                                <th class="py-3 px-3 text-right">Ticket Prom.</th>
                                <th class="py-3 px-3 text-right font-black">Total Facturado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10 font-medium">
                            @forelse ($resumen_meseros['meseros'] as $m)
                                <tr class="hover:bg-surface-container-low transition-colors">
                                    <td class="py-3 px-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-surface-container text-on-surface font-black text-xs border border-outline-variant/20">
                                                {{ strtoupper(substr($m['nombre'], 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-bold text-on-surface">{{ $m['nombre'] }}</span>
                                                    @if($m['activo'])
                                                        <span class="inline-block h-2 w-2 rounded-full bg-secondary" title="Activo"></span>
                                                    @endif
                                                </div>
                                                <span class="text-[10px] text-on-surface-variant font-mono">{{ $m['email'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        @if($m['mesas_activas'] > 0)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-primary/10 text-primary border border-primary/20">
                                                <span class="material-symbols-outlined text-[14px]">restaurant</span>
                                                {{ $m['mesas_activas'] }}
                                                @if($m['ventas_en_curso'] > 0)
                                                    <span class="text-[10px] font-normal text-on-surface-variant">(${{ number_format((float) $m['ventas_en_curso'], 0, ',', '.') }})</span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-on-surface-variant text-[11px]">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono font-bold">{{ $m['comandas_cerradas'] }}</td>
                                    <td class="py-3 px-3 text-right font-black text-on-surface">
                                        ${{ number_format((float) $m['ventas_netas'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-black text-secondary">
                                        ${{ number_format((float) $m['propinas_recaudadas'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded-lg text-[10px] font-black {{ $m['efectividad_propina'] >= 80 ? 'bg-secondary/15 text-secondary' : ($m['efectividad_propina'] >= 50 ? 'bg-amber-500/15 text-amber-600' : 'bg-surface-container text-on-surface-variant') }}">
                                            {{ $m['efectividad_propina'] }}%
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono text-on-surface-variant">
                                        ${{ number_format((float) $m['ticket_promedio'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-black text-base text-on-surface">
                                        ${{ number_format((float) $m['total_con_propina'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-on-surface-variant italic">
                                        Sin actividad de meseros registrada en este rango de fechas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(!empty($resumen_meseros['meseros']))
                            <tfoot>
                                <tr class="border-t-2 border-outline-variant/30 bg-surface-container-low font-black text-xs text-on-surface">
                                    <td class="py-3 px-3 uppercase tracking-wider">TOTAL SALÓN</td>
                                    <td class="py-3 px-3 text-center">{{ $resumen_meseros['totales']['total_mesas_activas'] }} activas</td>
                                    <td class="py-3 px-3 text-right font-mono">{{ $resumen_meseros['totales']['total_comandas'] }}</td>
                                    <td class="py-3 px-3 text-right">${{ number_format((float) $resumen_meseros['totales']['total_ventas_netas'], 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right text-secondary">${{ number_format((float) $resumen_meseros['totales']['total_propinas'], 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-center">—</td>
                                    <td class="py-3 px-3 text-right font-mono">${{ number_format((float) $resumen_meseros['totales']['ticket_promedio_general'], 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right text-base text-primary">${{ number_format((float) $resumen_meseros['totales']['total_con_propinas'], 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endif
    @endif

    @if ($pestana === 'clientes')
        <!-- Clientes tab -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                    <span class="material-symbols-outlined text-[18px] text-primary">group</span>
                    Top clientes
                </h3>
                <div class="space-y-2">
                    @forelse ($datos['topClientes'] as $fila)
                        <div class="flex items-center justify-between rounded-2xl bg-surface-container-low px-4 py-3">
                            <div>
                                <span class="text-xs font-bold text-on-surface block">{{ $fila['cliente'] }}</span>
                                <span class="text-[10px] font-mono text-on-surface-variant">{{ $fila['visitas'] }} visitas</span>
                            </div>
                            <span class="text-sm font-black text-secondary">${{ number_format((float) $fila['gastado'], 0, ',','.') }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-on-surface-variant italic py-4">Sin clientes con compras en el período.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                    <span class="material-symbols-outlined text-[18px] text-primary">delivery_dining</span>
                    Tiempos de entrega (delivery)
                </h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Promedio</span>
                        <p class="mt-1 text-xl font-black text-on-surface">{{ $datos['tiempos']['promedio_min'] }} min</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Entregados</span>
                        <p class="mt-1 text-xl font-black text-on-surface">{{ $datos['tiempos']['entregados'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Más rápido</span>
                        <p class="mt-1 text-xl font-black text-secondary">{{ $datos['tiempos']['min_min'] }} min</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Más lento</span>
                        <p class="mt-1 text-xl font-black text-error">{{ $datos['tiempos']['max_min'] }} min</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($pestana === 'reservas')
        <!-- Reservas tab -->
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-primary">event_available</span>
                Resumen de reservas
            </h3>
            @if ($resumen_reservas)
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Total</span>
                        <p class="mt-1 text-xl font-black text-on-surface">{{ $resumen_reservas['total'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Confirmadas</span>
                        <p class="mt-1 text-xl font-black text-secondary">{{ $resumen_reservas['confirmadas'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Canceladas</span>
                        <p class="mt-1 text-xl font-black text-error">{{ $resumen_reservas['canceladas'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">No-shows</span>
                        <p class="mt-1 text-xl font-black text-error">{{ $resumen_reservas['no_shows'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container-low p-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Cumplimiento</span>
                        <p class="mt-1 text-xl font-black text-primary">{{ $resumen_reservas['cumplimiento_porcentaje'] }}%</p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($pestana === 'estado')

    <!-- Bento KPIs -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ventas netas</span>
            <span class="mt-2 flex items-center gap-1 text-2xl font-black text-on-surface">
                <span class="material-symbols-outlined text-[20px] text-primary">receipt_long</span>
                ${{ number_format((float) $resultado['ingresos']['ventas_netas'], 0, ',','.') }}
            </span>
        </div>
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Total ingresos</span>
            <span class="mt-2 flex items-center gap-1 text-2xl font-black text-secondary">
                <span class="material-symbols-outlined text-[20px]">trending_up</span>
                ${{ number_format((float) $resultado['ingresos']['total'], 0, ',','.') }}
            </span>
        </div>
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Total gastos</span>
            <span class="mt-2 flex items-center gap-1 text-2xl font-black text-error">
                <span class="material-symbols-outlined text-[20px]">trending_down</span>
                ${{ number_format((float) $resultado['gastos']['total'], 0, ',','.') }}
            </span>
        </div>
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Resultado neto</span>
            <span class="mt-2 flex items-center gap-1 text-2xl font-black {{ $resultado['resultado_neto'] >= 0 ? 'text-secondary' : 'text-error' }}">
                <span class="material-symbols-outlined text-[20px]">{{ $resultado['resultado_neto'] >= 0 ? 'account_balance' : 'account_balance_wallet' }}</span>
                ${{ number_format((float) $resultado['resultado_neto'], 0, ',','.') }}
            </span>
        </div>
    </div>

    <!-- State of Results Detail -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-secondary">trending_up</span>
                Ingresos por cuenta
            </h3>
            <div class="space-y-2">
                @foreach($resultado['detalle']['ingresos'] as $linea)
                    <div class="flex items-center justify-between rounded-2xl bg-surface-container-low px-4 py-3">
                        <div>
                            <span class="text-xs font-bold text-on-surface block">{{ $linea['cuenta'] }}</span>
                            <span class="text-[10px] font-mono text-on-surface-variant">{{ $linea['movimientos'] }} movimientos</span>
                        </div>
                        <span class="text-sm font-black text-secondary">${{ number_format((float) $linea['total'], 0, ',','.') }}</span>
                    </div>
                @endforeach
                @if(empty($resultado['detalle']['ingresos']))
                    <p class="text-xs text-on-surface-variant italic py-4">Sin ingresos registrados en el período.</p>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-error">trending_down</span>
                Gastos por cuenta
            </h3>
            <div class="space-y-2">
                @foreach($resultado['detalle']['gastos'] as $linea)
                    <div class="flex items-center justify-between rounded-2xl bg-surface-container-low px-4 py-3">
                        <div>
                            <span class="text-xs font-bold text-on-surface block">{{ $linea['cuenta'] }}</span>
                            <span class="text-[10px] font-mono text-on-surface-variant">{{ $linea['movimientos'] }} movimientos</span>
                        </div>
                        <span class="text-sm font-black text-error">${{ number_format((float) $linea['total'], 0, ',','.') }}</span>
                    </div>
                @endforeach
                @if(empty($resultado['detalle']['gastos']))
                    <p class="text-xs text-on-surface-variant italic py-4">Sin gastos registrados en el período.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Movements -->
    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
        <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-[18px] text-primary">receipt</span>
            Movimientos contables recientes
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-outline-variant/15 text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                        <th class="py-2.5 px-3">Fecha</th>
                        <th class="py-2.5 px-3">Tipo</th>
                        <th class="py-2.5 px-3">Cuenta</th>
                        <th class="py-2.5 px-3">Concepto</th>
                        <th class="py-2.5 px-3 text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10 font-medium">
                    @forelse($movimientos as $mov)
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td class="py-2.5 px-3 font-mono text-on-surface-variant">{{ $mov->fecha->format('d/m/Y') }}</td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $mov->tipo === 'ingreso' ? 'bg-secondary/15 text-secondary' : 'bg-error/15 text-error' }}">
                                    {{ $mov->tipo }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-on-surface">{{ $mov->cuenta }}</td>
                            <td class="py-2.5 px-3 text-on-surface-variant">{{ $mov->concepto }}</td>
                            <td class="py-2.5 px-3 text-right font-black {{ $mov->tipo === 'ingreso' ? 'text-secondary' : 'text-error' }}">
                                ${{ number_format((float) $mov->monto, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-on-surface-variant">Sin movimientos contables todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @endif

    @if ($pestana === 'contabilidad' && $libroFiscal)
        <!-- Tab Contabilidad & DIAN (Art. 616-1 E.T. / Siigo / Alegra / World Office / Helisa) -->
        <div class="space-y-6 animate-fade-in">
            <!-- Header Informativo y Resumen Rápido -->
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-6 border-b border-outline-variant/15">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[26px] text-primary">account_balance</span>
                            <h2 class="text-lg font-extrabold text-on-surface">Módulo Contable & Cumplimiento Tributario DIAN</h2>
                            <span class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                Partida Doble Balanceada
                            </span>
                        </div>
                        <p class="text-xs text-on-surface-variant mt-1">
                            Exportaciones automáticas para software contable de tu contador (Siigo, Alegra, World Office, Helisa) y generación legal del <strong>Libro Fiscal de Operaciones Diarias (Art. 616-1 del E.T.)</strong>.
                        </p>
                    </div>

                    <!-- Botones de Acción Directa del Libro Fiscal -->
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('reportes.contable.libro-fiscal', ['desde' => $desde, 'hasta' => $hasta, 'formato' => 'pdf']) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-primary text-on-primary text-xs font-black shadow-md hover:bg-primary-container transition-colors">
                            <span class="material-symbols-outlined text-[18px]">picture_as_pdf</span>
                            <span>Libro Fiscal DIAN (PDF)</span>
                        </a>
                        <a href="{{ route('reportes.contable.libro-fiscal', ['desde' => $desde, 'hasta' => $hasta, 'formato' => 'csv']) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-2xl bg-surface-container text-on-surface text-xs font-bold border border-outline-variant/30 hover:bg-surface-container-high transition-colors">
                            <span class="material-symbols-outlined text-[18px]">table_chart</span>
                            <span>Libro Fiscal (CSV)</span>
                        </a>
                    </div>
                </div>

                <!-- Tarjetas KPI del Período Fiscal -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 pt-6">
                    <div class="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/15">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Operaciones</span>
                        <div class="text-base font-black text-on-surface mt-1">{{ number_format($libroFiscal['totales']['total_operaciones']) }}</div>
                        <span class="text-[10px] text-on-surface-variant">{{ $libroFiscal['totales']['dias_con_movimiento'] }} días activos</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/15">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ingresos Brutos</span>
                        <div class="text-base font-black text-on-surface mt-1">${{ number_format($libroFiscal['totales']['ingresos_brutos'], 0, ',', '.') }}</div>
                        <span class="text-[10px] text-on-surface-variant">Total ventas</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/15">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Base Gravable</span>
                        <div class="text-base font-black text-primary mt-1">${{ number_format($libroFiscal['totales']['base_gravable'], 0, ',', '.') }}</div>
                        <span class="text-[10px] text-on-surface-variant">Cuenta PUC 4135</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/15">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">INC (8%)</span>
                        <div class="text-base font-black text-secondary mt-1">${{ number_format($libroFiscal['totales']['impuesto_consumo_inc'], 0, ',', '.') }}</div>
                        <span class="text-[10px] text-on-surface-variant">Cuenta PUC 2495</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/15">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Devoluciones</span>
                        <div class="text-base font-black text-error mt-1">-${{ number_format($libroFiscal['totales']['total_devoluciones'], 0, ',', '.') }}</div>
                        <span class="text-[10px] text-error font-medium">Reversión 4175</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/15">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ingresos Netos</span>
                        <div class="text-base font-black text-emerald-600 dark:text-emerald-400 mt-1">${{ number_format($libroFiscal['totales']['ingresos_netos'], 0, ',', '.') }}</div>
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">Ventas netas</span>
                    </div>
                </div>
            </div>

            <!-- Grid de Exportación para Softwares Contables Colombianos -->
            <div>
                <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-[18px] text-secondary">cloud_download</span>
                    Exportar para el Contador / Software Contable
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Siigo Nube -->
                    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 flex flex-col justify-between hover:border-primary/50 transition-all shadow-sm">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-black text-base tracking-tight text-[#00a887]">Siigo Nube</span>
                                <span class="rounded-full bg-[#00a887]/15 px-2 py-0.5 text-[9px] font-black text-[#00a887]">CC-1 / Ventas</span>
                            </div>
                            <p class="text-xs text-on-surface-variant mt-2 leading-relaxed">
                                Comprobante Contable de Ventas con partida doble perfecta. Cuentas PUC estándar: <strong>4135</strong> (Ingresos), <strong>2495</strong> (INC 8%), <strong>1105/1110</strong> (Caja/Bancos) y <strong>4175</strong> (Devoluciones).
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-outline-variant/15">
                            <a href="{{ route('reportes.contable.siigo', ['desde' => $desde, 'hasta' => $hasta]) }}"
                               class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-surface-container hover:bg-[#00a887] hover:text-white text-xs font-bold text-on-surface transition-all">
                                <span class="material-symbols-outlined text-[16px]">download</span>
                                <span>Descargar Plantilla Siigo</span>
                            </a>
                        </div>
                    </div>

                    <!-- Alegra -->
                    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 flex flex-col justify-between hover:border-primary/50 transition-all shadow-sm">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-black text-base tracking-tight text-[#00b4d8]">Alegra</span>
                                <span class="rounded-full bg-[#00b4d8]/15 px-2 py-0.5 text-[9px] font-black text-[#00b4d8]">Facturas Masivas</span>
                            </div>
                            <p class="text-xs text-on-surface-variant mt-2 leading-relaxed">
                                Formato de importación de facturas de venta e ingresos con desglose por ítem, precio unitario, cantidad neta vendida, impuesto INC y método de pago.
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-outline-variant/15">
                            <a href="{{ route('reportes.contable.alegra', ['desde' => $desde, 'hasta' => $hasta]) }}"
                               class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-surface-container hover:bg-[#00b4d8] hover:text-white text-xs font-bold text-on-surface transition-all">
                                <span class="material-symbols-outlined text-[16px]">download</span>
                                <span>Descargar Plantilla Alegra</span>
                            </a>
                        </div>
                    </div>

                    <!-- World Office -->
                    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 flex flex-col justify-between hover:border-primary/50 transition-all shadow-sm">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-black text-base tracking-tight text-[#1d3557]">World Office</span>
                                <span class="rounded-full bg-[#1d3557]/15 px-2 py-0.5 text-[9px] font-black text-[#1d3557] dark:text-blue-300">Archivo Plano (;)</span>
                            </div>
                            <p class="text-xs text-on-surface-variant mt-2 leading-relaxed">
                                Archivo plano estructurado con delimitador punto y coma (;) compatible con el módulo de integración y migración de comprobantes de World Office.
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-outline-variant/15">
                            <a href="{{ route('reportes.contable.world-office', ['desde' => $desde, 'hasta' => $hasta]) }}"
                               class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-surface-container hover:bg-[#1d3557] hover:text-white text-xs font-bold text-on-surface transition-all">
                                <span class="material-symbols-outlined text-[16px]">text_snippet</span>
                                <span>Descargar Plano World Office</span>
                            </a>
                        </div>
                    </div>

                    <!-- Helisa -->
                    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 flex flex-col justify-between hover:border-primary/50 transition-all shadow-sm">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-black text-base tracking-tight text-[#7209b7]">Helisa</span>
                                <span class="rounded-full bg-[#7209b7]/15 px-2 py-0.5 text-[9px] font-black text-[#7209b7] dark:text-purple-300">Comprobantes (|)</span>
                            </div>
                            <p class="text-xs text-on-surface-variant mt-2 leading-relaxed">
                                Archivo plano delimitado por tubería (|) y fechas en formato AAAAMMDD para importación de comprobantes de diario en Helisa Norma Local y NIIF.
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-outline-variant/15">
                            <a href="{{ route('reportes.contable.helisa', ['desde' => $desde, 'hasta' => $hasta]) }}"
                               class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-surface-container hover:bg-[#7209b7] hover:text-white text-xs font-bold text-on-surface transition-all">
                                <span class="material-symbols-outlined text-[16px]">text_snippet</span>
                                <span>Descargar Plano Helisa</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Detallada del Libro Fiscal DIAN (Art. 616-1 E.T.) -->
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-primary">menu_book</span>
                            Libro Fiscal de Operaciones Diarias · Detalle Jornada a Jornada
                        </h3>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">
                            Registro cronológico con numeración consecutiva inicial y final de cada día, exigido legalmente por la DIAN.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-outline-variant/15">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant/15 text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low font-extrabold">
                                <th class="py-3 px-3 text-center">Fecha</th>
                                <th class="py-3 px-3">Comp. Inicial</th>
                                <th class="py-3 px-3">Comp. Final</th>
                                <th class="py-3 px-3 text-center">Operaciones</th>
                                <th class="py-3 px-3 text-right">Ingresos Brutos</th>
                                <th class="py-3 px-3 text-right">Base Gravable</th>
                                <th class="py-3 px-3 text-right">INC (8%)</th>
                                <th class="py-3 px-3 text-right">Devoluciones</th>
                                <th class="py-3 px-3 text-right">Ingresos Netos</th>
                                <th class="py-3 px-3 text-right">Gastos Caja</th>
                                <th class="py-3 px-3 text-right">Saldo Diario</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10 font-medium">
                            @forelse($libroFiscal['dias'] as $dia)
                                <tr class="hover:bg-surface-container-low/50 transition-colors {{ $dia['total_operaciones'] > 0 ? '' : 'opacity-60' }}">
                                    <td class="py-2.5 px-3 text-center font-mono text-on-surface font-bold">{{ $dia['fecha_formateada'] }}</td>
                                    <td class="py-2.5 px-3 font-mono text-on-surface-variant text-[11px]">{{ $dia['comprobante_inicial'] }}</td>
                                    <td class="py-2.5 px-3 font-mono text-on-surface-variant text-[11px]">{{ $dia['comprobante_final'] }}</td>
                                    <td class="py-2.5 px-3 text-center font-black">{{ $dia['total_operaciones'] }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono">${{ number_format($dia['ingresos_brutos'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono">${{ number_format($dia['base_gravable'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono text-secondary">${{ number_format($dia['impuesto_consumo_inc'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono {{ $dia['total_devoluciones'] > 0 ? 'text-error font-black' : 'text-on-surface-variant' }}">
                                        {{ $dia['total_devoluciones'] > 0 ? '-$'.number_format($dia['total_devoluciones'], 0, ',', '.') : '$0' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-black text-on-surface">${{ number_format($dia['ingresos_netos'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono text-on-surface-variant">${{ number_format($dia['gastos_diarios_caja'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-black {{ $dia['saldo_neto_fiscal'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-error' }}">
                                        ${{ number_format($dia['saldo_neto_fiscal'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="py-8 text-center text-on-surface-variant">Sin datos en el período seleccionado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t-2 border-outline-variant/30 bg-surface-container font-black text-xs">
                            <tr>
                                <td colspan="3" class="py-3 px-3 uppercase text-on-surface">TOTALES DEL PERÍODO</td>
                                <td class="py-3 px-3 text-center text-primary">{{ number_format($libroFiscal['totales']['total_operaciones']) }}</td>
                                <td class="py-3 px-3 text-right font-mono">${{ number_format($libroFiscal['totales']['ingresos_brutos'], 0, ',', '.') }}</td>
                                <td class="py-3 px-3 text-right font-mono">${{ number_format($libroFiscal['totales']['base_gravable'], 0, ',', '.') }}</td>
                                <td class="py-3 px-3 text-right font-mono text-secondary">${{ number_format($libroFiscal['totales']['impuesto_consumo_inc'], 0, ',', '.') }}</td>
                                <td class="py-3 px-3 text-right font-mono text-error">-${{ number_format($libroFiscal['totales']['total_devoluciones'], 0, ',', '.') }}</td>
                                <td class="py-3 px-3 text-right font-mono text-on-surface">${{ number_format($libroFiscal['totales']['ingresos_netos'], 0, ',', '.') }}</td>
                                <td class="py-3 px-3 text-right font-mono text-on-surface-variant">${{ number_format($libroFiscal['totales']['gastos_diarios_caja'], 0, ',', '.') }}</td>
                                <td class="py-3 px-3 text-right font-mono text-emerald-600 dark:text-emerald-400">${{ number_format($libroFiscal['totales']['saldo_neto_fiscal'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/15 text-[11px] text-on-surface-variant flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-[18px] text-primary shrink-0 mt-0.5">verified_user</span>
                    <div>
                        <strong class="text-on-surface">Cumplimiento DIAN Garantizado:</strong>
                        Este reporte calcula automáticamente la base gravable y el Impuesto Nacional al Consumo (INC 8%) bajo el Art. 512-1 del Estatuto Tributario, e integra las devoluciones de ítems y comprobantes de reversión contable para evitar cualquier tipo de descuadre fiscal.
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>