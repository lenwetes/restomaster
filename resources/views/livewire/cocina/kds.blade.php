<?php

use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Services\PedidoService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $areaSeleccionada = 'todas'; // 'todas', 'sushi', 'caliente', 'barra'

    public ?int $comandaParaImprimir = null;

    public function abrirComanda(int $pedidoId): void
    {
        $this->comandaParaImprimir = $pedidoId;
    }

    public function cerrarComanda(): void
    {
        $this->comandaParaImprimir = null;
    }

    public function tomarItem(int $itemId): void
    {
        $item = ItemPedido::findOrFail($itemId);
        $item->update([
            'estado_cocina' => 'en_preparacion',
            'iniciado_en' => now(),
        ]);

        if ($item->pedido->estado === 'creado') {
            $item->pedido->update(['estado' => 'en_cocina']);
        }
    }

    public function marcarListo(int $itemId): void
    {
        $item = ItemPedido::findOrFail($itemId);
        $pedidoService = app(PedidoService::class);
        $pedidoService->marcarItemListo($item);
    }

    public function marcarTodaComandaLista(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);
        $pedidoService = app(PedidoService::class);

        foreach ($pedido->items as $item) {
            if ($this->areaSeleccionada === 'todas' || $item->area_cocina === $this->areaSeleccionada) {
                $pedidoService->marcarItemListo($item);
            }
        }
    }

    public function marcarComandaEntregada(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);
        $pedidoService = app(PedidoService::class);

        foreach ($pedido->items as $item) {
            $pedidoService->marcarItemEntregado($item);
        }
    }

    public function with(): array
    {
        $query = Pedido::query()
            ->whereIn('estado', ['en_cocina', 'creado', 'listo'])
            ->whereHas('items', function ($q) {
                $q->whereIn('estado_cocina', ['pendiente', 'en_preparacion', 'listo']);
                if ($this->areaSeleccionada !== 'todas') {
                    $q->where('area_cocina', $this->areaSeleccionada);
                }
            })
            ->with(['mesa', 'items' => function ($q) {
                if ($this->areaSeleccionada !== 'todas') {
                    $q->where('area_cocina', $this->areaSeleccionada);
                }
            }])
            ->orderBy('created_at', 'asc');

        $pedidos = $query->get();

        // Contadores por área optimizados en SQL
        $conteosArea = ItemPedido::query()
            ->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])
            ->selectRaw('area_cocina, count(*) as total')
            ->groupBy('area_cocina')
            ->pluck('total', 'area_cocina');

        $conteo = [
            'total' => $pedidos->count(),
            'sushi' => (int) ($conteosArea['sushi'] ?? 0),
            'caliente' => (int) ($conteosArea['caliente'] ?? 0),
            'barra' => (int) ($conteosArea['barra'] ?? 0),
        ];

        return [
            'pedidos' => $pedidos,
            'conteo' => $conteo,
            'comandaImpresion' => $this->comandaParaImprimir
                ? Pedido::with(['mesa', 'items' => function ($q) {
                    if ($this->areaSeleccionada !== 'todas') {
                        $q->where('area_cocina', $this->areaSeleccionada);
                    }
                }])->find($this->comandaParaImprimir)
                : null,
        ];
    }
}; ?>

<div wire:poll.10s class="space-y-5">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">skillet</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Pantalla de Cocina & Barra (KDS)
                </h1>
                <span class="rounded-full bg-secondary-container/50 px-2.5 py-0.5 text-[11px] font-bold text-on-secondary-container border border-secondary/30">
                    COC-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Cola de comandas organizada por orden de llegada (FIFO) · Control estricto de SLA
            </p>
        </div>

        <!-- Station Selector Filter (Stitch COC-01 Area Pills) -->
        <div class="inline-flex rounded-2xl bg-surface-container-lowest p-1 border border-surface-container-highest shadow-sm">
            <button
                wire:click="$set('areaSeleccionada', 'todas')"
                class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $areaSeleccionada === 'todas' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
            >
                <span class="material-symbols-outlined text-[16px]">restaurant_menu</span>
                <span>Todas ({{ $conteo['total'] }})</span>
            </button>
            <button
                wire:click="$set('areaSeleccionada', 'sushi')"
                class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $areaSeleccionada === 'sushi' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
            >
                <span>🍣</span>
                <span>Barra Sushi ({{ $conteo['sushi'] }})</span>
            </button>
            <button
                wire:click="$set('areaSeleccionada', 'caliente')"
                class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $areaSeleccionada === 'caliente' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
            >
                <span class="material-symbols-outlined text-[16px] text-primary">soup_kitchen</span>
                <span>Wok & Caliente ({{ $conteo['caliente'] }})</span>
            </button>
            <button
                wire:click="$set('areaSeleccionada', 'barra')"
                class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $areaSeleccionada === 'barra' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
            >
                <span class="material-symbols-outlined text-[16px] text-tertiary">wine_bar</span>
                <span>Barra Bebidas ({{ $conteo['barra'] }})</span>
            </button>
        </div>
    </header>

    <!-- Top Ambient Accent Line (Stitch Signature) -->
    <div class="h-1.5 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    <!-- Live Operational Speed & Heartbeat Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-3.5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-primary">timer</span>
                <span class="text-xs font-bold text-on-surface-variant">SLA Objetivo: <strong class="text-on-surface">15 min</strong></span>
            </div>
            <div class="hidden sm:block h-4 w-px bg-surface-container-high"></div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-secondary">check_circle</span>
                <span class="text-xs font-bold text-on-surface-variant">Cumplimiento: <strong class="text-secondary">94%</strong></span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 bg-secondary-container/30 px-3 py-1 rounded-xl border border-secondary/20">
                <span class="h-2 w-2 rounded-full bg-secondary animate-pulse"></span>
                <span class="text-xs font-mono font-bold text-on-secondary-container">KDS Sync: 10s</span>
            </div>
        </div>
    </div>

    <!-- Orders Grid (Stitch COC-01 Ergonomic Ticket Swimlanes) -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 items-start">
        @forelse($pedidos as $pedido)
            @php
                $minutos = $pedido->created_at->diffInMinutes(now());
                $esUrgente = $minutos >= 15;
                $esAdvertencia = $minutos >= 8 && $minutos < 15;

                $cardBorder = match(true) {
                    $esUrgente => 'border-error/80 bg-surface-container-lowest shadow-error/10',
                    $esAdvertencia => 'border-tertiary/70 bg-surface-container-lowest shadow-tertiary/10',
                    default => 'border-surface-container-highest bg-surface-container-lowest',
                };
                $timerStyle = match(true) {
                    $esUrgente => 'bg-error-container text-error animate-pulse border border-error/40',
                    $esAdvertencia => 'bg-tertiary-container/30 text-tertiary border border-tertiary/40',
                    default => 'bg-surface-container text-on-surface border border-surface-container-high',
                };
                $ribbonColor = match(true) {
                    $esUrgente => 'bg-error',
                    $esAdvertencia => 'bg-tertiary',
                    default => 'bg-secondary',
                };
                $todosListos = $pedido->items->every(fn($i) => in_array($i->estado_cocina, ['listo', 'entregado', 'cancelado']));
            @endphp

            <article class="flex flex-col justify-between rounded-3xl border overflow-hidden shadow-sm transition-all duration-200 hover:shadow-md {{ $cardBorder }}">
                <!-- Top Urgency Ribbon -->
                <div class="h-1.5 w-full {{ $ribbonColor }}"></div>

                <div class="p-4 flex flex-col justify-between flex-1">
                    <div>
                        <!-- Ticket Header: Code, Table & Elapsed Timer -->
                        <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-base font-black text-on-surface">
                                    {{ $pedido->codigo }}
                                </span>
                                @if($pedido->mesa)
                                    <span class="rounded-xl bg-primary px-2 py-0.5 text-xs font-black text-on-primary shadow-sm">
                                        Mesa {{ $pedido->mesa->numero }}
                                    </span>
                                @else
                                    <span class="rounded-xl bg-surface-container px-2 py-0.5 text-xs font-bold text-on-surface capitalize border border-surface-container-high">
                                        {{ $pedido->tipo }}
                                    </span>
                                @endif
                            </div>

                            <!-- Elapsed Time in kds-timer Tabular Numerals -->
                            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-mono font-black {{ $timerStyle }}">
                                <span class="material-symbols-outlined text-[15px]">schedule</span>
                                <span>{{ sprintf('%02d', $minutos) }}:{{ sprintf('%02d', now()->second) }}m</span>
                            </div>
                        </div>

                        <!-- Ticket Items Dense Checklist -->
                        <div class="mt-3.5 space-y-2">
                            @foreach($pedido->items as $item)
                                <div class="flex items-start justify-between gap-2 rounded-2xl border border-surface-container-high bg-surface-container-low p-2.5">
                                    <div class="space-y-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-surface-container text-xs font-mono font-black text-on-surface">
                                                {{ $item->cantidad }}x
                                            </span>
                                            <span class="text-sm font-bold text-on-surface truncate">
                                                {{ $item->nombre_producto }}
                                            </span>
                                        </div>

                                        <!-- Modifiers & Customer Notes Highlighting -->
                                        @if($item->notas)
                                            <div class="ml-8 text-[11px] font-bold text-primary flex items-center gap-1">
                                                <span>✦</span>
                                                <span>{{ $item->notas }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Item Action Button -->
                                    <div class="shrink-0">
                                        @if($item->estado_cocina === 'pendiente')
                                            <button 
                                                wire:click="tomarItem({{ $item->id }})"
                                                class="rounded-xl bg-surface-container px-2.5 py-1.5 text-xs font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all"
                                            >
                                                Tomar
                                            </button>
                                        @elseif($item->estado_cocina === 'en_preparacion')
                                            <button 
                                                wire:click="marcarListo({{ $item->id }})"
                                                class="rounded-xl bg-primary px-2.5 py-1.5 text-xs font-bold text-on-primary shadow-sm hover:bg-primary-container active:scale-95 transition-all"
                                            >
                                                Prep → Listo
                                            </button>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-xl bg-secondary-container/60 px-2.5 py-1.5 text-xs font-bold text-on-secondary-container border border-secondary/30">
                                                <span class="material-symbols-outlined text-[14px]">check</span>
                                                <span>Listo</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Ticket Footer Action Bar -->
                    <div class="mt-5 pt-3 border-t border-surface-container-high flex items-center justify-between gap-2">
                        <span class="text-[11px] font-mono text-on-surface-variant">
                            {{ $pedido->created_at->format('H:i') }} • {{ count($pedido->items) }} platos
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                wire:click="abrirComanda({{ $pedido->id }})"
                                class="flex items-center gap-1.5 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all"
                                title="Imprimir comanda de esta estación"
                            >
                                <span class="material-symbols-outlined text-[16px]">print</span>
                                Comanda
                            </button>
                            @if(!$todosListos)
                                <button 
                                    wire:click="marcarTodaComandaLista({{ $pedido->id }})"
                                    class="flex items-center gap-1.5 rounded-xl bg-secondary px-3.5 py-2 text-xs font-extrabold text-on-secondary shadow-sm hover:bg-secondary-fixed-dim active:scale-95 transition-all"
                                >
                                    <span class="material-symbols-outlined text-[16px]">done_all</span>
                                    <span>✓ Todo Listo</span>
                                </button>
                            @else
                                <button 
                                    wire:click="marcarComandaEntregada({{ $pedido->id }})"
                                    class="flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-extrabold text-on-primary shadow-sm hover:bg-primary-container active:scale-95 transition-all"
                                >
                                    <span class="material-symbols-outlined text-[16px]">delivery_dining</span>
                                    <span>🚀 Despachar</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-3xl border border-dashed border-surface-container-highest p-16 text-center text-on-surface-variant bg-surface-container-lowest">
                <span class="material-symbols-outlined text-[48px] text-secondary/60 block mb-2">skillet</span>
                <p class="text-base font-extrabold text-on-surface">No hay comandas pendientes en cocina</p>
                <p class="text-xs text-on-surface-variant mt-1">Línea de preparación despejada · Todas las órdenes han sido expedidas.</p>
            </div>
        @endforelse
    </div>

    @if($comandaImpresion && $comandaImpresion->items->isNotEmpty())
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="print-ticket-termico w-full max-w-sm rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest font-mono text-xs">
                <!-- Comanda Ticket Header -->
                <div class="text-center border-b border-dashed border-surface-container-high pb-4">
                    <p class="text-base font-black tracking-tight text-primary">🍣 SUSHIXPRESS 🍣</p>
                    <p class="text-[11px] text-on-surface-variant">COMANDA DE COCINA</p>
                    <p class="text-[10px] text-on-surface-variant/70">Estación: <strong class="uppercase text-secondary">{{ $areaSeleccionada === 'todas' ? 'Todas' : $areaSeleccionada }}</strong></p>
                </div>

                <!-- Ticket Details -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span>ORDEN:</span>
                        <span class="font-bold text-primary">{{ $comandaImpresion->codigo }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>FECHA:</span>
                        <span>{{ $comandaImpresion->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>TIPO:</span>
                        <span class="font-bold uppercase text-secondary">{{ $comandaImpresion->tipo }} {{ $comandaImpresion->mesa ? "- Mesa {$comandaImpresion->mesa->numero}" : '' }}</span>
                    </div>
                </div>

                <!-- Ticket Line Items (solo items del área seleccionada) -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1.5">
                    @foreach($comandaImpresion->items as $item)
                        <div class="text-[11px]">
                            <div class="flex justify-between">
                                <span>{{ $item->cantidad }}x {{ $item->nombre_producto }}</span>
                                <span class="font-bold uppercase">{{ $item->area_cocina }}</span>
                            </div>
                            @if($item->notas)
                                <div class="text-[10px] font-bold text-primary ml-4">✦ {{ $item->notas }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Ticket Footer -->
                <div class="pt-4 text-center text-[10px] text-on-surface-variant space-y-1">
                    <p class="font-bold text-on-surface">IMPRIMIR Y PEGAR EN ESTACIÓN</p>
                    <p class="text-[9px]">{{ $comandaImpresion->items->sum('cantidad') }} platos · {{ $comandaImpresion->items->count() }} líneas</p>
                </div>

                <!-- Close / Print buttons -->
                <div class="no-print mt-5 grid grid-cols-2 gap-2">
                    <button
                        onclick="window.print()"
                        class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[16px]">print</span>
                        <span>Imprimir Comanda</span>
                    </button>
                    <button
                        wire:click="cerrarComanda"
                        class="rounded-xl bg-primary py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container cursor-pointer"
                    >
                        ✓ Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
