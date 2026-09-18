<?php

use App\Models\Cliente;
use App\Models\DireccionCliente;
use App\Models\MovimientoPuntos;
use App\Services\ClienteService;
use App\Services\FidelizacionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroTier = 'todos';
    public bool $filtroAlergias = false;
    public ?int $clienteSeleccionadoId = null;

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroTier(): void
    {
        $this->resetPage();
    }

    // Modales
    public bool $mostrarModalNuevo = false;
    public bool $mostrarModalEditar = false;
    public bool $mostrarModalPuntos = false;
    public bool $mostrarModalDireccion = false;

    // Form Nuevo Cliente
    public array $nuevo = [
        'nombre' => '',
        'telefono' => '',
        'email' => '',
        'documento' => '',
        'tier' => 'ocasional',
        'puntos_fidelidad' => 0,
        'alergias' => '',
        'preferencias' => '',
        'notas' => '',
        'direccion' => '',
        'referencia_apto' => '',
        'barrio_ciudad' => 'Medellín',
        'notas_entrega' => '',
        'acepta_tratamiento_datos' => false,
        'autoriza_whatsapp' => true,
        'autoriza_email' => true,
    ];

    // Form Edición
    public array $edicion = [
        'nombre' => '',
        'telefono' => '',
        'email' => '',
        'documento' => '',
        'tier' => 'ocasional',
        'alergias' => '',
        'preferencias' => '',
        'notas' => '',
        'acepta_tratamiento_datos' => false,
        'autoriza_whatsapp' => true,
        'autoriza_email' => true,
    ];

    // Form Ajuste Puntos
    public int $puntosAjuste = 100;
    public string $tipoAjuste = 'suma'; // 'suma' o 'resta'
    public string $motivoAjuste = 'Cortesía de fidelización / Ajuste manual';

    // Form Nueva Dirección
    public array $nuevaDireccion = [
        'etiqueta' => 'Entrega',
        'direccion' => '',
        'referencia_apto' => '',
        'barrio_ciudad' => 'El Poblado, Medellín',
        'telefono_contacto' => '',
        'notas_entrega' => '',
        'es_predeterminada' => false,
    ];

    public function mount(): void
    {
        $primerCliente = Cliente::where('activo', true)->orderByDesc('total_gastado')->first();
        if ($primerCliente) {
            $this->clienteSeleccionadoId = $primerCliente->id;
        }
    }

    public function seleccionarCliente(int $id): void
    {
        $this->clienteSeleccionadoId = $id;
    }

    public function abrirModalNuevo(): void
    {
        $this->nuevo = [
            'nombre' => '',
            'telefono' => '',
            'email' => '',
            'documento' => '',
            'tier' => 'ocasional',
            'puntos_fidelidad' => 0,
            'alergias' => '',
            'preferencias' => '',
            'notas' => '',
            'direccion' => '',
            'referencia_apto' => '',
            'barrio_ciudad' => 'Medellín',
            'notas_entrega' => '',
            'acepta_tratamiento_datos' => false,
            'autoriza_whatsapp' => true,
            'autoriza_email' => true,
        ];
        $this->mostrarModalNuevo = true;
    }

    public function guardarNuevo(): void
    {
        $this->authorize('create', Cliente::class);

        $this->validate([
            'nuevo.nombre' => 'required|string|min:3',
            'nuevo.telefono' => 'nullable|string|max:20',
            'nuevo.email' => 'nullable|email',
            'nuevo.tier' => 'required|in:ocasional,frecuente,vip,regular,gold,black',
            'nuevo.acepta_tratamiento_datos' => 'nullable|boolean',
            'nuevo.autoriza_whatsapp' => 'nullable|boolean',
            'nuevo.autoriza_email' => 'nullable|boolean',
        ]);

        if (! empty($this->nuevo['acepta_tratamiento_datos'])) {
            $this->nuevo['fecha_autorizacion_datos'] = now();
            $this->nuevo['canal_autorizacion_datos'] = 'crm_admin';
        }

        try {
            $cliente = app(ClienteService::class)->crear($this->nuevo);
            $this->clienteSeleccionadoId = $cliente->id;
            $this->mostrarModalNuevo = false;
            $this->dispatch('notificacion', ['mensaje' => 'Cliente registrado exitosamente.', 'tipo' => 'success']);
        } catch (\Exception $e) {
            $this->addError('nuevo.telefono', $e->getMessage());
        }
    }

    public function abrirModalEditar(): void
    {
        if (! $this->clienteSeleccionadoId) {
            return;
        }

        $cliente = Cliente::findOrFail($this->clienteSeleccionadoId);
        $this->edicion = [
            'nombre' => $cliente->nombre,
            'telefono' => $cliente->telefono ?? '',
            'email' => $cliente->email ?? '',
            'documento' => $cliente->documento ?? '',
            'tier' => $cliente->tier,
            'alergias' => $cliente->alergias ?? '',
            'preferencias' => $cliente->preferencias ?? '',
            'notas' => $cliente->notas ?? '',
            'acepta_tratamiento_datos' => (bool) $cliente->acepta_tratamiento_datos,
            'autoriza_whatsapp' => $cliente->autoriza_whatsapp ?? true,
            'autoriza_email' => $cliente->autoriza_email ?? true,
        ];
        $this->mostrarModalEditar = true;
    }

    public function guardarEdicion(): void
    {
        $cliente = Cliente::findOrFail($this->clienteSeleccionadoId);
        $this->authorize('update', $cliente);

        $this->validate([
            'edicion.nombre' => 'required|string|min:3',
            'edicion.telefono' => 'nullable|string|max:20',
            'edicion.email' => 'nullable|email',
            'edicion.tier' => 'required|in:ocasional,frecuente,vip,regular,gold,black',
            'edicion.acepta_tratamiento_datos' => 'nullable|boolean',
            'edicion.autoriza_whatsapp' => 'nullable|boolean',
            'edicion.autoriza_email' => 'nullable|boolean',
        ]);

        if (! empty($this->edicion['acepta_tratamiento_datos']) && ! $cliente->acepta_tratamiento_datos) {
            $this->edicion['fecha_autorizacion_datos'] = now();
            $this->edicion['canal_autorizacion_datos'] = 'crm_admin';
        }

        try {
            app(ClienteService::class)->actualizar($cliente, $this->edicion);
            $this->mostrarModalEditar = false;
            $this->dispatch('notificacion', ['mensaje' => 'Cliente actualizado.', 'tipo' => 'success']);
        } catch (\Exception $e) {
            $this->addError('edicion.telefono', $e->getMessage());
        }
    }

    public function abrirModalPuntos(): void
    {
        $this->puntosAjuste = 100;
        $this->tipoAjuste = 'suma';
        $this->motivoAjuste = 'Ajuste manual de fidelización';
        $this->mostrarModalPuntos = true;
    }

    public function ejecutarAjustePuntos(): void
    {
        $this->authorize('ajustarPuntos', Cliente::class);

        $this->validate([
            'puntosAjuste' => 'required|integer|min:1|max:100000',
            'tipoAjuste' => 'required|in:suma,resta',
            'motivoAjuste' => 'required|string|min:4',
        ]);

        $cliente = Cliente::findOrFail($this->clienteSeleccionadoId);
        $puntos = $this->tipoAjuste === 'suma' ? $this->puntosAjuste : -$this->puntosAjuste;

        app(FidelizacionService::class)->ajustarPuntos($cliente, $puntos, $this->motivoAjuste, Auth::user());
        $this->mostrarModalPuntos = false;
        $this->dispatch('notificacion', ['mensaje' => 'Puntos actualizados correctamente.', 'tipo' => 'success']);
    }

    public function abrirModalDireccion(): void
    {
        $cliente = Cliente::findOrFail($this->clienteSeleccionadoId);
        $this->nuevaDireccion = [
            'etiqueta' => 'Entrega',
            'direccion' => '',
            'referencia_apto' => '',
            'barrio_ciudad' => 'El Poblado, Medellín',
            'telefono_contacto' => $cliente->telefono,
            'notas_entrega' => '',
            'es_predeterminada' => false,
        ];
        $this->mostrarModalDireccion = true;
    }

    public function guardarDireccion(): void
    {
        $this->validate([
            'nuevaDireccion.direccion' => 'required|string|min:5',
        ]);

        $cliente = Cliente::findOrFail($this->clienteSeleccionadoId);
        $this->authorize('update', $cliente);

        app(ClienteService::class)->agregarDireccion($cliente, $this->nuevaDireccion);
        $this->mostrarModalDireccion = false;
        $this->dispatch('notificacion', ['mensaje' => 'Dirección agregada exitosamente.', 'tipo' => 'success']);
    }

    public function with(): array
    {
        $query = Cliente::with(['direcciones', 'direccionPredeterminada'])->where('activo', true);

        if ($this->busqueda !== '') {
            $t = trim($this->busqueda);
            $query->where(function ($q) use ($t) {
                $q->where('telefono', 'like', "%{$t}%")
                    ->orWhere('nombre', 'like', "%{$t}%")
                    ->orWhere('documento', 'like', "%{$t}%");
            });
        }

        if ($this->filtroTier !== 'todos') {
            $query->where('tier', $this->filtroTier);
        }

        if ($this->filtroAlergias) {
            $query->whereNotNull('alergias')->where('alergias', '!=', '');
        }

        $clientes = $query->orderByDesc('total_gastado')->paginate(25);

        $clienteSeleccionado = null;
        $historialPuntos = collect();
        $ultimosPedidos = collect();

        if ($this->clienteSeleccionadoId) {
            $clienteSeleccionado = Cliente::with(['direcciones', 'pedidos.items'])->find($this->clienteSeleccionadoId);
            if ($clienteSeleccionado) {
                $historialPuntos = MovimientoPuntos::where('cliente_id', $clienteSeleccionado->id)->latest()->take(6)->get();
                $ultimosPedidos = $clienteSeleccionado->pedidos()->latest()->take(4)->get();
            }
        }

        // Métricas Bento Flash
        $totalRegistrados = Cliente::where('activo', true)->count();
        $totalVip = Cliente::where('activo', true)->whereIn('tier', ['vip', 'black'])->count();
        $puntosTotales = (int) Cliente::where('activo', true)->sum('puntos_fidelidad');
        $respaldoCanjesCop = $puntosTotales * 10; // $10 COP por punto

        return [
            'clientes' => $clientes,
            'clienteSeleccionado' => $clienteSeleccionado,
            'historialPuntos' => $historialPuntos,
            'ultimosPedidos' => $ultimosPedidos,
            'totalRegistrados' => $totalRegistrados,
            'totalVip' => $totalVip,
            'puntosTotales' => $puntosTotales,
            'respaldoCanjesCop' => $respaldoCanjesCop,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header de Módulo y Acciones de Cabecera (Aura Gastro Expressive OS) -->
    <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full bg-primary-fixed text-on-primary-fixed font-bold text-[11px] uppercase tracking-wider border border-primary-fixed-dim">
                    Módulo Fidelización Haute
                </span>
                <span class="text-on-surface-variant text-xs">•</span>
                <span class="text-xs font-semibold text-secondary flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
                    CLI-01 · Sincronizado Central
                </span>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-on-surface mt-1">
                Clientes & Club Gourmet VIP
            </h1>
            <p class="text-xs text-on-surface-variant max-w-2xl mt-0.5">
                Directorio de comensales frecuentes, preferencias gastronómicas, direcciones delivery y programa de fidelización.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                wire:click="abrirModalNuevo"
                class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-xs font-black text-on-primary shadow-md shadow-primary/25 hover:bg-primary-container active:scale-95 transition-all"
            >
                <span class="material-symbols-outlined text-[20px]">person_add</span>
                <span>+ Nuevo Comensal</span>
            </button>
        </div>
    </header>

    <!-- 4 Bento Cards de KPIs en Porcelana Luminous y Blanco Puro -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- KPI 1: Total Clientes -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Total Comensales</span>
                <div class="w-9 h-9 rounded-xl bg-surface-container-high flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">group</span>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold tracking-tight text-on-surface">{{ number_format($totalRegistrados) }}</span>
                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full bg-secondary/15 text-secondary text-[11px] font-bold">
                    <span class="material-symbols-outlined text-[14px]">arrow_upward</span> Activos
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-outline-variant/15 flex items-center justify-between text-xs text-on-surface-variant">
                <span>Historial en sede</span>
                <span class="text-on-surface font-bold">El Poblado MDE-01</span>
            </div>
        </div>

        <!-- KPI 2: Nivel VIP / Imperial -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Club VIP & Imperial</span>
                <div class="w-9 h-9 rounded-xl bg-primary-fixed flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">stars</span>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold tracking-tight text-primary">{{ number_format($totalVip) }}</span>
                <span class="text-xs font-semibold text-on-surface-variant">Comensales Elite</span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-outline-variant/15 flex items-center justify-between text-xs text-on-surface-variant">
                <span>Ticket promedio VIP</span>
                <span class="text-secondary font-bold">$180.000+ COP</span>
            </div>
        </div>

        <!-- KPI 3: Puntos de Lealtad Activos -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Puntos en Circulación</span>
                <div class="w-9 h-9 rounded-xl bg-tertiary-fixed flex items-center justify-center text-tertiary">
                    <span class="material-symbols-outlined text-[20px]">loyalty</span>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold tracking-tight text-on-surface">{{ number_format($puntosTotales) }}</span>
                <span class="text-xs font-bold text-tertiary">pts activos</span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-outline-variant/15 flex items-center justify-between text-xs text-on-surface-variant">
                <span>Respaldo en canjes</span>
                <span class="text-on-surface font-bold">$ {{ number_format($respaldoCanjesCop) }} COP</span>
            </div>
        </div>

        <!-- KPI 4: Frecuencia & Retorno -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Tasa de Recompra</span>
                <div class="w-9 h-9 rounded-xl bg-secondary/15 flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined text-[20px]">repeat</span>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold tracking-tight text-secondary">78.4%</span>
                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full bg-secondary/15 text-secondary text-[11px] font-bold">
                    +4.2% mes
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-outline-variant/15 flex items-center justify-between text-xs text-on-surface-variant">
                <span>Ciclo promedio</span>
                <span class="text-on-surface font-bold">Cada 14 días</span>
            </div>
        </div>
    </div>

    <!-- Barra de Búsqueda y Filtros de Segmentación -->
    <div class="bg-surface-container-lowest rounded-3xl p-5 border border-outline-variant/20 shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row items-center gap-3">
            <div class="relative flex-1 w-full">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[22px]">search</span>
                <input
                    type="text"
                    wire:model.live.debounce.250ms="busqueda"
                    class="w-full h-12 pl-12 pr-10 rounded-2xl border border-outline-variant/30 bg-surface-container-low text-xs text-on-surface font-medium placeholder:text-on-surface-variant/70 focus:border-primary focus:ring-0 transition-colors"
                    placeholder="Buscar comensal por Teléfono (+57 3...), Nombre, Cédula / DNI..."
                />
                @if($busqueda)
                    <button wire:click="$set('busqueda', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-2 self-stretch sm:self-auto">
                <button
                    wire:click="$toggle('filtroAlergias')"
                    class="h-12 px-4 rounded-2xl border text-xs font-bold flex items-center gap-1.5 transition-all {{ $filtroAlergias ? 'bg-error/15 border-error/40 text-error' : 'bg-surface-container-low border-outline-variant/30 text-on-surface-variant hover:text-on-surface' }}"
                >
                    <span class="material-symbols-outlined text-[18px]">warning</span>
                    <span>Con Alergias</span>
                </button>
            </div>
        </div>

        <!-- Píldoras de Categoría / Tier -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-1 scrollbar-none">
            <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant mr-1">Categoría:</span>
            <button
                wire:click="$set('filtroTier', 'todos')"
                class="h-8 px-3 rounded-full text-xs font-bold transition-colors {{ $filtroTier === 'todos' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
            >
                Todos ({{ $totalRegistrados }})
            </button>
            <button
                wire:click="$set('filtroTier', 'vip')"
                class="h-8 px-3 rounded-full text-xs font-bold flex items-center gap-1.5 transition-colors {{ $filtroTier === 'vip' ? 'bg-primary-fixed text-on-primary-fixed shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
            >
                <span class="material-symbols-outlined text-[14px]">stars</span>
                VIP Club ({{ $totalVip }})
            </button>
            <button
                wire:click="$set('filtroTier', 'frecuente')"
                class="h-8 px-3 rounded-full text-xs font-bold flex items-center gap-1.5 transition-colors {{ $filtroTier === 'frecuente' ? 'bg-secondary text-on-secondary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
            >
                <span class="material-symbols-outlined text-[14px]">repeat</span>
                Frecuentes
            </button>
            <button
                wire:click="$set('filtroTier', 'ocasional')"
                class="h-8 px-3 rounded-full text-xs font-bold transition-colors {{ $filtroTier === 'ocasional' ? 'bg-surface-container-high text-on-surface border border-outline-variant/40 font-black' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' }}"
            >
                Ocasionales
            </button>
        </div>
    </div>

    <!-- Workspace Split: Directorio (7 Cols) & Inspector 360° (5 Cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Directorio de Clientes (Left Column - 7 Cols) -->
        <section class="lg:col-span-7 space-y-3">
            <div class="flex items-center justify-between px-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">contacts</span>
                        Directorio Activo ({{ $clientes->count() }})
                    </h2>
                </div>
                <span class="text-[11px] text-on-surface-variant font-medium">Click en tarjeta para ver ficha 360°</span>
            </div>

            <div class="space-y-3">
                @forelse($clientes as $c)
                    @php $badge = $c->badgeTier(); @endphp
                    <div
                        wire:click="seleccionarCliente({{ $c->id }})"
                        class="group p-4 rounded-3xl bg-surface-container-lowest border transition-all duration-200 cursor-pointer relative overflow-hidden shadow-sm hover:shadow-md {{ $clienteSeleccionadoId === $c->id ? 'border-2 border-primary shadow-md shadow-primary/10' : 'border-outline-variant/20 hover:border-outline-variant/40' }}"
                    >
                        @if($clienteSeleccionadoId === $c->id)
                            <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-primary"></div>
                        @endif

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pl-2">
                            <!-- Identidad del Comensal -->
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="relative shrink-0">
                                    <div class="w-12 h-12 rounded-2xl bg-surface-container-high overflow-hidden flex items-center justify-center text-primary font-black text-sm border border-outline-variant/20">
                                        {{ strtoupper(substr($c->nombre, 0, 2)) }}
                                    </div>
                                    @if($c->esVip())
                                        <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-primary flex items-center justify-center text-on-primary text-[10px] shadow-sm">
                                            <span class="material-symbols-outlined text-[13px]">star</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-col min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-extrabold text-on-surface truncate">{{ $c->nombre }}</span>
                                        <span class="px-2 py-0.5 rounded-full border text-[10px] font-extrabold {{ $badge['color'] }}">
                                            {{ $badge['label'] }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 text-xs text-on-surface-variant mt-1 flex-wrap font-medium">
                                        <span class="flex items-center gap-1 text-on-surface font-bold">
                                            <span class="material-symbols-outlined text-[14px] text-primary">call</span>
                                            {{ $c->telefono }}
                                        </span>
                                        @if($c->email)
                                            <span>•</span>
                                            <span class="truncate">{{ $c->email }}</span>
                                        @endif
                                    </div>

                                    @if($c->alergias)
                                        <div class="mt-2 flex items-center gap-1.5 flex-wrap">
                                            <span class="px-2 py-0.5 rounded-lg bg-error/15 text-error text-[10px] font-extrabold flex items-center gap-1 border border-error/30">
                                                <span class="material-symbols-outlined text-[12px]">block</span>
                                                {{ $c->alergias }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Métricas & Puntos -->
                            <div class="flex items-center justify-between sm:justify-end gap-4 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-outline-variant/15">
                                <div class="text-left sm:text-right">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant block">Consumo</span>
                                    <span class="text-xs font-black text-on-surface font-mono">$ {{ number_format($c->total_gastado) }}</span>
                                    <span class="text-[10px] text-on-surface-variant block">{{ $c->visitas_count }} pedidos</span>
                                </div>

                                <div class="flex flex-col items-end">
                                    <div class="px-2.5 py-1 rounded-xl bg-tertiary-fixed text-on-tertiary-fixed text-right border border-tertiary/20">
                                        <span class="text-[9px] font-bold uppercase block text-tertiary">Puntos</span>
                                        <span class="text-xs font-extrabold font-mono text-on-tertiary-fixed">{{ number_format($c->puntos_fidelidad) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center rounded-3xl bg-surface-container-lowest border border-outline-variant/20">
                        <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">person_off</span>
                        <p class="text-xs font-bold text-on-surface">No se encontraron comensales registrados.</p>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">Prueba con otro número telefónico o agrega un nuevo comensal.</p>
                    </div>
                @endforelse

                @if($clientes->hasPages())
                    <div class="pt-4">
                        {{ $clientes->links() }}
                    </div>
                @endif
            </div>
        </section>

        <!-- Inspector 360° de Cliente Seleccionado (Right Column - 5 Cols) -->
        <aside class="lg:col-span-5 sticky top-20 space-y-4">
            @if($clienteSeleccionado)
                <div class="rounded-3xl bg-surface-container-lowest p-6 border border-outline-variant/20 shadow-sm space-y-5">
                    <!-- Cabecera de la Ficha -->
                    <div class="flex items-start justify-between pb-4 border-b border-outline-variant/15">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-black text-lg border border-primary-fixed-dim">
                                {{ strtoupper(substr($clienteSeleccionado->nombre, 0, 2)) }}
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-on-surface">{{ $clienteSeleccionado->nombre }}</h3>
                                <p class="text-xs text-on-surface-variant font-medium">{{ $clienteSeleccionado->documento ?? 'Sin documento registrado' }}</p>
                                <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $clienteSeleccionado->badgeTier()['color'] }}">
                                    {{ $clienteSeleccionado->badgeTier()['label'] }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <button
                                wire:click="abrirModalEditar"
                                class="p-2 rounded-xl bg-surface-container-low hover:bg-surface-container-high text-on-surface transition-colors"
                                title="Editar datos"
                            >
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                        </div>
                    </div>

                    <!-- Datos Rápidos de Contacto -->
                    <div class="grid grid-cols-2 gap-3 bg-surface-container-low p-3.5 rounded-2xl text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Teléfono / WhatsApp</span>
                            <a href="tel:{{ $clienteSeleccionado->telefono }}" class="font-extrabold text-secondary hover:underline flex items-center gap-1 mt-0.5">
                                <span class="material-symbols-outlined text-[14px]">call</span>
                                {{ $clienteSeleccionado->telefono }}
                            </a>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Total Invertido</span>
                            <span class="font-black text-on-surface font-mono mt-0.5 block">
                                $ {{ number_format($clienteSeleccionado->total_gastado) }} COP
                            </span>
                        </div>
                    </div>

                    <!-- Puntos de Fidelización y Botón de Ajuste -->
                    <div class="rounded-2xl bg-surface-container-lowest p-4 border border-outline-variant/30 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-tertiary block">Saldo de Fidelización</span>
                            <div class="flex items-baseline gap-1.5 mt-0.5">
                                <span class="text-2xl font-black text-on-surface font-mono">{{ number_format($clienteSeleccionado->puntos_fidelidad) }}</span>
                                <span class="text-xs font-bold text-tertiary">pts</span>
                            </div>
                            <span class="text-[11px] text-on-surface-variant">Equivale a $ {{ number_format($clienteSeleccionado->puntos_fidelidad * 10) }} COP en descuento</span>
                        </div>
                        <button
                            wire:click="abrirModalPuntos"
                            class="px-3 py-2 rounded-xl bg-tertiary-fixed text-on-tertiary-fixed text-xs font-extrabold hover:bg-tertiary/20 active:scale-95 transition-all flex items-center gap-1 border border-tertiary/20"
                        >
                            <span class="material-symbols-outlined text-[16px]">tune</span>
                            Ajustar
                        </button>
                    </div>

                    <!-- Alergias y Preferencias Culinarias -->
                    @if($clienteSeleccionado->alergias || $clienteSeleccionado->preferencias)
                        <div class="space-y-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant block">Perfil Gastronómico</span>
                            @if($clienteSeleccionado->alergias)
                                <div class="p-3 rounded-xl bg-error/10 border border-error/25 text-xs text-error flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[16px] mt-0.5">block</span>
                                    <div>
                                        <span class="font-extrabold block">Alergias Críticas:</span>
                                        <span>{{ $clienteSeleccionado->alergias }}</span>
                                    </div>
                                </div>
                            @endif
                            @if($clienteSeleccionado->preferencias)
                                <div class="p-3 rounded-xl bg-surface-container-low border border-outline-variant/20 text-xs text-on-surface flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[16px] text-secondary mt-0.5">favorite</span>
                                    <div>
                                        <span class="font-bold text-on-surface-variant block">Preferencias de mesa:</span>
                                        <span>{{ $clienteSeleccionado->preferencias }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Direcciones Guardadas (Delivery) -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">
                                Direcciones de Entrega ({{ $clienteSeleccionado->direcciones->count() }})
                            </span>
                            <button
                                wire:click="abrirModalDireccion"
                                class="text-xs font-bold text-primary hover:underline flex items-center gap-1"
                            >
                                <span class="material-symbols-outlined text-[14px]">add_location_alt</span>
                                + Agregar
                            </button>
                        </div>

                        <div class="space-y-2">
                            @forelse($clienteSeleccionado->direcciones as $dir)
                                <div class="p-3 rounded-2xl bg-surface-container-low border border-outline-variant/20 text-xs space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-extrabold text-on-surface flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px] text-primary">home_pin</span>
                                            {{ $dir->etiqueta }}
                                        </span>
                                        @if($dir->es_predeterminada)
                                            <span class="px-2 py-0.5 rounded-full bg-secondary/15 text-secondary text-[10px] font-extrabold border border-secondary/30">
                                                Principal
                                            </span>
                                        @endif
                                    </div>
                                    <p class="font-medium text-on-surface">{{ $dir->direccion }}</p>
                                    @if($dir->referencia_apto)
                                        <p class="text-on-surface-variant text-[11px]">{{ $dir->referencia_apto }} · {{ $dir->barrio_ciudad }}</p>
                                    @endif
                                    @if($dir->notas_entrega)
                                        <p class="text-[10px] text-tertiary italic">Nota: {{ $dir->notas_entrega }}</p>
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 rounded-2xl bg-surface-container-low text-center text-xs text-on-surface-variant">
                                    Sin direcciones registradas para entrega a domicilio.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Últimos Pedidos -->
                    <div class="space-y-2 pt-2 border-t border-outline-variant/15">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant block">Historial Reciente</span>
                        <div class="space-y-1.5">
                            @forelse($ultimosPedidos as $p)
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-surface-container-low text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 rounded bg-surface-container-high text-[10px] font-mono font-bold text-on-surface">
                                            #{{ $p->codigo }}
                                        </span>
                                        <span class="capitalize text-on-surface font-semibold">{{ $p->tipo }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-black text-on-surface font-mono">$ {{ number_format($p->total) }}</span>
                                        <span class="text-[10px] text-on-surface-variant block">{{ $p->created_at->format('d/m H:i') }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-on-surface-variant">Sin pedidos recientes.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-3xl bg-surface-container-lowest p-12 text-center border border-outline-variant/20">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">badge</span>
                    <p class="text-xs font-bold text-on-surface">Selecciona un comensal</p>
                    <p class="text-[11px] text-on-surface-variant mt-0.5">Elige cualquier cliente del directorio para ver su ficha 360°.</p>
                </div>
            @endif
        </aside>
    </div>

    <!-- Modal: Nuevo Comensal -->
    @if($mostrarModalNuevo)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[24px] text-primary">person_add</span>
                        <h3 class="text-base font-extrabold text-on-surface">Registrar Nuevo Comensal</h3>
                    </div>
                    <button wire:click="$set('mostrarModalNuevo', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre completo *:</label>
                        <input type="text" wire:model="nuevo.nombre" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: Roberto Santander" />
                        @error('nuevo.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Teléfono / WhatsApp *:</label>
                            <input type="text" wire:model="nuevo.telefono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="+57 300 000 0000" />
                            @error('nuevo.telefono') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Documento (CC / DNI):</label>
                            <input type="text" wire:model="nuevo.documento" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="CC 71.294.019" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Email:</label>
                            <input type="email" wire:model="nuevo.email" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="cliente@correo.com" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Nivel / Tier VIP:</label>
                            <select wire:model="nuevo.tier" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                <option value="ocasional">Ocasional (1 visita / sin registrar)</option>
                                <option value="frecuente">Frecuente (Visitas recurrentes)</option>
                                <option value="vip">VIP Club (Fidelizado / Créditos)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-error">Alergias Alimentarias:</label>
                            <input type="text" wire:model="nuevo.alergias" class="mt-1 w-full rounded-xl border border-error/30 bg-error/5 p-2 text-xs text-on-surface focus:border-error focus:ring-0" placeholder="Ej: Sin nuez moscada, sin sésamo" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Preferencias:</label>
                            <input type="text" wire:model="nuevo.preferencias" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: Wagyu 3/4, vino blanco" />
                        </div>
                    </div>

                    <!-- Dirección Inicial (Opcional) -->
                    <div class="pt-2 border-t border-outline-variant/15 space-y-2">
                        <span class="text-xs font-bold text-primary block">Dirección de Entrega Inicial (Opcional):</span>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <input type="text" wire:model="nuevo.direccion" class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Dirección: Cra 43A # 1Sur-150" />
                            </div>
                            <div>
                                <input type="text" wire:model="nuevo.referencia_apto" class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Apto / Torre" />
                            </div>
                        </div>
                    </div>

                    <!-- Habeas Data / Tratamiento de Datos (Ley 1581) -->
                    <div class="pt-2 border-t border-outline-variant/15 space-y-2">
                        <span class="text-xs font-bold text-on-surface flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-primary">verified_user</span>
                            Consentimiento Habeas Data (Ley 1581)
                        </span>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="nuevo.acepta_tratamiento_datos" class="mt-0.5 rounded border-outline-variant text-primary focus:ring-primary h-4 w-4" />
                            <span class="text-[11px] text-on-surface font-semibold">Autoriza tratamiento de datos personales</span>
                        </label>
                        <div class="pl-6 space-y-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="nuevo.autoriza_whatsapp" class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4" />
                                <span class="text-[10px] text-on-surface-variant">Promociones y pedidos vía WhatsApp</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="nuevo.autoriza_email" class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4" />
                                <span class="text-[10px] text-on-surface-variant">Facturación electrónica y promociones vía Email</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalNuevo', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface">
                        Cancelar
                    </button>
                    <button wire:click="guardarNuevo" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container">
                        ✓ Guardar Comensal
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Editar Comensal -->
    @if($mostrarModalEditar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[24px] text-primary">edit</span>
                        <h3 class="text-base font-extrabold text-on-surface">Editar Ficha Comensal</h3>
                    </div>
                    <button wire:click="$set('mostrarModalEditar', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre completo:</label>
                        <input type="text" wire:model="edicion.nombre" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Teléfono:</label>
                            <input type="text" wire:model="edicion.telefono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Nivel VIP:</label>
                            <select wire:model="edicion.tier" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2 py-2 text-xs font-bold text-on-surface">
                                <option value="ocasional">Ocasional</option>
                                <option value="frecuente">Frecuente</option>
                                <option value="vip">VIP Club</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-error">Alergias:</label>
                        <input type="text" wire:model="edicion.alergias" class="mt-1 w-full rounded-xl border border-error/30 bg-error/5 p-2 text-xs text-on-surface" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Preferencias:</label>
                        <input type="text" wire:model="edicion.preferencias" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" />
                    </div>

                    <!-- Habeas Data / Tratamiento de Datos (Ley 1581) -->
                    <div class="pt-2 border-t border-outline-variant/15 space-y-2">
                        <span class="text-xs font-bold text-on-surface flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-primary">verified_user</span>
                            Consentimiento Habeas Data (Ley 1581)
                        </span>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="edicion.acepta_tratamiento_datos" class="mt-0.5 rounded border-outline-variant text-primary focus:ring-primary h-4 w-4" />
                            <span class="text-[11px] text-on-surface font-semibold">Autoriza tratamiento de datos personales</span>
                        </label>
                        <div class="pl-6 space-y-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="edicion.autoriza_whatsapp" class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4" />
                                <span class="text-[10px] text-on-surface-variant">Promociones y pedidos vía WhatsApp</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="edicion.autoriza_email" class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4" />
                                <span class="text-[10px] text-on-surface-variant">Facturación electrónica y promociones vía Email</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalEditar', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant">
                        Cancelar
                    </button>
                    <button wire:click="guardarEdicion" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md">
                        ✓ Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Ajuste Manual de Puntos -->
    @if($mostrarModalPuntos)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[24px] text-tertiary">tune</span>
                        <h3 class="text-base font-extrabold text-on-surface">Ajustar Puntos</h3>
                    </div>
                    <button wire:click="$set('mostrarModalPuntos', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            wire:click="$set('tipoAjuste', 'suma')"
                            class="py-2.5 rounded-xl text-xs font-bold transition-all {{ $tipoAjuste === 'suma' ? 'bg-secondary text-on-secondary shadow' : 'bg-surface-container-high text-on-surface-variant' }}"
                        >
                            + Acreditar
                        </button>
                        <button
                            type="button"
                            wire:click="$set('tipoAjuste', 'resta')"
                            class="py-2.5 rounded-xl text-xs font-bold transition-all {{ $tipoAjuste === 'resta' ? 'bg-error text-on-error shadow' : 'bg-surface-container-high text-on-surface-variant' }}"
                        >
                            - Debitar
                        </button>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Cantidad de Puntos:</label>
                        <input type="number" wire:model="puntosAjuste" min="1" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-base font-bold text-on-surface font-mono" />
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Motivo / Justificación:</label>
                        <input type="text" wire:model="motivoAjuste" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" placeholder="Ej: Cortesía aniversario comensal" />
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalPuntos', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant">
                        Cancelar
                    </button>
                    <button wire:click="ejecutarAjustePuntos" class="rounded-2xl bg-tertiary py-3 text-xs font-black text-on-tertiary shadow-md">
                        ✓ Confirmar Ajuste
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Nueva Dirección de Entrega -->
    @if($mostrarModalDireccion)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[24px] text-primary">add_location_alt</span>
                        <h3 class="text-base font-extrabold text-on-surface">Agregar Dirección Delivery</h3>
                    </div>
                    <button wire:click="$set('mostrarModalDireccion', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Etiqueta:</label>
                        <input type="text" wire:model="nuevaDireccion.etiqueta" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" placeholder="Casa, Oficina, Apartamento..." />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Dirección completa *:</label>
                        <input type="text" wire:model="nuevaDireccion.direccion" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" placeholder="Ej: Cra 43A # 1Sur-150" />
                        @error('nuevaDireccion.direccion') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Apto / Torre / Ref:</label>
                            <input type="text" wire:model="nuevaDireccion.referencia_apto" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" placeholder="Torre 2, Apto 501" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Barrio / Ciudad:</label>
                            <input type="text" wire:model="nuevaDireccion.barrio_ciudad" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" placeholder="El Poblado, Medellín" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Notas para el motorizado:</label>
                        <input type="text" wire:model="nuevaDireccion.notas_entrega" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface" placeholder="Dejar en portería, avisar por citófono" />
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalDireccion', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant">
                        Cancelar
                    </button>
                    <button wire:click="guardarDireccion" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md">
                        ✓ Guardar Dirección
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
