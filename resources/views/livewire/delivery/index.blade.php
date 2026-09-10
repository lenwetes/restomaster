<?php

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\PedidoService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $filtroEstado = 'todos'; // 'todos', 'en_cocina', 'listo', 'en_ruta', 'entregado'
    public string $filtroCanal = 'todos'; // 'todos', 'web', 'whatsapp', 'telefono'
    public ?int $pedidoSeleccionadoId = null;

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroCanal(): void
    {
        $this->resetPage();
    }

    // Modales
    public bool $mostrarModalAsignar = false;
    public bool $mostrarModalCobroEntrega = false;
    public bool $mostrarModalNuevo = false;

    // Asignación
    public ?int $repartidorIdSeleccionado = null;

    // Cobro contra entrega
    public string $metodoCobro = 'efectivo';
    public float $montoRecibido = 0;

    // Form Nuevo Pedido Manual
    public array $nuevoPedido = [
        'nombre_cliente' => '',
        'telefono_cliente' => '',
        'direccion_delivery' => '',
        'canal_origen' => 'whatsapp',
        'costo_envio' => 8000,
        'producto_id' => null,
        'cantidad' => 1,
        'notas' => '',
    ];

    public function abrirModalAsignar(int $pedidoId): void
    {
        $this->pedidoSeleccionadoId = $pedidoId;
        $pedido = Pedido::findOrFail($pedidoId);
        $this->repartidorIdSeleccionado = $pedido->repartidor_id;
        $this->mostrarModalAsignar = true;
    }

    public function asignarRepartidor(): void
    {
        $this->validate([
            'repartidorIdSeleccionado' => 'required|exists:users,id',
        ]);

        $pedido = Pedido::findOrFail($this->pedidoSeleccionadoId);
        $repartidor = User::findOrFail($this->repartidorIdSeleccionado);

        app(DeliveryService::class)->asignarRepartidor($pedido, $repartidor);
        $this->mostrarModalAsignar = false;
        $this->dispatch('notificacion', ['mensaje' => 'Motorizado asignado con éxito.', 'tipo' => 'success']);
    }

    public function marcarSalida(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);
        try {
            app(DeliveryService::class)->marcarSalida($pedido);
            $this->dispatch('notificacion', ['mensaje' => "Pedido #{$pedido->codigo} en ruta con motorizado.", 'tipo' => 'success']);
        } catch (\Exception $e) {
            $this->dispatch('notificacion', ['mensaje' => $e->getMessage(), 'tipo' => 'error']);
        }
    }

    public function abrirModalEntrega(int $pedidoId): void
    {
        $this->pedidoSeleccionadoId = $pedidoId;
        $pedido = Pedido::findOrFail($pedidoId);
        $this->montoRecibido = (float) $pedido->total;
        $this->metodoCobro = $pedido->metodo_pago ?? 'efectivo';

        if ($pedido->estado === 'pagado') {
            // Ya está pagado (en línea), marcar directo
            app(DeliveryService::class)->marcarEntregado($pedido);
            $this->dispatch('notificacion', ['mensaje' => "Pedido #{$pedido->codigo} entregado al comensal.", 'tipo' => 'success']);
        } else {
            $this->mostrarModalCobroEntrega = true;
        }
    }

    public function confirmarEntregaYCobro(): void
    {
        $pedido = Pedido::findOrFail($this->pedidoSeleccionadoId);
        app(DeliveryService::class)->marcarEntregado($pedido, $this->metodoCobro, $this->montoRecibido);
        $this->mostrarModalCobroEntrega = false;
        $this->dispatch('notificacion', ['mensaje' => "Pedido #{$pedido->codigo} entregado y cobro registrado.", 'tipo' => 'success']);
    }

    public function liquidarRepartidor(int $repartidorId): void
    {
        $repartidor = User::findOrFail($repartidorId);
        $turnoActivo = TurnoCaja::where('estado', 'abierto')->latest()->first();

        if (! $turnoActivo) {
            $this->dispatch('notificacion', ['mensaje' => 'No hay un turno de caja abierto para registrar la liquidación.', 'tipo' => 'error']);
            return;
        }

        $montoLiquidado = app(DeliveryService::class)->liquidarRecaudoRepartidor($repartidor, $turnoActivo);
        if ($montoLiquidado > 0) {
            $this->dispatch('notificacion', [
                'mensaje' => "Liquidado $ " . number_format($montoLiquidado) . " COP del motorizado {$repartidor->name} en Caja #01.",
                'tipo' => 'success',
            ]);
        } else {
            $this->dispatch('notificacion', ['mensaje' => 'El repartidor no tiene cobros en efectivo pendientes de liquidar.', 'tipo' => 'info']);
        }
    }

    public function abrirModalNuevo(): void
    {
        $primerProd = Producto::where('activo', true)->first();
        $this->nuevoPedido = [
            'nombre_cliente' => '',
            'telefono_cliente' => '',
            'direccion_delivery' => '',
            'canal_origen' => 'whatsapp',
            'costo_envio' => 8000,
            'producto_id' => $primerProd?->id,
            'cantidad' => 1,
            'notas' => '',
        ];
        $this->mostrarModalNuevo = true;
    }

    public function guardarNuevoPedido(): void
    {
        $this->validate([
            'nuevoPedido.nombre_cliente' => 'required|string|min:3',
            'nuevoPedido.telefono_cliente' => 'required|string|min:7',
            'nuevoPedido.direccion_delivery' => 'required|string|min:5',
            'nuevoPedido.producto_id' => 'required|exists:productos,id',
        ]);

        $turnoActivo = TurnoCaja::where('estado', 'abierto')->latest()->first();
        $pedido = app(DeliveryService::class)->crearPedidoDelivery(array_merge($this->nuevoPedido, [
            'turno_caja_id' => $turnoActivo?->id,
        ]));

        // Agregar ítem
        $producto = Producto::findOrFail($this->nuevoPedido['producto_id']);
        app(PedidoService::class)->agregarItem(
            $pedido,
            $producto,
            (int) $this->nuevoPedido['cantidad'],
            $this->nuevoPedido['notas'] ?? null
        );

        $pedido->update(['estado' => 'listo']); // Pasa a listo en pase

        $this->mostrarModalNuevo = false;
        $this->dispatch('notificacion', ['mensaje' => "Pedido delivery #{$pedido->codigo} creado y listo para despacho.", 'tipo' => 'success']);
    }

    public function with(): array
    {
        $query = Pedido::with(['cliente', 'direccion', 'repartidor', 'items.producto'])
            ->where('tipo', 'delivery');

        if ($this->filtroEstado === 'en_cocina') {
            $query->whereIn('estado', ['creado', 'en_cocina', 'en_proceso']);
        } elseif ($this->filtroEstado === 'listo') {
            $query->where('estado', 'listo')->whereIn('estado_delivery', ['pendiente', 'asignado']);
        } elseif ($this->filtroEstado === 'en_ruta') {
            $query->where('estado_delivery', 'en_ruta');
        } elseif ($this->filtroEstado === 'entregado') {
            $query->where('estado_delivery', 'entregado');
        }

        if ($this->filtroCanal !== 'todos') {
            $query->where('canal_origen', $this->filtroCanal);
        }

        $pedidos = $query->latest()->paginate(30);
        $metricas = app(DeliveryService::class)->obtenerMetricasDelivery();
        $flota = app(DeliveryService::class)->obtenerFlotaMotorizados();
        $productos = Producto::where('activo', true)->orderBy('nombre')->get();

        return [
            'pedidos' => $pedidos,
            'metricas' => $metricas,
            'flota' => $flota,
            'productos' => $productos,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Sub-header Operativo & Resumen de Métricas Flash (Stitch PED-04) -->
    <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-secondary/15 text-secondary font-extrabold text-[11px] border border-secondary/30">
                    <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                    Flota Activa · Medellín Valle de Aburrá
                </span>
                <span class="text-on-surface-variant text-xs">•</span>
                <span class="text-xs font-semibold text-on-surface-variant">
                    Sede El Poblado MDE-01
                </span>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-on-surface mt-1">
                Cola de Despacho & Flota Delivery
            </h1>
            <p class="text-xs text-on-surface-variant max-w-3xl mt-0.5">
                Control de pedidos a domicilio, trazabilidad de ruta, asignación de motorizados y recaudo contra entrega.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                wire:click="abrirModalNuevo"
                class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-xs font-black text-on-primary shadow-md shadow-primary/25 hover:bg-primary-container active:scale-95 transition-all"
            >
                <span class="material-symbols-outlined text-[20px]">add_circle</span>
                <span>+ Nuevo Pedido Manual</span>
            </button>
        </div>
    </header>

    <!-- Cuadrícula de 5 Métricas Operativas en Vivo (Bento Flash) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <!-- Métrica 1: Despachados Hoy -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Despachados Hoy</span>
                <div class="w-8 h-8 rounded-xl bg-surface-container-high flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl font-black tracking-tight text-on-surface">{{ $metricas['despachados_hoy'] }}</span>
                <span class="text-[11px] font-bold text-secondary">pedidos</span>
            </div>
            <span class="text-[11px] text-on-surface-variant mt-1">Corte turno activo</span>
        </div>

        <!-- Métrica 2: En Ruta Activa -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">En Ruta Activa</span>
                <div class="w-8 h-8 rounded-xl bg-secondary/15 flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl font-black tracking-tight text-secondary">{{ $metricas['en_ruta'] }}</span>
                <span class="text-[11px] font-bold text-on-surface-variant">en moto</span>
            </div>
            <div class="w-full bg-surface-container-high h-1.5 rounded-full mt-2 overflow-hidden">
                <div class="bg-secondary h-full rounded-full w-3/4"></div>
            </div>
        </div>

        <!-- Métrica 3: Listos en Pase -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Listos en Pase</span>
                <div class="w-8 h-8 rounded-xl bg-primary-fixed flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">notifications_active</span>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl font-black tracking-tight text-primary">{{ $metricas['listos_en_pase'] }}</span>
                <span class="text-[11px] font-bold text-primary">esperando moto</span>
            </div>
            <span class="text-[11px] text-error font-extrabold mt-1 flex items-center gap-1">
                <span class="material-symbols-outlined text-[13px]">warning</span> Empacado listo
            </span>
        </div>

        <!-- Métrica 4: Tiempo Promedio SLA -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Tiempo Promedio</span>
                <div class="w-8 h-8 rounded-xl bg-tertiary-fixed flex items-center justify-center text-tertiary">
                    <span class="material-symbols-outlined text-[18px]">timer</span>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl font-black tracking-tight text-on-surface">{{ $metricas['tiempo_promedio_min'] }}</span>
                <span class="text-xs font-bold text-on-surface-variant">min</span>
            </div>
            <span class="text-[11px] text-secondary font-bold mt-1 flex items-center gap-0.5">
                <span class="material-symbols-outlined text-[13px]">thumb_up</span> Meta &lt; 35 min
            </span>
        </div>

        <!-- Métrica 5: Recaudo Pendiente -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between col-span-2 md:col-span-3 lg:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Recaudo en Calle</span>
                <div class="w-8 h-8 rounded-xl bg-surface-container-high flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">payments</span>
                </div>
            </div>
            <div class="mt-2 flex flex-col">
                <span class="text-xl font-black tracking-tight text-primary font-mono leading-tight">
                    $ {{ number_format($metricas['recaudo_pendiente']) }}
                </span>
                <span class="text-[10px] text-on-surface-variant font-medium">Efectivo a liquidar</span>
            </div>
            <span class="text-[11px] text-secondary font-extrabold mt-1">Retorno a Caja #01</span>
        </div>
    </div>

    <!-- Barra de Filtros de Estados & Canales -->
    <div class="bg-surface-container-lowest rounded-3xl p-5 border border-outline-variant/20 shadow-sm space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <!-- Tabs de Estado Operativo -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 scrollbar-none">
                <button
                    wire:click="$set('filtroEstado', 'todos')"
                    class="h-9 px-3.5 rounded-full text-xs font-bold transition-all {{ $filtroEstado === 'todos' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    Todos los envíos ({{ $pedidos->total() }})
                </button>
                <button
                    wire:click="$set('filtroEstado', 'en_cocina')"
                    class="h-9 px-3.5 rounded-full text-xs font-bold transition-all {{ $filtroEstado === 'en_cocina' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    En Preparación KDS
                </button>
                <button
                    wire:click="$set('filtroEstado', 'listo')"
                    class="h-9 px-3.5 rounded-full text-xs font-bold flex items-center gap-1 transition-all {{ $filtroEstado === 'listo' ? 'bg-primary-fixed text-on-primary-fixed shadow-sm border border-primary-fixed-dim' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-ping"></span>
                    Listos en Pase ({{ $metricas['listos_en_pase'] }})
                </button>
                <button
                    wire:click="$set('filtroEstado', 'en_ruta')"
                    class="h-9 px-3.5 rounded-full text-xs font-bold transition-all {{ $filtroEstado === 'en_ruta' ? 'bg-secondary text-on-secondary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    En Ruta / Motorizado ({{ $metricas['en_ruta'] }})
                </button>
                <button
                    wire:click="$set('filtroEstado', 'entregado')"
                    class="h-9 px-3.5 rounded-full text-xs font-bold transition-all {{ $filtroEstado === 'entregado' ? 'bg-surface-container-high text-on-surface border border-outline-variant/40' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    Entregados Turno
                </button>
            </div>

            <!-- Filtro por Canal de Origen -->
            <div class="flex items-center gap-1.5 overflow-x-auto self-start lg:self-auto">
                <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider mr-1">Canal:</span>
                <button
                    wire:click="$set('filtroCanal', 'todos')"
                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition-colors {{ $filtroCanal === 'todos' ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    Todos
                </button>
                <button
                    wire:click="$set('filtroCanal', 'web')"
                    class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1 transition-colors {{ $filtroCanal === 'web' ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Aura Web
                </button>
                <button
                    wire:click="$set('filtroCanal', 'whatsapp')"
                    class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1 transition-colors {{ $filtroCanal === 'whatsapp' ? 'bg-secondary text-on-secondary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> WhatsApp
                </button>
                <button
                    wire:click="$set('filtroCanal', 'telefono')"
                    class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1 transition-colors {{ $filtroCanal === 'telefono' ? 'bg-tertiary text-on-tertiary' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span> Teléfono
                </button>
            </div>
        </div>
    </div>

    <!-- Layout de Despacho (Split 65% Comandas Activas / 35% Gestión Flota) -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
        <!-- Columna Principal: Comandas de Despacho (8 Cols) -->
        <div class="xl:col-span-8 space-y-4">
            @forelse($pedidos as $p)
                @php
                    $esListo = $p->estado === 'listo' && in_array($p->estado_delivery, ['pendiente', 'asignado']);
                    $enRuta = $p->estado_delivery === 'en_ruta';
                    $esEntregado = $p->estado_delivery === 'entregado';
                @endphp
                <div class="bg-surface-container-lowest rounded-3xl p-6 border border-outline-variant/20 shadow-sm hover:shadow-md transition-all space-y-4 relative overflow-hidden">
                    <!-- Ribbon Superior de Urgencia -->
                    @if($esListo)
                        <div class="absolute top-0 left-0 right-0 h-1.5 bg-primary animate-pulse"></div>
                    @elseif($enRuta)
                        <div class="absolute top-0 left-0 right-0 h-1.5 bg-secondary"></div>
                    @endif

                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pt-1">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-base {{ $esListo ? 'bg-primary-fixed text-primary' : ($enRuta ? 'bg-secondary/15 text-secondary' : 'bg-surface-container-high text-on-surface') }}">
                                <span class="material-symbols-outlined text-[26px]">
                                    {{ $esListo ? 'kitchen' : ($enRuta ? 'two_wheeler' : 'check_circle') }}
                                </span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-base font-black text-on-surface font-mono">#{{ $p->codigo }}</span>
                                    @if($esListo)
                                        <span class="px-2.5 py-0.5 rounded-full bg-primary-fixed text-primary text-[10px] font-extrabold flex items-center gap-1 border border-primary-fixed-dim">
                                            <span class="material-symbols-outlined text-[13px]">timer</span> Listo en Pase
                                        </span>
                                    @elseif($enRuta)
                                        <span class="px-2.5 py-0.5 rounded-full bg-secondary/15 text-secondary text-[10px] font-extrabold flex items-center gap-1 border border-secondary/30">
                                            <span class="material-symbols-outlined text-[13px]">motorcycle</span> En Ruta
                                        </span>
                                    @elseif($esEntregado)
                                        <span class="px-2.5 py-0.5 rounded-full bg-surface-container-high text-on-surface text-[10px] font-extrabold border border-outline-variant/30">
                                            ✓ Entregado
                                        </span>
                                    @endif

                                    <span class="px-2 py-0.5 rounded-md bg-surface-container-low text-on-surface text-[10px] font-extrabold uppercase border border-outline-variant/20">
                                        {{ $p->canal_origen }}
                                    </span>
                                </div>
                                <span class="text-xs text-on-surface-variant font-medium mt-0.5 block">
                                    Creado hace {{ $p->created_at->diffForHumans(null, true) }} · Sede El Poblado
                                </span>
                            </div>
                        </div>

                        <div class="text-left sm:text-right">
                            <span class="text-xl font-black text-on-surface font-mono block">$ {{ number_format($p->total) }} COP</span>
                            @if($p->estado === 'pagado')
                                <span class="text-xs text-secondary font-extrabold flex items-center sm:justify-end gap-1">
                                    <span class="material-symbols-outlined text-[15px]">verified</span> Pagado ({{ $p->metodo_pago }})
                                </span>
                            @else
                                <span class="text-xs text-primary font-black flex items-center sm:justify-end gap-1">
                                    <span class="material-symbols-outlined text-[15px]">payments</span> Cobro Contra Entrega
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Datos del Destinatario y Dirección -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-surface-container-low p-4 rounded-2xl text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Destinatario</span>
                            <span class="font-extrabold text-on-surface text-sm block mt-0.5">{{ $p->nombre_cliente }}</span>
                            @if($p->telefono_cliente)
                                <a href="tel:{{ $p->telefono_cliente }}" class="font-bold text-secondary hover:underline flex items-center gap-1 mt-1">
                                    <span class="material-symbols-outlined text-[14px]">call</span>
                                    {{ $p->telefono_cliente }}
                                </a>
                            @endif
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Dirección de Entrega</span>
                            <p class="font-bold text-on-surface mt-0.5 flex items-start gap-1">
                                <span class="material-symbols-outlined text-[16px] text-primary shrink-0 mt-0.5">location_on</span>
                                <span>{{ $p->direccion_delivery ?: ($p->direccion?->direccion_completa ?? 'Dirección no especificada') }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Ítems de la Comanda -->
                    <div class="space-y-1.5">
                        <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Contenido del Pedido:</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach($p->items as $it)
                                <div class="px-3 py-1.5 rounded-xl bg-surface-container-low border border-outline-variant/15 flex items-center gap-2 text-xs">
                                    <span class="px-1.5 py-0.5 rounded bg-primary-fixed text-on-primary-fixed font-black text-[10px]">
                                        {{ $it->cantidad }}x
                                    </span>
                                    <span class="font-bold text-on-surface">{{ $it->nombre_producto }}</span>
                                    @if($it->notas)
                                        <span class="text-[10px] text-on-surface-variant italic">({{ $it->notas }})</span>
                                    @endif
                                </div>
                            @endforeach
                            @if($p->costo_envio > 0)
                                <div class="px-2.5 py-1.5 rounded-xl bg-surface-container-high text-[11px] font-bold text-on-surface-variant flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">two_wheeler</span>
                                    Envío: $ {{ number_format($p->costo_envio) }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Repartidor Asignado / Barra Táctil de Acciones (56px touch standard) -->
                    <div class="pt-2 border-t border-outline-variant/15 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-secondary">person_pin_circle</span>
                            @if($p->repartidor)
                                <span class="text-xs font-bold text-on-surface">
                                    Motorizado: <strong class="text-secondary font-black">{{ $p->repartidor->name }}</strong>
                                </span>
                            @else
                                <span class="text-xs text-on-surface-variant font-medium">Sin motorizado asignado</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            @if(! $esEntregado)
                                <button
                                    wire:click="abrirModalAsignar({{ $p->id }})"
                                    class="h-12 px-4 rounded-2xl bg-surface-container-low border border-outline-variant/30 text-on-surface font-extrabold text-xs hover:bg-surface-container-high active:scale-95 transition-all flex items-center gap-1.5"
                                >
                                    <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                                    <span>{{ $p->repartidor_id ? 'Cambiar Moto' : 'Asignar Moto' }}</span>
                                </button>
                            @endif

                            @if($p->estado_delivery === 'asignado')
                                <button
                                    wire:click="marcarSalida({{ $p->id }})"
                                    class="h-12 px-5 rounded-2xl bg-primary text-on-primary font-black text-xs hover:bg-primary-container shadow-md active:scale-95 transition-all flex items-center gap-1.5"
                                >
                                    <span class="material-symbols-outlined text-[18px]">near_me</span>
                                    <span>Marcar Salida</span>
                                </button>
                            @endif

                            @if($p->estado_delivery === 'en_ruta')
                                <button
                                    wire:click="abrirModalEntrega({{ $p->id }})"
                                    class="h-12 px-5 rounded-2xl bg-secondary text-on-secondary font-black text-xs hover:bg-secondary/90 shadow-md active:scale-95 transition-all flex items-center gap-1.5"
                                >
                                    <span class="material-symbols-outlined text-[18px]">done_all</span>
                                    <span>Marcar Entregado</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center rounded-3xl bg-surface-container-lowest border border-outline-variant/20">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">two_wheeler</span>
                    <p class="text-xs font-bold text-on-surface">No hay pedidos en esta sección de despacho.</p>
                    <p class="text-[11px] text-on-surface-variant mt-0.5">Los nuevos pedidos de delivery aparecerán automáticamente aquí.</p>
                </div>
            @endforelse

            @if($pedidos->hasPages())
                <div class="pt-4">
                    {{ $pedidos->links() }}
                </div>
            @endif
        </div>

        <!-- Columna Lateral: Flota de Motorizados (4 Cols) -->
        <aside class="xl:col-span-4 sticky top-20 space-y-4">
            <div class="rounded-3xl bg-surface-container-lowest p-6 border border-outline-variant/20 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-outline-variant/15">
                    <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-secondary">sports_motorsports</span>
                        Flota de Motorizados ({{ $flota->count() }})
                    </h3>
                    <span class="text-[10px] font-bold bg-secondary/15 text-secondary px-2 py-0.5 rounded-full border border-secondary/30">
                        En Servicio
                    </span>
                </div>

                <div class="space-y-3">
                    @forelse($flota as $moto)
                        @php
                            $pedidosEnRuta = $moto->pedidos_en_ruta_count ?? 0;
                            $efectivoPendiente = (float) ($moto->efectivo_pendiente_sum ?? 0);
                        @endphp
                        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/20 space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-10 h-10 rounded-xl bg-surface-container-high text-on-surface flex items-center justify-center font-black text-xs border border-outline-variant/20">
                                        <span class="material-symbols-outlined text-[20px] text-secondary">moped</span>
                                    </div>
                                    <div>
                                        <span class="font-extrabold text-on-surface text-xs block leading-tight">{{ $moto->name }}</span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $moto->telefono ?? 'Sede Medellín' }}</span>
                                    </div>
                                </div>

                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $pedidosEnRuta > 0 ? 'bg-secondary/15 text-secondary border border-secondary/30' : 'bg-surface-container-high text-on-surface-variant' }}">
                                    {{ $pedidosEnRuta > 0 ? "{$pedidosEnRuta} en ruta" : 'Disponible' }}
                                </span>
                            </div>

                            <!-- Estado de Recaudo en Efectivo -->
                            <div class="pt-2 border-t border-outline-variant/15 flex items-center justify-between text-xs">
                                <div>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant block">Efectivo a Liquidar</span>
                                    <span class="font-black text-on-surface font-mono">$ {{ number_format($efectivoPendiente) }} COP</span>
                                </div>

                                @if($efectivoPendiente > 0)
                                    <button
                                        wire:click="liquidarRepartidor({{ $moto->id }})"
                                        class="px-3 py-1.5 rounded-xl bg-primary text-on-primary text-[11px] font-black hover:bg-primary-container active:scale-95 transition-all shadow-sm"
                                    >
                                        Liquidar en Caja
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-on-surface-variant">No hay motorizados registrados.</p>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>

    <!-- Modal: Asignar Repartidor -->
    @if($mostrarModalAsignar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[24px] text-primary">two_wheeler</span>
                        <h3 class="text-base font-extrabold text-on-surface">Asignar Motorizado</h3>
                    </div>
                    <button wire:click="$set('mostrarModalAsignar', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="text-xs font-bold text-on-surface-variant">Seleccionar Repartidor:</label>
                    <select wire:model="repartidorIdSeleccionado" class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                        <option value="">Seleccione motorizado...</option>
                        @foreach($flota as $m)
                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                    @error('repartidorIdSeleccionado') <span class="text-xs text-error font-bold block">{{ $message }}</span> @enderror
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalAsignar', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant">
                        Cancelar
                    </button>
                    <button wire:click="asignarRepartidor" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md">
                        ✓ Asignar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Confirmar Entrega y Cobro Contra Entrega -->
    @if($mostrarModalCobroEntrega)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[24px] text-secondary">payments</span>
                        <h3 class="text-base font-extrabold text-on-surface">Cobro Contra Entrega</h3>
                    </div>
                    <button wire:click="$set('mostrarModalCobroEntrega', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Método de Cobro:</label>
                        <select wire:model="metodoCobro" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface">
                            <option value="efectivo">Efectivo en destino</option>
                            <option value="tarjeta">Datáfono inalámbrico / Tarjeta</option>
                            <option value="transferencia">Transferencia / Nequi / Bancolombia</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Monto Recibido ($ COP):</label>
                        <input type="number" wire:model="montoRecibido" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-base font-black text-on-surface font-mono" />
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalCobroEntrega', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant">
                        Cancelar
                    </button>
                    <button wire:click="confirmarEntregaYCobro" class="rounded-2xl bg-secondary py-3 text-xs font-black text-on-secondary shadow-md">
                        ✓ Confirmar Cobro
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Nuevo Pedido Manual Delivery -->
    @if($mostrarModalNuevo)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[24px] text-primary">add_circle</span>
                        <h3 class="text-base font-extrabold text-on-surface">Nuevo Pedido Delivery Manual</h3>
                    </div>
                    <button wire:click="$set('mostrarModalNuevo', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Nombre Comensal *:</label>
                            <input type="text" wire:model="nuevoPedido.nombre_cliente" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" placeholder="Nombre completo" />
                            @error('nuevoPedido.nombre_cliente') <span class="text-xs text-error font-bold block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Teléfono / WhatsApp *:</label>
                            <input type="text" wire:model="nuevoPedido.telefono_cliente" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" placeholder="+57 300 000 0000" />
                            @error('nuevoPedido.telefono_cliente') <span class="text-xs text-error font-bold block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Dirección Completa *:</label>
                        <input type="text" wire:model="nuevoPedido.direccion_delivery" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface" placeholder="Ej: Cra 43A # 1Sur-150 Apto 802, El Poblado" />
                        @error('nuevoPedido.direccion_delivery') <span class="text-xs text-error font-bold block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Canal:</label>
                            <select wire:model="nuevoPedido.canal_origen" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface">
                                <option value="whatsapp">WhatsApp Directo</option>
                                <option value="telefono">Llamada Telefónica</option>
                                <option value="web">Aura Web</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Costo Envío ($ COP):</label>
                            <input type="number" wire:model="nuevoPedido.costo_envio" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-outline-variant/15">
                        <div class="col-span-2">
                            <label class="text-xs font-bold text-on-surface-variant">Plato Principal:</label>
                            <select wire:model="nuevoPedido.producto_id" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface">
                                @foreach($productos as $pr)
                                    <option value="{{ $pr->id }}">{{ $pr->nombre }} (${{ number_format($pr->precio) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Cantidad:</label>
                            <input type="number" min="1" wire:model="nuevoPedido.cantidad" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface font-mono" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalNuevo', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant">
                        Cancelar
                    </button>
                    <button wire:click="guardarNuevoPedido" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md">
                        ✓ Despachar Pedido
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
