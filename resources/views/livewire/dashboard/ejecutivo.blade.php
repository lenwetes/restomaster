<?php

use App\Services\DashboardService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $periodo = 'hoy';

    public function setPeriodo(string $periodo): void
    {
        if (in_array($periodo, ['hoy', 'ayer', 'semana', 'mes'], true)) {
            $this->periodo = $periodo;
        }
    }

    public function with(): array
    {
        $service = app(DashboardService::class);

        return [
            'kpis' => $service->kpisGenerales($this->periodo),
            'ventasHora' => $service->ventasPorHora($this->periodo),
            'tendenciaSemanal' => $service->tendenciaUltimos7Dias(),
            'mix' => $service->mixCanalesYMetodos($this->periodo),
            'inventarioAlertas' => $service->insumosEnAlerta(8),
            'topPlatos' => $service->topProductos($this->periodo, 5),
            'rankingMeseros' => $service->rankingMeseros($this->periodo),
            'operativo' => $service->pulsoOperativo(),
        ];
    }
}; ?>

<div wire:poll.60s class="space-y-6">
    <!-- ENCABEZADO EJECUTIVO & SELECTOR DE PERÍODO -->
    <div class="w-full bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-surface-container-highest flex flex-col xl:flex-row items-start xl:items-center justify-between gap-5 transition-all">
        <div class="flex flex-col gap-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container text-xs font-bold tracking-wide">
                    SEDE EL POBLADO · MEDELLÍN
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-secondary font-bold">
                    <span class="h-2.5 w-2.5 rounded-full bg-secondary animate-pulse"></span>
                    Centro de Comando en Vivo
                </span>
                <span class="text-[11px] text-on-surface-variant font-mono opacity-80">
                    • Auto-refresco 60s
                </span>
            </div>

            <h1 class="text-2xl lg:text-3xl font-black text-on-surface tracking-tight">
                Panel Ejecutivo RestoMaster
            </h1>

            <div class="flex items-center gap-2 text-on-surface-variant text-xs sm:text-sm font-medium">
                <span class="material-symbols-outlined text-[18px] text-primary">schedule</span>
                <span class="font-bold text-on-surface">{{ ucfirst(now()->locale('es')->translatedFormat('l, d \d\e F \d\e Y')) }}</span>
                <span class="text-outline-variant">•</span>
                <span class="text-xs text-on-surface-variant">Vista filtrada: <strong class="text-primary uppercase">{{ $kpis['periodo_etiqueta'] }}</strong></span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
            <!-- Selector Rápido de Períodos -->
            <div class="inline-flex p-1 rounded-2xl bg-surface-container border border-surface-container-highest shadow-2xs">
                @foreach (['hoy' => 'Hoy', 'ayer' => 'Ayer', 'semana' => 'Esta Semana', 'mes' => 'Este Mes'] as $key => $lbl)
                    <button 
                        type="button"
                        wire:click="setPeriodo('{{ $key }}')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-200 cursor-pointer {{ $periodo === $key ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}"
                    >
                        {{ $lbl }}
                    </button>
                @endforeach
            </div>

            <!-- Accesos Operativos Clave -->
            <div class="flex items-center gap-2">
                <a 
                    href="{{ route('pos') }}" 
                    wire:navigate
                    class="h-10 px-4 rounded-xl bg-primary hover:bg-primary/90 text-on-primary font-bold text-xs flex items-center gap-1.5 shadow-sm active:scale-95 transition-all"
                    title="Nueva Comanda POS"
                >
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    <span>+ Comanda</span>
                </a>
                <a 
                    href="{{ route('mesas') }}" 
                    wire:navigate
                    class="h-10 px-3.5 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-bold text-xs flex items-center gap-1.5 border border-surface-container-highest active:scale-95 transition-all"
                    title="Ver Plano de Mesas"
                >
                    <span class="material-symbols-outlined text-[18px] text-primary">table_restaurant</span>
                    <span class="hidden sm:inline">Mesas</span>
                </a>
                <a 
                    href="{{ route('caja') }}" 
                    wire:navigate
                    class="h-10 px-3.5 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-bold text-xs flex items-center gap-1.5 border border-surface-container-highest active:scale-95 transition-all"
                    title="Arqueo de Caja"
                >
                    <span class="material-symbols-outlined text-[18px] text-secondary">payments</span>
                    <span class="hidden sm:inline">Caja</span>
                </a>
            </div>
        </div>
    </div>

    <!-- BENTO GRID DE 4 KPIS EJECUTIVOS CON COMPARATIVAS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Ventas Facturadas -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ventas Facturadas</span>
                    <div class="text-2xl sm:text-3xl font-black text-on-surface mt-1.5 tracking-tight font-mono">
                        ${{ number_format($kpis['ventas'], 0, ',', '.') }}
                    </div>
                </div>
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 border border-primary/20 text-primary shrink-0">
                    <span class="material-symbols-outlined text-[24px]">monetization_on</span>
                </span>
            </div>

            <div class="mt-4 pt-3 border-t border-surface-container flex items-center justify-between text-xs">
                <span class="text-on-surface-variant font-medium">
                    {{ $kpis['transacciones'] }} transacciones
                </span>
                @if ($kpis['variacion_ventas_pct'] >= 0)
                    <span class="inline-flex items-center gap-0.5 text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span>
                        +{{ $kpis['variacion_ventas_pct'] }}%
                    </span>
                @else
                    <span class="inline-flex items-center gap-0.5 text-xs font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200">
                        <span class="material-symbols-outlined text-[14px]">trending_down</span>
                        {{ $kpis['variacion_ventas_pct'] }}%
                    </span>
                @endif
            </div>
        </div>

        <!-- KPI 2: Ticket Promedio -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ticket Promedio</span>
                    <div class="text-2xl sm:text-3xl font-black text-on-surface mt-1.5 tracking-tight font-mono">
                        ${{ number_format($kpis['ticket_promedio'], 0, ',', '.') }}
                    </div>
                </div>
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-700 shrink-0">
                    <span class="material-symbols-outlined text-[24px]">receipt_long</span>
                </span>
            </div>

            <div class="mt-4 pt-3 border-t border-surface-container flex items-center justify-between text-xs">
                <span class="text-on-surface-variant font-medium">Gasto medio / comensal</span>
                <span class="font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200 text-[11px]">
                    Optimizado
                </span>
            </div>
        </div>

        <!-- KPI 3: Food Cost % -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant">Food Cost Estimado</span>
                    <div class="text-2xl sm:text-3xl font-black {{ $kpis['food_cost_pct'] > 35 ? 'text-rose-600' : 'text-emerald-700' }} mt-1.5 tracking-tight font-mono">
                        {{ $kpis['food_cost_pct'] }}%
                    </div>
                </div>
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-secondary-container/40 border border-secondary/30 text-secondary shrink-0">
                    <span class="material-symbols-outlined text-[24px]">restaurant_menu</span>
                </span>
            </div>

            <div class="mt-4 pt-3 border-t border-surface-container flex items-center justify-between text-xs">
                <span class="text-on-surface-variant font-medium">Costo insumos: ${{ number_format($kpis['costo_vendido'], 0, ',', '.') }}</span>
                <span class="font-bold {{ $kpis['food_cost_pct'] <= 35 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-rose-700 bg-rose-50 border-rose-200' }} px-2 py-0.5 rounded-full border text-[11px]">
                    {{ $kpis['food_cost_pct'] <= 35 ? 'Saludable (<35%)' : 'Alerta Costo' }}
                </span>
            </div>
        </div>

        <!-- KPI 4: Margen Bruto -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant">Margen Bruto de Operación</span>
                    <div class="text-2xl sm:text-3xl font-black text-secondary mt-1.5 tracking-tight font-mono">
                        ${{ number_format($kpis['margen_bruto'], 0, ',', '.') }}
                    </div>
                </div>
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 shrink-0">
                    <span class="material-symbols-outlined text-[24px]">query_stats</span>
                </span>
            </div>

            <div class="mt-4 pt-3 border-t border-surface-container flex items-center justify-between text-xs">
                <span class="text-on-surface-variant font-medium">Margen sobre ventas</span>
                <span class="font-bold text-secondary bg-secondary-container/30 px-2 py-0.5 rounded-full border border-secondary/20 text-[11px]">
                    {{ $kpis['margen_bruto_pct'] }}% neto
                </span>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DE GRÁFICOS Y ANALÍTICA DE VENTAS (2 COLUMNAS) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- GRÁFICO A: CURVA DE VENTAS Y PICOS POR HORA (2 COLUMNAS) -->
        <div class="lg:col-span-2 bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-surface-container-highest flex flex-col justify-between">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[22px]">show_chart</span>
                        <h2 class="text-base font-black text-on-surface tracking-tight">Curva de Ventas & Picos por Hora</h2>
                    </div>
                    <p class="text-xs text-on-surface-variant mt-0.5">Demanda de sala e ingresos fraccionados por hora operativa</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1.5 font-bold text-on-surface-variant">
                        <span class="h-3 w-3 rounded-md bg-gradient-to-t from-primary to-rose-400"></span> Ventas ($)
                    </span>
                    <span class="inline-flex items-center gap-1.5 font-bold text-on-surface-variant">
                        <span class="h-1.5 w-1.5 rounded-full bg-secondary"></span> Transacciones
                    </span>
                </div>
            </div>

            <!-- Gráfico de Barras SVG Interactivo Responsivo -->
            <div class="relative w-full h-56 pt-4 pb-2 flex items-end justify-between gap-1 sm:gap-2">
                @php
                    $maxVentaHora = max(1.0, (float) collect($ventasHora)->max('ventas'));
                @endphp

                @foreach ($ventasHora as $vh)
                    @php
                        $barHeight = max(4, $vh['pct_altura']);
                        $esPico = $vh['ventas'] > 0 && $vh['ventas'] === $maxVentaHora;
                        $alturaStyle = "height: {$barHeight}%;";
                    @endphp
                    <div class="flex-1 flex flex-col items-center h-full justify-end group relative" title="{{ $vh['hora'] }}: ${{ number_format($vh['ventas'], 0, ',', '.') }} ({{ $vh['transacciones'] }} pedidos)">
                        <!-- Tooltip Hover Flotante -->
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none absolute -top-12 z-20 whitespace-nowrap rounded-xl bg-stone-900 text-white text-[10px] font-bold px-2.5 py-1 shadow-lg">
                            <span class="text-rose-300 font-mono">${{ number_format($vh['ventas'], 0, ',', '.') }}</span>
                            <span class="text-stone-400 font-normal">· {{ $vh['transacciones'] }} ped.</span>
                        </div>

                        <!-- Barra de Venta -->
                        <div 
                            class="w-full max-w-[28px] rounded-t-lg transition-all duration-500 cursor-pointer {{ $esPico ? 'bg-gradient-to-t from-rose-600 to-primary ring-2 ring-primary/40' : ($vh['ventas'] > 0 ? 'bg-gradient-to-t from-primary/80 to-primary/40 hover:from-primary hover:to-rose-400' : 'bg-surface-container-high/40') }}"
                            @style([$alturaStyle])
                        ></div>

                        <!-- Indicador de Punto de Transacción -->
                        @if ($vh['transacciones'] > 0)
                            <span class="h-1.5 w-1.5 rounded-full bg-secondary -mt-1 z-10"></span>
                        @endif

                        <!-- Etiqueta de Hora -->
                        <span class="mt-2 text-[10px] font-mono text-on-surface-variant {{ $esPico ? 'font-black text-primary' : '' }}">
                            {{ substr($vh['hora'], 0, 2) }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-3 border-t border-surface-container flex flex-wrap items-center justify-between text-xs text-on-surface-variant">
                <span>Franja matutina (08:00) → Turno almuerzo (12:00-15:00) → Cena (19:00-23:00)</span>
                <span class="font-bold text-primary flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px]">tips_and_updates</span>
                    Pico de facturación resaltado en rojo brasa
                </span>
            </div>
        </div>

        <!-- GRÁFICO B: TENDENCIA 7 DÍAS & MIX DE CANALES (1 COLUMNA) -->
        <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-surface-container-highest flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-secondary text-[22px]">date_range</span>
                        <h2 class="text-base font-black text-on-surface tracking-tight">Últimos 7 Días</h2>
                    </div>
                    <span class="text-xs font-mono font-bold text-secondary bg-secondary-container/40 px-2 py-0.5 rounded-full border border-secondary/20">
                        Semana Móvil
                    </span>
                </div>

                <!-- Mini Barras de la Semana -->
                <div class="space-y-2 mb-6">
                    @foreach ($tendenciaSemanal['dias'] as $d)
                        @php
                            $widthBar = max(6, $d['pct_altura']);
                            $barWidthStyle = "width: {$widthBar}%;";
                        @endphp
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-16 font-bold text-on-surface-variant truncate text-[11px]">{{ $d['dia_nombre'] }}</span>
                            <div class="flex-1 bg-surface-container h-3 rounded-full overflow-hidden">
                                <div class="bg-secondary h-full rounded-full transition-all duration-500" @style([$barWidthStyle])></div>
                            </div>
                            <span class="w-20 text-right font-mono font-bold text-on-surface text-[11px]">
                                ${{ number_format($d['ventas'], 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="pt-3 border-t border-surface-container flex items-center justify-between text-xs">
                    <span class="text-on-surface-variant font-medium">Promedio Diario:</span>
                    <span class="font-mono font-black text-on-surface">${{ number_format($tendenciaSemanal['promedio_diario'], 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- MIX DE CANALES Y MÉTODOS DE PAGO -->
            <div class="mt-6 pt-5 border-t border-surface-container">
                <span class="text-[11px] font-black uppercase tracking-wider text-on-surface-variant block mb-2">
                    Mix por Canales de Venta
                </span>
                
                <!-- Barra Segmentada Visual -->
                <div class="h-3 w-full rounded-full bg-surface-container flex overflow-hidden mb-3">
                    @foreach ($mix['canales'] as $cn)
                        @if ($cn['pct'] > 0)
                            @php $cnStyle = "width: {$cn['pct']}%;"; @endphp
                            <div class="{{ $cn['color'] }} h-full transition-all duration-500" @style([$cnStyle]) title="{{ $cn['nombre'] }}: {{ $cn['pct'] }}%"></div>
                        @endif
                    @endforeach
                </div>

                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    @foreach ($mix['canales'] as $cn)
                        <div class="flex flex-col items-center p-1.5 rounded-xl bg-surface-container-low">
                            <span class="text-[10px] font-bold text-on-surface-variant truncate w-full">{{ $cn['nombre'] }}</span>
                            <span class="font-mono font-black text-on-surface text-xs mt-0.5">{{ $cn['pct'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN OPERATIVA: INVENTARIO, PLATOS ESTRELLA & RANKING DE PERSONAL (3 COLUMNAS) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- MÓDULO PRIMARIO DE INVENTARIO: INSUMOS AGOTADOS & STOCK CRÍTICO (1 COLUMNA) -->
        <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-surface-container-highest flex flex-col justify-between">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 border border-rose-500/20">
                            <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-black text-on-surface tracking-tight">Alerta Primaria de Inventario</h2>
                                @if ($inventarioAlertas['total_alertas'] > 0)
                                    <span class="px-2.5 py-0.5 rounded-full bg-rose-600 text-white text-[11px] font-black animate-pulse">
                                        {{ $inventarioAlertas['total_alertas'] }} en Riesgo
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-600 text-white text-[11px] font-bold">
                                        Stock Óptimo
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-on-surface-variant mt-0.5">Insumos agotados y bajo stock mínimo con impacto directo en recetas</p>
                        </div>
                    </div>

                    <a 
                        href="{{ route('inventario') }}" 
                        wire:navigate
                        class="px-3 py-1.5 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-bold text-xs flex items-center gap-1 border border-surface-container-highest transition-colors"
                    >
                        <span>Abrir Kardex</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>

                <!-- Lista de Insumos Críticos -->
                @if ($inventarioAlertas['total_alertas'] > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-surface-container-highest text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low/60">
                                    <th class="py-2.5 px-3 rounded-l-xl">Insumo</th>
                                    <th class="py-2.5 px-3">Estado</th>
                                    <th class="py-2.5 px-3 text-right">Stock Actual</th>
                                    <th class="py-2.5 px-3 text-right">Mínimo</th>
                                    <th class="py-2.5 px-3">Proveedor Habitual</th>
                                    <th class="py-2.5 px-3 text-right rounded-r-xl">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-container font-medium">
                                <!-- 1. Insumos Agotados (Stock 0) -->
                                @foreach ($inventarioAlertas['agotados'] as $ag)
                                    <tr class="hover:bg-rose-50/40 transition-colors">
                                        <td class="py-2.5 px-3 font-bold text-on-surface">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2 w-2 rounded-full bg-rose-600"></span>
                                                <span>{{ $ag->nombre }}</span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-black uppercase">
                                                <span class="material-symbols-outlined text-[12px]">warning</span>
                                                Agotado
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-black text-rose-700">
                                            0.00 {{ $ag->unidad_medida }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-on-surface-variant">
                                            {{ $ag->stock_minimo }} {{ $ag->unidad_medida }}
                                        </td>
                                        <td class="py-2.5 px-3 text-on-surface-variant text-[11px]">
                                            {{ $ag->proveedor?->nombre_contacto ?? ($ag->proveedor_nombre ?: 'Sin proveedor') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right">
                                            <a 
                                                href="{{ route('proveedores') }}" 
                                                wire:navigate 
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-800 font-bold text-[11px] transition-colors"
                                            >
                                                Pedir
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach

                                <!-- 2. Insumos en Riesgo (Stock <= Mínimo) -->
                                @foreach ($inventarioAlertas['criticos'] as $cr)
                                    <tr class="hover:bg-amber-50/40 transition-colors">
                                        <td class="py-2.5 px-3 font-bold text-on-surface">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                                <span>{{ $cr->nombre }}</span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-extrabold uppercase border border-amber-200">
                                                Stock Bajo
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-amber-800">
                                            {{ $cr->stock_actual }} {{ $cr->unidad_medida }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-on-surface-variant">
                                            {{ $cr->stock_minimo }} {{ $cr->unidad_medida }}
                                        </td>
                                        <td class="py-2.5 px-3 text-on-surface-variant text-[11px]">
                                            {{ $cr->proveedor?->nombre_contacto ?? ($cr->proveedor_nombre ?: 'Sin proveedor') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right">
                                            <a 
                                                href="{{ route('proveedores') }}" 
                                                wire:navigate 
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-[11px] transition-colors"
                                            >
                                                Reponer
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-10 text-center text-on-surface-variant flex flex-col items-center justify-center">
                        <span class="material-symbols-outlined text-[38px] text-emerald-600 mb-1">verified</span>
                        <p class="font-bold text-sm text-on-surface">Inventario en Nivel Óptimo</p>
                        <p class="text-xs text-on-surface-variant">No hay insumos agotados ni por debajo del stock de seguridad.</p>
                    </div>
                @endif
            </div>

            <!-- Footer con Costo de Reposición Estimado -->
            @if ($inventarioAlertas['total_alertas'] > 0)
                <div class="mt-4 pt-3 border-t border-surface-container flex flex-wrap items-center justify-between text-xs gap-2">
                    <span class="text-on-surface-variant">
                        Costo estimado para reabastecer stock de seguridad: <strong class="font-mono text-on-surface">${{ number_format($inventarioAlertas['costo_reposicion_estimado'], 0, ',', '.') }} COP</strong>
                    </span>
                    <a 
                        href="{{ route('proveedores') }}" 
                        wire:navigate 
                        class="text-primary font-bold hover:underline flex items-center gap-1"
                    >
                        <span>Gestionar Órdenes con Proveedores</span>
                        <span class="material-symbols-outlined text-[15px]">open_in_new</span>
                    </a>
                </div>
            @endif
        </div>

        <!-- TOP 5 PLATOS MÁS VENDIDOS (1 COLUMNA) -->
        <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-surface-container-highest flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[22px]">military_tech</span>
                        <h2 class="text-base font-black text-on-surface tracking-tight">Top Platos Vendidos</h2>
                    </div>
                    <span class="text-[11px] font-bold text-on-surface-variant font-mono">Ranking en {{ $kpis['periodo_etiqueta'] }}</span>
                </div>

                <div class="space-y-3">
                    @forelse ($topPlatos as $tp)
                        @php
                            $medalColor = match ($tp['posicion']) {
                                1 => 'bg-amber-400 text-amber-950 font-black ring-2 ring-amber-300',
                                2 => 'bg-stone-300 text-stone-900 font-bold',
                                3 => 'bg-amber-700/80 text-white font-bold',
                                default => 'bg-surface-container-high text-on-surface-variant font-bold',
                            };
                            $barPlatoStyle = "width: {$tp['pct_aporte']}%;";
                        @endphp
                        <div class="p-3 rounded-2xl bg-surface-container-low border border-surface-container-highest/60 flex flex-col gap-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs shrink-0 {{ $medalColor }}">
                                        {{ $tp['posicion'] }}
                                    </span>
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-on-surface truncate block">{{ $tp['producto'] }}</span>
                                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold">{{ $tp['area_cocina'] }}</span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-mono text-xs font-black text-on-surface">${{ number_format($tp['total_ventas'], 0, ',', '.') }}</span>
                                    <span class="text-[10px] font-bold text-on-surface-variant block">{{ $tp['cantidad'] }} uds</span>
                                </div>
                            </div>
                            <!-- Barra de Aporte -->
                            <div class="w-full bg-surface-container h-1.5 rounded-full overflow-hidden">
                                <div class="bg-primary h-full rounded-full" @style([$barPlatoStyle])></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-on-surface-variant italic text-xs">
                            Sin ventas registradas en el período seleccionado.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-surface-container flex items-center justify-between text-xs text-on-surface-variant">
                <span>Calculado por volumen de facturación</span>
                <a href="{{ route('menu') }}" wire:navigate class="text-primary font-bold hover:underline">Ver Carta</a>
            </div>
        </div>

        <!-- 3. RENDIMIENTO DE MESEROS & EQUIPO EN TURNO (1 COLUMNA) -->
        <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-surface-container-highest flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-secondary/15 text-secondary border border-secondary/20">
                            <span class="material-symbols-outlined text-[20px]">badge</span>
                        </span>
                        <div>
                            <h2 class="text-base font-black text-on-surface tracking-tight">Rendimiento de Meseros</h2>
                            <p class="text-[11px] text-on-surface-variant font-medium">Ventas, comensales y propinas</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-secondary bg-secondary-container/20 px-2 py-0.5 rounded-full border border-secondary/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-secondary animate-pulse"></span>
                        {{ $rankingMeseros['total_meseros'] }} en Turno
                    </span>
                </div>

                <!-- Resumen Rápido de Personal -->
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <div class="p-2.5 rounded-2xl bg-surface-container-low border border-surface-container-highest/60 flex flex-col">
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant">Promedio / Mesero</span>
                        <span class="font-mono text-sm font-black text-on-surface mt-0.5">
                            ${{ number_format($rankingMeseros['promedio_venta'], 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="p-2.5 rounded-2xl bg-surface-container-low border border-surface-container-highest/60 flex flex-col">
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant">Propinas Período</span>
                        <span class="font-mono text-sm font-black text-secondary mt-0.5">
                            ${{ number_format($rankingMeseros['total_propinas'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <!-- Ranking Podio de Meseros -->
                <div class="space-y-3">
                    @forelse ($rankingMeseros['meseros'] as $m)
                        @php
                            $medalColor = match ($m['posicion']) {
                                1 => 'bg-amber-400 text-amber-950 font-black ring-2 ring-amber-300',
                                2 => 'bg-stone-300 text-stone-900 font-bold',
                                3 => 'bg-amber-700/80 text-white font-bold',
                                default => 'bg-surface-container-high text-on-surface-variant font-bold',
                            };
                            $barMeseroWidth = max(4, $m['pct_aporte']);
                            $barMeseroStyle = "width: {$barMeseroWidth}%;";
                        @endphp
                        <div class="p-3 rounded-2xl bg-surface-container-low border border-surface-container-highest/60 flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs shrink-0 {{ $medalColor }}">
                                        {{ $m['posicion'] }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-bold text-on-surface truncate">{{ $m['nombre'] }}</span>
                                            @if ($m['mesas_activas'] > 0)
                                                <span class="px-1.5 py-0.2 text-[9px] font-extrabold rounded-full bg-primary/10 text-primary border border-primary/20">
                                                    {{ $m['mesas_activas'] }} {{ $m['mesas_activas'] === 1 ? 'mesa' : 'mesas' }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-[10px] text-on-surface-variant block truncate">
                                            {{ $m['total_pedidos'] }} {{ $m['total_pedidos'] === 1 ? 'comanda' : 'comandas' }} · Prom. ${{ number_format($m['ticket_promedio'], 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-mono text-xs font-black text-on-surface">${{ number_format($m['total_ventas'], 0, ',', '.') }}</span>
                                    @if ($m['total_propinas'] > 0)
                                        <span class="text-[10px] font-mono font-bold text-secondary block">+${{ number_format($m['total_propinas'], 0, ',', '.') }} prop.</span>
                                    @endif
                                </div>
                            </div>
                            <!-- Barra de Desempeño / Contribución -->
                            <div class="w-full bg-surface-container h-1.5 rounded-full overflow-hidden">
                                <div class="bg-secondary h-full rounded-full" @style([$barMeseroStyle])></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-on-surface-variant italic text-xs">
                            No hay meseros registrados en esta sucursal.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-surface-container flex items-center justify-between text-xs text-on-surface-variant">
                <span>Gestión de servicio y propinas</span>
                <a href="{{ route('mesas') }}" wire:navigate class="text-primary font-bold hover:underline">Ver Salón</a>
            </div>
        </div>
    </div>

    <!-- SECCIÓN PULSO OPERATIVO EN VIVO: CAJA, KDS, MESAS Y RESERVAS (4 BENTO CARDS) -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-black text-on-surface tracking-tight">Pulso de Operaciones en Vivo</h2>
                <p class="text-xs text-on-surface-variant">Monitor en tiempo real de caja, cocina KDS, aforo y reservas</p>
            </div>
            <span class="text-xs font-mono font-bold text-secondary bg-secondary-container/40 px-2.5 py-1 rounded-full border border-secondary/20">
                Sincronización Instantánea
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. CAJA & TURNO ACTIVO -->
            <a 
                href="{{ route('caja') }}" 
                wire:navigate
                class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest hover:border-primary transition-all group flex flex-col justify-between"
            >
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-secondary-container/30 text-secondary">
                                <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                            </span>
                            <span class="text-xs font-black text-on-surface">Caja & Turno</span>
                        </div>
                        @if ($operativo['caja']['hay_turno_abierto'])
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                Abierta
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[10px] font-black uppercase">
                                Cerrada
                            </span>
                        @endif
                    </div>

                    @if ($operativo['caja']['hay_turno_abierto'])
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-xs">
                                <span class="text-on-surface-variant">Cajero:</span>
                                <span class="font-bold text-on-surface truncate max-w-[120px]">{{ $operativo['caja']['cajero_nombre'] }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-on-surface-variant">Efectivo en gaveta:</span>
                                <span class="font-mono font-black text-emerald-700">${{ number_format($operativo['caja']['ventas_efectivo'] + $operativo['caja']['monto_inicial'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-on-surface-variant">Ventas Digitales:</span>
                                <span class="font-mono font-bold text-sky-700">${{ number_format($operativo['caja']['ventas_digital'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-on-surface-variant italic py-2">No hay turno de caja abierto en este momento.</p>
                    @endif
                </div>

                <div class="mt-4 pt-2.5 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary group-hover:underline">
                    <span>Gestionar Caja</span>
                    <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>

            <!-- 2. COCINA KDS & TIEMPOS SLA -->
            <a 
                href="{{ route('cocina') }}" 
                wire:navigate
                class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest hover:border-primary transition-all group flex flex-col justify-between"
            >
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <span class="material-symbols-outlined text-[20px]">skillet</span>
                            </span>
                            <span class="text-xs font-black text-on-surface">Cocina (KDS)</span>
                        </div>
                        @if ($operativo['kds']['alerta_demora'])
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-black uppercase animate-bounce">
                                Demora
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase">
                                En Ritmo
                            </span>
                        @endif
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="text-on-surface-variant">Comandas en marcha:</span>
                            <span class="font-mono font-black text-primary text-sm">{{ $operativo['kds']['total_activas'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-on-surface-variant">Demoradas (>20 min):</span>
                            <span class="font-mono font-black {{ $operativo['kds']['demoradas'] > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ $operativo['kds']['demoradas'] }}
                            </span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-on-surface-variant">SLA Objetivo:</span>
                            <span class="font-mono font-bold text-on-surface">< 15 min</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2.5 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary group-hover:underline">
                    <span>Pantalla KDS</span>
                    <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>

            <!-- 3. SALÓN & AFORO EN MESA -->
            <a 
                href="{{ route('mesas') }}" 
                wire:navigate
                class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest hover:border-primary transition-all group flex flex-col justify-between"
            >
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-secondary-container/40 text-secondary">
                                <span class="material-symbols-outlined text-[20px]">table_restaurant</span>
                            </span>
                            <span class="text-xs font-black text-on-surface">Salón & Aforo</span>
                        </div>
                        <span class="text-xs font-mono font-bold text-secondary">
                            {{ $operativo['salon']['pct_aforo'] }}% ocupado
                        </span>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="text-on-surface-variant">Mesas activas:</span>
                            <span class="font-mono font-black text-on-surface">{{ $operativo['salon']['mesas_ocupadas'] }} de {{ $operativo['salon']['total_mesas'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-on-surface-variant">Comensales sentados:</span>
                            <span class="font-mono font-bold text-secondary">{{ $operativo['salon']['comensales_en_sala'] }} pax</span>
                        </div>
                        <!-- Mini Barra de Aforo -->
                        <div class="w-full bg-surface-container h-2 rounded-full overflow-hidden mt-1">
                            @php $aforoWidthStyle = "width: {$operativo['salon']['pct_aforo']}%;"; @endphp
                            <div class="bg-secondary h-full rounded-full transition-all duration-500" @style([$aforoWidthStyle])></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2.5 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary group-hover:underline">
                    <span>Mapa de Mesas</span>
                    <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>

            <!-- 4. RESERVAS & DELIVERY ACTIVO -->
            <a 
                href="{{ route('reservas') }}" 
                wire:navigate
                class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest hover:border-primary transition-all group flex flex-col justify-between"
            >
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-500/10 text-purple-700">
                                <span class="material-symbols-outlined text-[20px]">event</span>
                            </span>
                            <span class="text-xs font-black text-on-surface">Reservas & Flota</span>
                        </div>
                        <span class="text-[10px] font-bold text-purple-800 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200">
                            Hoy
                        </span>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="text-on-surface-variant">Reservas agendadas:</span>
                            <span class="font-mono font-black text-purple-800">{{ $operativo['reservas_hoy']->count() }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-on-surface-variant">Delivery en ruta:</span>
                            <span class="font-mono font-bold text-emerald-700">{{ $operativo['deliveries_en_ruta'] }} pedidos</span>
                        </div>
                        @if ($operativo['reservas_hoy']->isNotEmpty())
                            @php $proxRes = $operativo['reservas_hoy']->first(); @endphp
                            <div class="text-[11px] font-semibold text-on-surface-variant truncate mt-1 bg-surface-container-low px-2 py-1 rounded-lg">
                                Próx: <strong>{{ substr($proxRes->hora_llegada, 0, 5) }}</strong> · {{ $proxRes->nombre_contacto }} ({{ $proxRes->personas }}p)
                            </div>
                        @else
                            <p class="text-[11px] text-on-surface-variant italic mt-1">Sin más reservas programadas hoy.</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4 pt-2.5 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary group-hover:underline">
                    <span>Agenda de Reservas</span>
                    <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>
        </div>
    </div>
</div>
