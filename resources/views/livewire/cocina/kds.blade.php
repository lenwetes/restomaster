<?php

use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Services\PedidoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public string $vistaModo = 'kds'; // 'kds', 'historial'

    public string $areaSeleccionada = 'todas'; // 'todas', 'sushi', 'caliente', 'barra'

    public ?int $comandaParaImprimir = null;

    // Filtros multidimensionales para el historial de comandas
    public string $historialFecha = '';
    public string $historialHora = 'todas'; // 'todas', '00'..'23'
    public string $historialMes = ''; // '', '1'..'12'
    public string $historialAnio = ''; // '', '2026', '2025'
    public string $historialArea = 'todas'; // 'todas', 'sushi', 'caliente', 'barra'
    public string $historialEstado = 'todos'; // 'todos', 'entregado', 'pagado', 'en_cocina', 'cancelado'
    public string $historialSearch = '';

    public ?int $historialDetalleId = null;

    public function abrirComanda(int $pedidoId): void
    {
        $this->comandaParaImprimir = $pedidoId;
    }

    public function cerrarComanda(): void
    {
        $this->comandaParaImprimir = null;
    }

    public function abrirDetalleHistorial(int $id): void
    {
        $this->historialDetalleId = $id;
    }

    public function cerrarDetalleHistorial(): void
    {
        $this->historialDetalleId = null;
    }

    public function limpiarFiltrosHistorial(): void
    {
        $this->historialFecha = '';
        $this->historialHora = 'todas';
        $this->historialMes = '';
        $this->historialAnio = '';
        $this->historialArea = 'todas';
        $this->historialEstado = 'todos';
        $this->historialSearch = '';
    }

    public function tomarItem(int $itemId): void
    {
        $item = ItemPedido::with('pedido')->findOrFail($itemId);
        $this->authorize('cocinar', [Pedido::class, $item->area_cocina]);

        $item->update([
            'estado_cocina' => 'en_preparacion',
            'iniciado_en' => now(),
        ]);

        if ($item->pedido_id) {
            Pedido::where('id', $item->pedido_id)
                ->where('estado', 'creado')
                ->update(['estado' => 'en_cocina']);
        }
    }

    public function marcarListo(int $itemId): void
    {
        $item = ItemPedido::with(['pedido.mesa', 'pedido.mesero'])->findOrFail($itemId);
        $sucursalId = $item->relationLoaded('pedido') ? $item->pedido?->sucursal_id : Pedido::where('id', $item->pedido_id)->value('sucursal_id');
        abort_if(Auth::user()?->sucursal_id && $sucursalId && $sucursalId !== Auth::user()->sucursal_id, 403, 'No autorizado para operar sobre comandas de otra sucursal.');
        $this->authorize('cocinar', [Pedido::class, $item->area_cocina]);

        $pedidoService = app(PedidoService::class);
        $pedidoService->marcarItemListo($item);

        $pedido = Pedido::with(['mesa', 'mesero', 'items'])->find($item->pedido_id);
        $mesaNombre = $pedido?->mesa ? "Mesa {$pedido->mesa->numero}" : ($pedido?->codigo ?? 'Comanda');
        $this->dispatch('notificacion', [
            'mensaje' => "✓ Plato '{$item->nombre_producto}' marcado LISTO para {$mesaNombre}.",
            'tipo' => 'success',
        ]);
        $this->dispatch('comanda-actualizada', pedidoId: $pedido?->id);
    }

    public function obtenerAreasFiltradas(string $area): array
    {
        return match ($area) {
            'fria', 'sushi' => ['fria', 'sushi', 'postres', 'cocina_fria'],
            'caliente' => ['caliente', 'calientes', 'cocina'],
            'barra' => ['barra', 'bebidas'],
            'postres' => ['postres', 'fria'],
            default => [$area],
        };
    }

    public function marcarTodaComandaLista(int $pedidoId): void
    {
        $pedido = Pedido::with('items')->findOrFail($pedidoId);
        abort_if(Auth::user()?->sucursal_id && $pedido->sucursal_id && $pedido->sucursal_id !== Auth::user()->sucursal_id, 403, 'No autorizado para operar sobre comandas de otra sucursal.');
        $pedidoService = app(PedidoService::class);

        $areas = $this->areaSeleccionada === 'todas' ? [] : $this->obtenerAreasFiltradas($this->areaSeleccionada);

        foreach ($pedido->items as $item) {
            if ($this->areaSeleccionada === 'todas' || in_array($item->area_cocina, $areas, true)) {
                if (Gate::allows('cocinar', [Pedido::class, $item->area_cocina])) {
                    $item->setRelation('pedido', $pedido);
                    $pedidoService->marcarItemListo($item);
                }
            }
        }

        $pedido = Pedido::with(['mesa', 'mesero', 'items'])->find($pedidoId);
        $mesaNombre = $pedido?->mesa ? "Mesa {$pedido->mesa->numero}" : ($pedido?->codigo ?? 'Comanda');
        $this->dispatch('notificacion', [
            'mensaje' => "🛎️ ¡Comanda de {$mesaNombre} marcada completamente LISTA para servir!",
            'tipo' => 'success',
        ]);
        $this->dispatch('comanda-actualizada', pedidoId: $pedido?->id);
    }

    public function marcarComandaEntregada(int $pedidoId): void
    {
        $pedido = Pedido::with('items')->findOrFail($pedidoId);
        abort_if(Auth::user()?->sucursal_id && $pedido->sucursal_id && $pedido->sucursal_id !== Auth::user()->sucursal_id, 403, 'No autorizado para operar sobre comandas de otra sucursal.');
        $pedidoService = app(PedidoService::class);

        foreach ($pedido->items as $item) {
            if (Gate::allows('cocinar', [Pedido::class, $item->area_cocina])) {
                $item->setRelation('pedido', $pedido);
                $pedidoService->marcarItemEntregado($item);
            }
        }

        $pedido = Pedido::with('mesa')->find($pedidoId);
        $mesaNombre = $pedido?->mesa ? "Mesa {$pedido->mesa->numero}" : ($pedido?->codigo ?? 'Comanda');
        $this->dispatch('notificacion', [
            'mensaje' => "🍽️ Comanda de {$mesaNombre} entregada / servida a la mesa.",
            'tipo' => 'info',
        ]);
        $this->dispatch('comanda-actualizada', pedidoId: $pedido?->id);
    }

    public function with(): array
    {
        // 1. COLA EN VIVO DE COCINA (KDS): Incluye pedidos creados, en cocina, en preparacion y listos
        $query = Pedido::query()
            ->whereIn('estado', ['en_cocina', 'en_preparacion', 'creado', 'listo'])
            ->whereHas('items', function ($q) {
                $q->whereIn('estado_cocina', ['pendiente', 'en_preparacion', 'listo']);
                if ($this->areaSeleccionada !== 'todas') {
                    $areas = $this->obtenerAreasFiltradas($this->areaSeleccionada);
                    $q->whereIn('area_cocina', $areas);
                }
            });

        if (Auth::user()?->sucursal_id) {
            $query->where('sucursal_id', Auth::user()->sucursal_id);
        }

        $query->with(['mesa', 'usuario', 'mesero', 'items' => function ($q) {
            if ($this->areaSeleccionada !== 'todas') {
                $areas = $this->obtenerAreasFiltradas($this->areaSeleccionada);
                $q->whereIn('area_cocina', $areas);
            }
        }])
            ->orderBy('created_at', 'asc');

        $pedidos = $query->get();

        // Contadores por área optimizados en SQL (solo pedidos activos en cocina)
        $conteosQuery = ItemPedido::query()
            ->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])
            ->whereHas('pedido', function ($q) {
                $q->whereIn('estado', ['en_cocina', 'en_preparacion', 'creado', 'listo']);
                if (Auth::user()?->sucursal_id) {
                    $q->where('sucursal_id', Auth::user()->sucursal_id);
                }
            });

        $conteosArea = $conteosQuery
            ->selectRaw('area_cocina, count(*) as total')
            ->groupBy('area_cocina')
            ->pluck('total', 'area_cocina');

        $conteoFria = (int) ($conteosArea['fria'] ?? 0) + (int) ($conteosArea['sushi'] ?? 0) + (int) ($conteosArea['postres'] ?? 0) + (int) ($conteosArea['cocina_fria'] ?? 0);
        $conteoCaliente = (int) ($conteosArea['caliente'] ?? 0) + (int) ($conteosArea['calientes'] ?? 0) + (int) ($conteosArea['cocina'] ?? 0);
        $conteoBarra = (int) ($conteosArea['barra'] ?? 0) + (int) ($conteosArea['bebidas'] ?? 0);

        $conteo = [
            'total' => $pedidos->count(),
            'fria' => $conteoFria,
            'sushi' => $conteoFria,
            'caliente' => $conteoCaliente,
            'barra' => $conteoBarra,
        ];

        // 2. HISTORIAL MULTIDIMENSIONAL DE COMANDAS
        $comandasHistorial = collect();
        $totalHistorial = 0;
        $totalPlatosHistorial = 0;
        $tiempoPromedioHistorial = 0.0;

        if ($this->vistaModo === 'historial') {
            $hQuery = Pedido::query()
                ->with(['mesa', 'usuario', 'items.producto.categoria']);

            if (Auth::user()?->sucursal_id) {
                $hQuery->where('sucursal_id', Auth::user()->sucursal_id);
            }

            // Filtro por Fecha específica
            if ($this->historialFecha !== '') {
                $hQuery->whereDate('created_at', $this->historialFecha);
            } else {
                // Filtro por Mes
                if ($this->historialMes !== '') {
                    $hQuery->whereMonth('created_at', (int) $this->historialMes);
                }
                // Filtro por Año
                if ($this->historialAnio !== '') {
                    $hQuery->whereYear('created_at', (int) $this->historialAnio);
                }
            }

            // Filtro por Hora (00 a 23)
            if ($this->historialHora !== 'todas') {
                if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
                    $hQuery->whereRaw("cast(strftime('%H', created_at) as integer) = ?", [(int) $this->historialHora]);
                } else {
                    $hQuery->whereRaw('EXTRACT(HOUR FROM created_at) = ?', [(int) $this->historialHora]);
                }
            }

            // Filtro por Área / Estación
            if ($this->historialArea !== 'todas') {
                $hQuery->whereHas('items', function ($q) {
                    $q->where('area_cocina', $this->historialArea);
                });
            }

            // Filtro por Estado
            if ($this->historialEstado !== 'todos') {
                $hQuery->where('estado', $this->historialEstado);
            }

            // Búsqueda de texto (código, mesa, plato o cliente)
            if (trim($this->historialSearch) !== '') {
                $term = '%' . trim($this->historialSearch) . '%';
                $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $hQuery->where(function ($q) use ($term, $like) {
                    $q->where('codigo', $like, $term)
                      ->orWhere('nombre_cliente', $like, $term)
                      ->orWhereHas('mesa', fn ($m) => $m->where('numero', $like, $term))
                      ->orWhereHas('items', fn ($i) => $i->where('nombre_producto', $like, $term));
                });
            }

            $comandasHistorial = $hQuery->orderBy('created_at', 'desc')->limit(60)->get();
            $totalHistorial = $comandasHistorial->count();
            $totalPlatosHistorial = (int) $comandasHistorial->sum(fn ($p) => $p->items->sum('cantidad'));

            $tiempos = $comandasHistorial->map(function ($p) {
                $fin = $p->hora_entrega ?? $p->hora_despacho ?? $p->pagado_en ?? $p->updated_at;
                return $p->created_at->diffInMinutes($fin);
            });
            $tiempoPromedioHistorial = $totalHistorial > 0 ? round($tiempos->average(), 1) : 0.0;
        }

        return [
            'pedidos' => $pedidos,
            'conteo' => $conteo,
            'comandasHistorial' => $comandasHistorial,
            'totalHistorial' => $totalHistorial,
            'totalPlatosHistorial' => $totalPlatosHistorial,
            'tiempoPromedioHistorial' => $tiempoPromedioHistorial,
            'historialDetalle' => $this->historialDetalleId
                ? Pedido::with(['mesa', 'usuario', 'items.producto.categoria'])->find($this->historialDetalleId)
                : null,
            'comandaImpresion' => $this->comandaParaImprimir
                ? Pedido::with(['mesa', 'usuario', 'items' => function ($q) {
                    if ($this->areaSeleccionada !== 'todas') {
                        $q->where('area_cocina', $this->areaSeleccionada);
                    }
                    $q->with('producto.categoria');
                }])->find($this->comandaParaImprimir)
                : null,
        ];
    }
}; ?>

<div wire:poll.15s class="space-y-5">
    <!-- ENCABEZADO PRINCIPAL: SWITCH KDS EN VIVO vs HISTORIAL DE COMANDAS -->
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shadow-xs">
                    <span class="material-symbols-outlined text-[24px]">skillet</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-black tracking-tight text-on-surface">
                            {{ $vistaModo === 'kds' ? 'Pantalla de Cocina & Barra (KDS)' : 'Historial de Comandas y Tiempos' }}
                        </h1>
                        <span class="rounded-full bg-secondary-container/50 px-2.5 py-0.5 text-[11px] font-bold text-on-secondary-container border border-secondary/30 font-mono">
                            COC-01
                        </span>
                    </div>
                    <p class="text-xs text-on-surface-variant mt-0.5">
                        {{ $vistaModo === 'kds' ? 'Cola de comandas activa en tiempo real · Control de tiempos de preparación (FIFO)' : 'Auditoría histórica de comandas, tiempos de elaboración y platos despachados por fecha y hora' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- SELECTOR DE VISTA: KDS EN VIVO VS HISTORIAL -->
        <div class="inline-flex rounded-2xl bg-surface-container-low p-1.5 border border-surface-container-high shadow-xs shrink-0">
            <button
                type="button"
                wire:click="$set('vistaModo', 'kds')"
                class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition-all cursor-pointer {{ $vistaModo === 'kds' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
            >
                <span class="material-symbols-outlined text-[18px]">skillet</span>
                <span>En Vivo KDS</span>
                @if($conteo['total'] > 0)
                    <span class="px-1.5 py-0.2 rounded-full bg-white/20 text-white text-[10px] font-mono font-bold">{{ $conteo['total'] }}</span>
                @endif
            </button>
            <button
                type="button"
                wire:click="$set('vistaModo', 'historial')"
                class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition-all cursor-pointer {{ $vistaModo === 'historial' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
            >
                <span class="material-symbols-outlined text-[18px]">history</span>
                <span>Historial de Comandas</span>
            </button>
        </div>
    </header>

    <!-- Top Ambient Accent Line (Stitch Signature) -->
    <div class="h-1.5 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @if($vistaModo === 'kds')
        <!-- ========================================================================= -->
        <!-- MODO 1: COLA OPERATIVA EN VIVO (KDS)                                      -->
        <!-- ========================================================================= -->
        <div class="space-y-4 animate-fade-in">
            <!-- Station Selector Filter -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-surface-container-lowest p-3 rounded-2xl border border-surface-container-highest shadow-xs">
                <div class="inline-flex rounded-xl bg-surface-container-low p-1 border border-surface-container-high overflow-x-auto max-w-full">
                    <button
                        type="button"
                        wire:click="$set('areaSeleccionada', 'todas')"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all cursor-pointer {{ $areaSeleccionada === 'todas' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                    >
                        <span class="material-symbols-outlined text-[16px]">restaurant_menu</span>
                        <span>Todas ({{ $conteo['total'] }})</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('areaSeleccionada', 'fria')"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all cursor-pointer {{ in_array($areaSeleccionada, ['fria', 'sushi']) ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                    >
                        <span>🥗</span>
                        <span>Cocina Fría & Postres ({{ $conteo['fria'] }})</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('areaSeleccionada', 'caliente')"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all cursor-pointer {{ $areaSeleccionada === 'caliente' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                    >
                        <span class="material-symbols-outlined text-[16px]">outdoor_grill</span>
                        <span>Parrilla & Caliente ({{ $conteo['caliente'] }})</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('areaSeleccionada', 'barra')"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all cursor-pointer {{ $areaSeleccionada === 'barra' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                    >
                        <span class="material-symbols-outlined text-[16px]">wine_bar</span>
                        <span>Barra Bebidas ({{ $conteo['barra'] }})</span>
                    </button>
                </div>

                <!-- Live Operational Speed & Heartbeat Bar -->
                <div class="flex items-center gap-4 text-xs">
                    <div class="flex items-center gap-1.5 text-on-surface-variant font-bold">
                        <span class="material-symbols-outlined text-[18px] text-primary">timer</span>
                        <span>SLA: <strong class="text-on-surface">15 min</strong></span>
                    </div>
                    <div class="flex items-center gap-1.5 bg-secondary-container/30 px-2.5 py-1 rounded-xl border border-secondary/20">
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
                                                {{ str_starts_with(strtolower($pedido->mesa->numero), 'mesa') ? $pedido->mesa->numero : 'Mesa '.$pedido->mesa->numero }}
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
                                                    @can('cocinar', [\App\Models\Pedido::class, $item->area_cocina])
                                                        <button 
                                                            type="button"
                                                            wire:click="tomarItem({{ $item->id }})"
                                                            class="rounded-xl bg-surface-container px-2.5 py-1.5 text-xs font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all cursor-pointer"
                                                        >
                                                            Tomar
                                                        </button>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 rounded-xl bg-surface-container/50 px-2 py-1 text-[11px] font-bold text-on-surface-variant/70 border border-outline-variant/20" title="Asignado a estación {{ ucfirst($item->area_cocina) }}">
                                                            <span class="material-symbols-outlined text-[12px]">lock</span>
                                                            <span>{{ ucfirst($item->area_cocina) }}</span>
                                                        </span>
                                                    @endcan
                                                @elseif($item->estado_cocina === 'en_preparacion')
                                                    @can('cocinar', [\App\Models\Pedido::class, $item->area_cocina])
                                                        <button 
                                                            type="button"
                                                            wire:click="marcarListo({{ $item->id }})"
                                                            class="rounded-xl bg-primary px-2.5 py-1.5 text-xs font-bold text-on-primary shadow-sm hover:bg-primary-container active:scale-95 transition-all cursor-pointer"
                                                        >
                                                            Prep → Listo
                                                        </button>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 rounded-xl bg-amber-500/10 px-2 py-1 text-[11px] font-bold text-amber-700 border border-amber-500/20">
                                                            <span>En {{ ucfirst($item->area_cocina) }}</span>
                                                        </span>
                                                    @endcan
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
                                        type="button"
                                        wire:click="abrirComanda({{ $pedido->id }})"
                                        class="flex items-center gap-1.5 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all cursor-pointer"
                                        title="Imprimir comanda de esta estación"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">print</span>
                                        Comanda
                                    </button>
                                    @if(!$todosListos)
                                        <button 
                                            type="button"
                                            wire:click="marcarTodaComandaLista({{ $pedido->id }})"
                                            class="flex items-center gap-1.5 rounded-xl bg-secondary px-3.5 py-2 text-xs font-extrabold text-on-secondary shadow-sm hover:bg-secondary-fixed-dim active:scale-95 transition-all cursor-pointer"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">done_all</span>
                                            <span>✓ Todo Listo</span>
                                        </button>
                                    @else
                                        <button 
                                            type="button"
                                            wire:click="marcarComandaEntregada({{ $pedido->id }})"
                                            class="flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-extrabold text-on-primary shadow-sm hover:bg-primary-container active:scale-95 transition-all cursor-pointer"
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
        </div>
    @else
        <!-- ========================================================================= -->
        <!-- MODO 2: HISTORIAL MULTIDIMENSIONAL DE COMANDAS                            -->
        <!-- ========================================================================= -->
        <div class="space-y-4 animate-fade-in">
            <!-- BARRA DE FILTROS MULTIDIMENSIONALES (FECHA, HORA, MES, AÑO, ÁREA, ESTADO) -->
            <div class="rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">filter_alt</span>
                        <h3 class="text-sm font-black text-on-surface">Filtros de Búsqueda Histórica</h3>
                    </div>
                    <button
                        type="button"
                        wire:click="limpiarFiltrosHistorial"
                        class="text-xs font-bold text-primary hover:underline flex items-center gap-1 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                        <span>Limpiar Filtros</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- 1. Filtro por Fecha Específica -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">
                            Fecha Exacta
                        </label>
                        <input
                            type="date"
                            wire:model.live="historialFecha"
                            class="w-full h-10 px-3 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-semibold text-on-surface focus:border-primary outline-none"
                        />
                    </div>

                    <!-- 2. Filtro por Hora (00:00 a 23:00) -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">
                            Hora del Día
                        </label>
                        <select
                            wire:model.live="historialHora"
                            class="w-full h-10 px-3 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-semibold text-on-surface focus:border-primary outline-none"
                        >
                            <option value="todas">Todas las Horas (00:00 - 23:59)</option>
                            @for ($h = 0; $h < 24; $h++)
                                <option value="{{ $h }}">{{ sprintf('%02d:00 - %02d:59', $h, $h) }}</option>
                            @endfor
                        </select>
                    </div>

                    <!-- 3. Filtro por Mes -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">
                            Mes
                        </label>
                        <select
                            wire:model.live="historialMes"
                            class="w-full h-10 px-3 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-semibold text-on-surface focus:border-primary outline-none"
                        >
                            <option value="">Todos los Meses</option>
                            <option value="1">Enero</option>
                            <option value="2">Febrero</option>
                            <option value="3">Marzo</option>
                            <option value="4">Abril</option>
                            <option value="5">Mayo</option>
                            <option value="6">Junio</option>
                            <option value="7">Julio</option>
                            <option value="8">Agosto</option>
                            <option value="9">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>
                        </select>
                    </div>

                    <!-- 4. Filtro por Año -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">
                            Año
                        </label>
                        <select
                            wire:model.live="historialAnio"
                            class="w-full h-10 px-3 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-semibold text-on-surface focus:border-primary outline-none"
                        >
                            <option value="">Todos los Años</option>
                            <option value="2026">2026</option>
                            <option value="2025">2025</option>
                            <option value="2024">2024</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                    <!-- 5. Filtro por Estación / Área -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">
                            Estación de Preparación
                        </label>
                        <select
                            wire:model.live="historialArea"
                            class="w-full h-10 px-3 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-semibold text-on-surface focus:border-primary outline-none"
                        >
                            <option value="todas">Todas las Estaciones</option>
                            <option value="sushi">Cocina Fría & Entradas</option>
                            <option value="caliente">Cocina Caliente & Parrilla</option>
                            <option value="barra">Barra & Bebidas</option>
                            <option value="postres">Postres</option>
                        </select>
                    </div>

                    <!-- 6. Filtro por Estado -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">
                            Estado de la Orden
                        </label>
                        <select
                            wire:model.live="historialEstado"
                            class="w-full h-10 px-3 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-semibold text-on-surface focus:border-primary outline-none"
                        >
                            <option value="todos">Todos los Estados</option>
                            <option value="entregado">Entregado / Despachado</option>
                            <option value="pagado">Pagado y Finalizado</option>
                            <option value="en_cocina">En Cocina</option>
                            <option value="listo">Listo en Pasa-Platos</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>

                    <!-- 7. Búsqueda por Texto -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">
                            Búsqueda Rápida
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-on-surface-variant">search</span>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="historialSearch"
                                placeholder="Ej: PED-001, Mesa 3, Salmón..."
                                class="w-full h-10 pl-9 pr-3 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-semibold text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- BARRA DE KPIS DEL HISTORIAL FILTRADO -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 rounded-2xl bg-surface-container-lowest border border-surface-container-highest shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] uppercase font-bold text-on-surface-variant">Comandas Encontradas</span>
                        <h4 class="text-2xl font-black text-on-surface font-mono">{{ $totalHistorial }}</h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-surface-container-lowest border border-surface-container-highest shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] uppercase font-bold text-on-surface-variant">Platos / Ítems Procesados</span>
                        <h4 class="text-2xl font-black text-on-surface font-mono">{{ $totalPlatosHistorial }}</h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">restaurant</span>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-surface-container-lowest border border-surface-container-highest shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] uppercase font-bold text-on-surface-variant">Tiempo Promedio Expedición</span>
                        <div class="flex items-center gap-2">
                            <h4 class="text-2xl font-black text-on-surface font-mono">{{ $tiempoPromedioHistorial }} min</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $tiempoPromedioHistorial <= 15 ? 'bg-secondary/15 text-secondary' : 'bg-amber-500/15 text-amber-700' }}">
                                {{ $tiempoPromedioHistorial <= 15 ? 'En SLA ✓' : 'Atención SLA' }}
                            </span>
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">timer</span>
                    </div>
                </div>
            </div>

            <!-- LISTADO / GRID DE COMANDAS HISTÓRICAS -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @forelse($comandasHistorial as $hPedido)
                    @php
                        $fin = $hPedido->hora_entrega ?? $hPedido->hora_despacho ?? $hPedido->pagado_en ?? $hPedido->updated_at;
                        $minPrep = $hPedido->created_at->diffInMinutes($fin);
                        $enSla = $minPrep <= 15;
                    @endphp
                    <div class="rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-sm flex flex-col justify-between hover:border-primary/40 transition">
                        <div>
                            <!-- Header Comanda Histórica -->
                            <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm font-black text-on-surface">{{ $hPedido->codigo }}</span>
                                    @if($hPedido->mesa)
                                        <span class="rounded-lg bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 text-xs font-black">
                                            Mesa {{ $hPedido->mesa->numero }}
                                        </span>
                                    @else
                                        <span class="rounded-lg bg-surface-container text-on-surface px-2 py-0.5 text-xs font-bold capitalize border border-surface-container-high">
                                            {{ $hPedido->tipo }}
                                        </span>
                                    @endif
                                </div>
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border {{ $hPedido->estado === 'entregado' || $hPedido->estado === 'pagado' ? 'bg-secondary/10 text-secondary border-secondary/20' : 'bg-surface-container text-on-surface-variant border-surface-container-high' }}">
                                    {{ $hPedido->estado }}
                                </span>
                            </div>

                            <!-- Meta de Tiempo y Fecha -->
                            <div class="grid grid-cols-2 gap-2 my-3 p-2 rounded-xl bg-surface-container-low text-[11px]">
                                <div>
                                    <span class="text-on-surface-variant text-[10px] block">Ingreso / Comanda:</span>
                                    <span class="font-bold text-on-surface font-mono">{{ $hPedido->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div>
                                    <span class="text-on-surface-variant text-[10px] block">Tiempo de Elaboración:</span>
                                    <span class="font-bold font-mono {{ $enSla ? 'text-secondary' : 'text-amber-700' }}">
                                        {{ $minPrep }} min ({{ $enSla ? 'Dentro de SLA' : 'Excedió SLA' }})
                                    </span>
                                </div>
                            </div>

                            <!-- Lista de Platos Preparados -->
                            <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                @foreach($hPedido->items as $hItem)
                                    @php
                                        $catColor = $hItem->producto?->categoria?->color ?? '#e11d48';
                                    @endphp
                                    <div class="flex items-center justify-between p-2 rounded-xl bg-surface-container-low border border-surface-container-high text-xs">
                                        <div class="flex items-center gap-2 min-w-0 flex-1">
                                            <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs" @style(['background-color: ' . $catColor]) title="Categoría: {{ $hItem->producto?->categoria?->nombre ?? 'General' }}"></span>
                                            <span class="font-mono font-bold text-on-surface shrink-0">{{ $hItem->cantidad }}x</span>
                                            <span class="font-semibold text-on-surface truncate">{{ $hItem->nombre_producto }}</span>
                                        </div>
                                        <span class="text-[10px] uppercase font-bold text-on-surface-variant px-1.5 py-0.2 rounded bg-surface-container-high shrink-0 ml-2">
                                            {{ $hItem->area_cocina }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Footer con Botones -->
                        <div class="mt-4 pt-3 border-t border-surface-container-high flex items-center justify-between">
                            <span class="text-[10px] text-on-surface-variant font-medium">
                                {{ $hPedido->items->sum('cantidad') }} ítems · Atendido por {{ $hPedido->usuario?->name ?? 'Sistema' }}
                            </span>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    wire:click="abrirComanda({{ $hPedido->id }})"
                                    class="h-8 px-2.5 rounded-xl border border-surface-container-high bg-surface-container hover:bg-surface-container-high text-[11px] font-bold text-on-surface flex items-center gap-1 transition cursor-pointer"
                                    title="Reimprimir Comanda"
                                >
                                    <span class="material-symbols-outlined text-[15px]">print</span>
                                    <span>Ticket</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="abrirDetalleHistorial({{ $hPedido->id }})"
                                    class="h-8 px-3 rounded-xl bg-primary hover:bg-primary-container text-[11px] font-bold text-on-primary flex items-center gap-1 transition cursor-pointer shadow-xs"
                                >
                                    <span>Detalle</span>
                                    <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full rounded-3xl border border-dashed border-surface-container-highest p-14 text-center text-on-surface-variant bg-surface-container-lowest">
                        <span class="material-symbols-outlined text-[48px] text-on-surface-variant/40 block mb-2">manage_search</span>
                        <p class="text-base font-extrabold text-on-surface">No se encontraron comandas con los filtros actuales</p>
                        <p class="text-xs text-on-surface-variant mt-1">Prueba seleccionando otra fecha, hora o ampliando el rango de búsqueda.</p>
                        <button
                            type="button"
                            wire:click="limpiarFiltrosHistorial"
                            class="mt-4 px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold shadow hover:bg-primary/90 transition inline-flex items-center gap-1 cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[16px]">restart_alt</span>
                            <span>Restablecer Filtros</span>
                        </button>
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- MODAL DETALLE DE COMANDA HISTÓRICA -->
    @if($historialDetalle)
        <div x-data @keydown.escape.window="$wire.cerrarDetalleHistorial()" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in" role="dialog" aria-modal="true">
            <div class="w-full max-w-2xl rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest space-y-4 max-h-[90vh] flex flex-col">
                <!-- Header Modal Detalle -->
                <div class="flex items-center justify-between border-b border-surface-container-high pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-on-surface">Detalle de Comanda {{ $historialDetalle->codigo }}</h3>
                            <span class="text-xs text-on-surface-variant font-medium">
                                {{ $historialDetalle->mesa ? 'Mesa ' . $historialDetalle->mesa->numero : ucfirst($historialDetalle->tipo) }} · Registrado: {{ $historialDetalle->created_at->format('d/m/Y H:i:s') }}
                            </span>
                        </div>
                    </div>
                    <button type="button" wire:click="cerrarDetalleHistorial" class="w-8 h-8 rounded-xl hover:bg-surface-container text-on-surface-variant flex items-center justify-center transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Tiempos de Elaboración -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3 rounded-2xl bg-surface-container-low border border-surface-container-high text-xs">
                    <div>
                        <span class="text-on-surface-variant text-[10px] block">Ingreso a Cocina:</span>
                        <span class="font-bold text-on-surface font-mono">{{ $historialDetalle->created_at->format('H:i:s') }}</span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant text-[10px] block">Finalizado / Despacho:</span>
                        <span class="font-bold text-on-surface font-mono">
                            {{ ($historialDetalle->hora_entrega ?? $historialDetalle->hora_despacho ?? $historialDetalle->pagado_en ?? $historialDetalle->updated_at)->format('H:i:s') }}
                        </span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant text-[10px] block">Tiempo Transcurrido:</span>
                        @php
                            $finDetalle = $historialDetalle->hora_entrega ?? $historialDetalle->hora_despacho ?? $historialDetalle->pagado_en ?? $historialDetalle->updated_at;
                            $mDetalle = $historialDetalle->created_at->diffInMinutes($finDetalle);
                        @endphp
                        <span class="font-black font-mono {{ $mDetalle <= 15 ? 'text-secondary' : 'text-amber-700' }}">{{ $mDetalle }} minutos</span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant text-[10px] block">Responsable:</span>
                        <span class="font-bold text-on-surface truncate block">{{ $historialDetalle->usuario?->name ?? 'Mesero' }}</span>
                    </div>
                </div>

                <!-- Desglose de Platos -->
                <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Platos y Elaboraciones de la Comanda:</h4>
                    @foreach($historialDetalle->items as $dItem)
                        @php $dCatColor = $dItem->producto?->categoria?->color ?? '#e11d48'; @endphp
                        <div class="p-3 rounded-2xl bg-surface-container-low border border-surface-container-high flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm font-bold text-white shrink-0 shadow-2xs"
                                     @style(['background-color: ' . $dCatColor])>
                                    @if(preg_match('/^[a-z0-9_]+$/', $dItem->producto?->categoria?->icono ?? ''))
                                        <span class="material-symbols-outlined text-[16px] text-white">{{ $dItem->producto->categoria->icono }}</span>
                                    @else
                                        <span>{{ $dItem->producto?->categoria?->icono ?? '🍽️' }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-black text-sm text-on-surface">{{ $dItem->cantidad }}x</span>
                                        <span class="text-xs font-bold text-on-surface truncate">{{ $dItem->nombre_producto }}</span>
                                    </div>
                                    @if($dItem->notas)
                                        <p class="text-[11px] text-primary font-bold mt-0.5 flex items-center gap-1">
                                            <span>✦</span>
                                            <span>{{ $dItem->notas }}</span>
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] uppercase font-bold text-on-surface-variant px-2 py-0.5 rounded bg-surface-container-high border border-surface-container-highest">
                                    {{ $dItem->area_cocina }}
                                </span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-secondary/15 text-secondary">
                                    {{ $dItem->estado_cocina }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Footer Modal -->
                <div class="pt-3 border-t border-surface-container-high flex items-center justify-between">
                    <button
                        type="button"
                        wire:click="abrirComanda({{ $historialDetalle->id }})"
                        class="h-10 px-4 rounded-xl border border-surface-container-high bg-surface-container hover:bg-surface-container-high text-xs font-bold text-on-surface flex items-center gap-1.5 transition cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">print</span>
                        <span>Reimprimir Comanda</span>
                    </button>
                    <button
                        type="button"
                        wire:click="cerrarDetalleHistorial"
                        class="h-10 px-5 rounded-xl bg-primary hover:bg-primary-container text-xs font-bold text-on-primary shadow transition cursor-pointer"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL IMPRESIÓN TICKET TÉRMICO DE COMANDA -->
    @if($comandaImpresion && $comandaImpresion->items->isNotEmpty())
        <div x-data @keydown.escape.window="$wire.cerrarComanda()" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-comanda-title" class="print-ticket-termico w-full max-w-sm rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest font-mono text-xs">
                <!-- Comanda Ticket Header -->
                <div class="text-center border-b border-dashed border-surface-container-high pb-4">
                    <p id="modal-comanda-title" class="text-base font-black tracking-tight text-primary">🍽️ RESTOMASTER 🍽️</p>
                    <p class="text-[11px] text-on-surface-variant font-bold">COMANDA DE COCINA</p>
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

                <!-- Ticket Line Items -->
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
                    <p class="font-bold text-on-surface">DOCUMENTO DE CONTROL INTERNO</p>
                    <p class="text-[9px]">{{ $comandaImpresion->items->sum('cantidad') }} platos · {{ $comandaImpresion->items->count() }} líneas</p>
                </div>

                <!-- Close / Print buttons -->
                <div class="no-print mt-5 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        onclick="window.print()"
                        class="min-h-[44px] rounded-xl border border-surface-container-high bg-surface-container py-2.5 px-3 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[16px]">print</span>
                        <span>Imprimir</span>
                    </button>
                    <button
                        type="button"
                        wire:click="cerrarComanda"
                        class="min-h-[44px] rounded-xl bg-primary py-2.5 px-3 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container cursor-pointer flex items-center justify-center"
                    >
                        ✓ Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
