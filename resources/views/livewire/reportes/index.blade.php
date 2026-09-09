<?php

use App\Services\ReporteService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $desde = '';
    public string $hasta = '';

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();
    }

    public function with(): array
    {
        $service = app(ReporteService::class);

        return [
            'resultado' => $service->estadoResultados($this->desde, $this->hasta),
            'movimientos' => $service->movimientosRecientes(50),
        ];
    }
}; ?>

<x-slot name="header">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">monitoring</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Reportes Contables
                </h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">
                    REP-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Estado de Resultados y trazabilidad de asientos contables
            </p>
        </div>
    </div>
</x-slot>

<div class="space-y-6">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    <!-- Range Filter -->
    <div class="bg-surface-container-lowest rounded-3xl p-5 border border-outline-variant/20 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="text-xs font-bold text-on-surface-variant">Desde:</label>
                <input type="date" wire:model="desde" class="mt-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
            </div>
            <div>
                <label class="text-xs font-bold text-on-surface-variant">Hasta:</label>
                <input type="date" wire:model="hasta" class="mt-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
            </div>
            <span class="text-[11px] text-on-surface-variant font-mono">Ventas + ingresos − gastos = Resultado del período</span>
        </div>
    </div>

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
</div>