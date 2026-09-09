<?php

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Services\MesaService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $filtroZona = 'todas';
    public string $filtroEstado = 'todas';

    public function cambiarEstado(int $mesaId, string $nuevoEstado): void
    {
        $mesa = Mesa::findOrFail($mesaId);
        $mesa->update(['estado' => $nuevoEstado]);

        $this->dispatch('notificacion', [
            'mensaje' => "Mesa {$mesa->numero} actualizada a {$nuevoEstado}",
            'tipo' => 'success',
        ]);
    }

    public function with(): array
    {
        $query = Mesa::query()->with(['sucursal', 'pedidos' => function ($q) {
            $q->activos()->latest();
        }]);

        if ($this->filtroZona !== 'todas') {
            $query->where('zona', $this->filtroZona);
        }

        if ($this->filtroEstado !== 'todas') {
            $query->where('estado', $this->filtroEstado);
        }

        $mesas = $query->orderBy('numero')->get();

        // Global counters
        $todasMesas = Mesa::all();
        $conteo = [
            'total' => $todasMesas->count(),
            'libres' => $todasMesas->where('estado', MesaEstado::LIBRE->value)->count(),
            'ocupadas' => $todasMesas->where('estado', MesaEstado::OCUPADA->value)->count(),
            'por_limpiar' => $todasMesas->where('estado', MesaEstado::POR_LIMPIAR->value)->count(),
            'reservadas' => $todasMesas->where('estado', MesaEstado::RESERVADA->value)->count(),
        ];

        return [
            'mesas' => $mesas,
            'conteo' => $conteo,
        ];
    }
}; ?>

<x-slot name="header">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">table_restaurant</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Salón & Mapa de Mesas
                </h1>
                <span class="rounded-full bg-secondary-container/50 px-2.5 py-0.5 text-[11px] font-bold text-on-secondary-container border border-secondary/30">
                    MES-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Monitoreo visual táctil en tiempo real · Distribución espacial y comensales
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a 
                href="{{ route('pos') }}" 
                wire:navigate
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container transition-all active:scale-95"
            >
                <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
                <span>Abrir POS Táctil</span>
            </a>
        </div>
    </div>
</x-slot>

<div class="space-y-5">
    <!-- Status Overview Counters (Stitch MES-01 Aura Gastro Expressive OS) -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <!-- Libres -->
        <button 
            wire:click="$set('filtroEstado', 'libre')"
            class="flex items-center justify-between rounded-2xl border p-4 text-left transition-all {{ $filtroEstado === 'libre' ? 'border-secondary bg-secondary-container/30 ring-2 ring-secondary/40 shadow-sm' : 'border-surface-container-highest bg-surface-container-lowest hover:bg-surface-container-low shadow-sm' }}"
        >
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Mesas Libres</span>
                <p class="text-2xl font-mono font-extrabold text-secondary">{{ $conteo['libres'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary-container/50 text-secondary border border-secondary/30">
                <span class="material-symbols-outlined text-[22px]">check_circle</span>
            </div>
        </button>

        <!-- Ocupadas -->
        <button 
            wire:click="$set('filtroEstado', 'ocupada')"
            class="flex items-center justify-between rounded-2xl border p-4 text-left transition-all {{ $filtroEstado === 'ocupada' ? 'border-primary bg-primary-container/15 ring-2 ring-primary/40 shadow-sm' : 'border-surface-container-highest bg-surface-container-lowest hover:bg-surface-container-low shadow-sm' }}"
        >
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Mesas Ocupadas</span>
                <p class="text-2xl font-mono font-extrabold text-primary">{{ $conteo['ocupadas'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-fixed text-primary border border-primary/30">
                <span class="material-symbols-outlined text-[22px]">restaurant</span>
            </div>
        </button>

        <!-- Por Limpiar -->
        <button 
            wire:click="$set('filtroEstado', 'por_limpiar')"
            class="flex items-center justify-between rounded-2xl border p-4 text-left transition-all {{ $filtroEstado === 'por_limpiar' ? 'border-tertiary bg-tertiary-container/20 ring-2 ring-tertiary/40 shadow-sm' : 'border-surface-container-highest bg-surface-container-lowest hover:bg-surface-container-low shadow-sm' }}"
        >
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Por Limpiar</span>
                <p class="text-2xl font-mono font-extrabold text-tertiary">{{ $conteo['por_limpiar'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-tertiary-container/30 text-tertiary border border-tertiary/30">
                <span class="material-symbols-outlined text-[22px]">cleaning_services</span>
            </div>
        </button>

        <!-- Total Mesas -->
        <button 
            wire:click="$set('filtroEstado', 'todas')"
            class="flex items-center justify-between rounded-2xl border p-4 text-left transition-all {{ $filtroEstado === 'todas' ? 'border-outline bg-surface-container ring-2 ring-outline/30 shadow-sm' : 'border-surface-container-highest bg-surface-container-lowest hover:bg-surface-container-low shadow-sm' }}"
        >
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Total Mesas</span>
                <p class="text-2xl font-mono font-extrabold text-on-surface">{{ $conteo['total'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container text-on-surface border border-surface-container-high">
                <span class="material-symbols-outlined text-[22px]">grid_view</span>
            </div>
        </button>
    </div>

    <!-- Zone Filters Bar (Stitch MES-01 Area Pills) -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-3 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-on-surface-variant px-2 flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">filter_alt</span>
                Zona:
            </span>
            <button 
                wire:click="$set('filtroZona', 'todas')"
                class="rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $filtroZona === 'todas' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
            >
                Todas las Zonas
            </button>
            <button 
                wire:click="$set('filtroZona', 'salon')"
                class="rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $filtroZona === 'salon' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
            >
                Salón Principal
            </button>
            <button 
                wire:click="$set('filtroZona', 'barra')"
                class="rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $filtroZona === 'barra' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
            >
                Barra Sushi
            </button>
            <button 
                wire:click="$set('filtroZona', 'terraza')"
                class="rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $filtroZona === 'terraza' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
            >
                Terraza
            </button>
        </div>

        @if($filtroEstado !== 'todas' || $filtroZona !== 'todas')
            <button 
                wire:click="$set('filtroEstado', 'todas'); $set('filtroZona', 'todas')"
                class="flex items-center gap-1 text-xs font-bold text-primary hover:underline px-2"
            >
                <span class="material-symbols-outlined text-[16px]">close</span>
                <span>Restablecer</span>
            </button>
        @endif
    </div>

    <!-- Mesas Matrix Grid (Stitch MES-01 Aura Gastro Porcelain Squircle Cards) -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @forelse ($mesas as $mesa)
            @php
                $cardBorder = match($mesa->estado) {
                    'libre' => 'border-secondary/30 hover:border-secondary hover:shadow-md',
                    'ocupada' => 'border-primary/30 hover:border-primary hover:shadow-md',
                    'por_limpiar' => 'border-tertiary/40 hover:border-tertiary hover:shadow-md',
                    'reservada' => 'border-secondary/30 hover:border-secondary hover:shadow-md',
                    default => 'border-surface-container-highest',
                };
                $badgeStyle = match($mesa->estado) {
                    'libre' => 'bg-secondary-container/60 text-on-secondary-container border-secondary/30',
                    'ocupada' => 'bg-primary-fixed text-on-primary-fixed border-primary/30',
                    'por_limpiar' => 'bg-tertiary-container/30 text-tertiary border-tertiary/30',
                    'reservada' => 'bg-secondary-container/60 text-on-secondary-container border-secondary/30',
                    default => 'bg-surface-container text-on-surface-variant border-surface-container-high',
                };
                $pedidoActivo = $mesa->pedidos->first();
            @endphp

            <div class="relative flex flex-col justify-between rounded-3xl border bg-surface-container-lowest p-4 shadow-sm transition-all duration-200 hover:shadow-md {{ $cardBorder }}">
                <div>
                    <!-- Header: Table Number, Pax and Status -->
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-2xl font-mono font-black text-on-surface tracking-tight">
                                {{ $mesa->numero }}
                            </span>
                            <span class="text-[10px] font-bold text-on-surface-variant block uppercase tracking-wider mt-0.5">
                                Zona {{ $mesa->zona }}
                            </span>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="rounded-full border px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider {{ $badgeStyle }}">
                                {{ str_replace('_', ' ', $mesa->estado) }}
                            </span>
                            <span class="flex items-center gap-1 text-[11px] font-bold text-on-surface-variant">
                                <span class="material-symbols-outlined text-[14px]">group</span>
                                <span>{{ $mesa->capacidad }} pax</span>
                            </span>
                        </div>
                    </div>

                    <!-- Active Order Container (if occupied) -->
                    @if($pedidoActivo)
                        <div class="mt-3.5 rounded-2xl bg-surface-container-low p-2.5 text-xs border border-surface-container-high">
                            <div class="flex items-center justify-between font-bold">
                                <span class="text-on-surface font-mono">{{ $pedidoActivo->codigo }}</span>
                                <span class="text-primary font-mono font-extrabold">${{ number_format($pedidoActivo->total, 2) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[10px] text-on-surface-variant font-medium">
                                <span class="capitalize">Estado: {{ $pedidoActivo->estado }}</span>
                                <span>{{ $pedidoActivo->items->count() }} items</span>
                            </div>
                        </div>
                    @else
                        <div class="mt-3.5 rounded-2xl border border-dashed border-surface-container-highest p-2 text-center text-[11px] text-on-surface-variant/70 font-medium">
                            Mesa disponible
                        </div>
                    @endif
                </div>

                <!-- Touch Interaction Button (Tactile 48px standard) -->
                <div class="mt-4 pt-3 border-t border-surface-container-high">
                    @if($mesa->estado === 'libre')
                        <a 
                            href="{{ route('pos', ['mesa_id' => $mesa->id]) }}" 
                            wire:navigate
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-primary text-xs font-extrabold text-on-primary shadow-sm hover:bg-primary-container transition-all active:scale-95"
                        >
                            <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                            <span>+ Abrir Comanda</span>
                        </a>
                    @elseif($mesa->estado === 'por_limpiar')
                        <button 
                            wire:click="cambiarEstado({{ $mesa->id }}, 'libre')" 
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-secondary text-xs font-extrabold text-on-secondary shadow-sm hover:bg-secondary-fixed-dim transition-all active:scale-95"
                        >
                            <span class="material-symbols-outlined text-[18px]">cleaning_services</span>
                            <span>✓ Marcar Limpia</span>
                        </button>
                    @elseif($mesa->estado === 'ocupada')
                        <a 
                            href="{{ route('pos', ['mesa_id' => $mesa->id]) }}" 
                            wire:navigate
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container border border-primary/30 text-xs font-extrabold text-on-surface hover:bg-surface-container-high transition-all active:scale-95"
                        >
                            <span class="material-symbols-outlined text-[18px] text-primary">receipt_long</span>
                            <span>Ver / Cobrar</span>
                        </a>
                    @else
                        <button 
                            wire:click="cambiarEstado({{ $mesa->id }}, 'libre')" 
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container text-xs font-extrabold text-on-surface hover:bg-surface-container-high transition-all active:scale-95"
                        >
                            <span>Liberar Mesa</span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-3xl border border-dashed border-surface-container-highest p-12 text-center text-on-surface-variant">
                No se encontraron mesas con los filtros seleccionados.
            </div>
        @endforelse
    </div>
</div>
