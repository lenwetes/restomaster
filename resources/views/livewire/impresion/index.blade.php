<?php

use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Services\ImpresionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $filtroTipo = 'todos'; // 'todos', 'comanda_cocina', 'ticket_venta', 'reporte_z', 'error'
    public ?int $trabajoSeleccionadoId = null;

    // Modales
    public bool $modalImpresoraOpen = false;
    public ?int $impresoraEditandoId = null;
    public array $impresorasDetectadasSO = [];
    public array $formImpresora = [
        'nombre' => '',
        'tipo_conexion' => 'usb_local',
        'driver_nombre' => '',
        'ip_address' => '',
        'puerto' => 9100,
        'area' => 'cocina_sushi',
        'ancho_columnas' => 48,
        'copias' => 1,
        'activa' => true,
        'descripcion' => '',
    ];

    public ?string $mensajeFlash = null;
    public ?string $tipoFlash = 'success';

    public function mount(): void
    {
        $primerTrabajo = TrabajoImpresion::latest()->first();
        if ($primerTrabajo) {
            $this->trabajoSeleccionadoId = $primerTrabajo->id;
        }
    }

    public function seleccionarTrabajo(int $id): void
    {
        $this->trabajoSeleccionadoId = $id;
    }

    public function probarImpresora(int $impresoraId): void
    {
        $impresora = Impresora::findOrFail($impresoraId);
        $servicio = app(ImpresionService::class);
        $trabajo = $servicio->probarImpresora($impresora, Auth::user());

        $this->trabajoSeleccionadoId = $trabajo->id;
        $this->mensajeFlash = "Test de impresión enviado a {$impresora->nombre}.";
        $this->tipoFlash = 'success';
    }

    public function testConexionSocket(int $impresoraId): void
    {
        $impresora = Impresora::findOrFail($impresoraId);
        $resultado = $impresora->probarConexion(1.5);

        $this->mensajeFlash = "{$impresora->nombre}: {$resultado['mensaje']}";
        $this->tipoFlash = $resultado['ok'] ? 'success' : 'error';
    }

    public function detectarImpresorasSO(): void
    {
        $servicio = app(ImpresionService::class);
        $this->impresorasDetectadasSO = $servicio->obtenerImpresorasInstaladasSO();

        if (!empty($this->impresorasDetectadasSO)) {
            $this->mensajeFlash = "Se detectaron " . count($this->impresorasDetectadasSO) . " impresoras instaladas en este equipo.";
            $this->tipoFlash = 'success';
            if (empty($this->formImpresora['driver_nombre'])) {
                $this->formImpresora['driver_nombre'] = $this->impresorasDetectadasSO[0];
            }
        } else {
            $this->mensajeFlash = "No se detectaron impresoras activas en el sistema operativo.";
            $this->tipoFlash = 'warning';
        }
    }

    public function seleccionarDriverDetectado(string $nombreDriver): void
    {
        $this->formImpresora['driver_nombre'] = $nombreDriver;
        if (empty($this->formImpresora['nombre'])) {
            $this->formImpresora['nombre'] = $nombreDriver;
        }
    }

    public function reimprimirTrabajo(int $trabajoId): void
    {
        $trabajo = TrabajoImpresion::findOrFail($trabajoId);
        $servicio = app(ImpresionService::class);
        $servicio->reimprimir($trabajo, Auth::user(), 'Reimpresión manual desde Spooler IMP-01');

        $this->mensajeFlash = "Trabajo #{$trabajo->id} re-encolado para impresión inmediata.";
        $this->tipoFlash = 'success';
    }

    public function abrirModalNuevaImpresora(): void
    {
        $this->impresoraEditandoId = null;
        $this->formImpresora = [
            'nombre' => '',
            'tipo_conexion' => 'usb_local',
            'driver_nombre' => '',
            'ip_address' => '192.168.1.',
            'puerto' => 9100,
            'area' => 'caja_principal',
            'ancho_columnas' => 48,
            'copias' => 1,
            'activa' => true,
            'descripcion' => '',
        ];
        $this->impresorasDetectadasSO = [];
        $this->modalImpresoraOpen = true;
    }

    public function abrirModalEditarImpresora(int $id): void
    {
        $impresora = Impresora::findOrFail($id);
        $this->impresoraEditandoId = $impresora->id;
        $this->formImpresora = [
            'nombre' => $impresora->nombre,
            'tipo_conexion' => $impresora->tipo_conexion,
            'driver_nombre' => $impresora->driver_nombre ?: '',
            'ip_address' => $impresora->ip_address ?: '',
            'puerto' => $impresora->puerto ?: 9100,
            'area' => $impresora->area,
            'ancho_columnas' => $impresora->ancho_columnas,
            'copias' => $impresora->copias,
            'activa' => $impresora->activa,
            'descripcion' => $impresora->descripcion ?: '',
        ];
        $this->impresorasDetectadasSO = [];
        $this->modalImpresoraOpen = true;
    }

    public function guardarImpresora(): void
    {
        $this->validate([
            'formImpresora.nombre' => 'required|string|max:100',
            'formImpresora.tipo_conexion' => 'required|in:red_ip,usb_local,driver_sistema,driver_navegador,usb_compartida,virtual_simulador',
            'formImpresora.driver_nombre' => 'nullable|string|max:150',
            'formImpresora.puerto' => 'nullable|integer|min:1|max:65535',
            'formImpresora.area' => 'required|string',
            'formImpresora.ancho_columnas' => 'required|integer|in:32,40,42,48',
        ]);

        if ($this->impresoraEditandoId) {
            $impresora = Impresora::findOrFail($this->impresoraEditandoId);
            $impresora->update($this->formImpresora);
            $this->mensajeFlash = "Impresora {$impresora->nombre} actualizada correctamente.";
        } else {
            $impresora = Impresora::create($this->formImpresora);
            $this->mensajeFlash = "Impresora {$impresora->nombre} guardada y vinculada.";
        }

        $this->modalImpresoraOpen = false;
        $this->tipoFlash = 'success';
    }

    public function with(): array
    {
        $query = TrabajoImpresion::with(['impresora', 'pedido', 'turnoCaja', 'usuario'])->latest();

        if ($this->filtroTipo === 'error') {
            $query->where('estado', 'error');
        } elseif ($this->filtroTipo !== 'todos') {
            $query->where('tipo', $this->filtroTipo);
        }

        $trabajos = $query->take(35)->get();

        $trabajoSeleccionado = null;
        if ($this->trabajoSeleccionadoId) {
            $trabajoSeleccionado = TrabajoImpresion::with(['impresora', 'pedido', 'turnoCaja', 'usuario', 'reimpresoPor'])->find($this->trabajoSeleccionadoId);
        }

        $impresoras = Impresora::withCount(['trabajos', 'trabajosPendientes'])->get();

        $kpis = [
            'trabajos_hoy' => TrabajoImpresion::hoy()->count(),
            'en_cola' => TrabajoImpresion::pendientes()->count(),
            'fallidos' => TrabajoImpresion::fallidos()->count(),
            'impresoras_activas' => Impresora::activas()->count(),
        ];

        return [
            'trabajos' => $trabajos,
            'trabajoSeleccionado' => $trabajoSeleccionado,
            'impresoras' => $impresoras,
            'kpis' => $kpis,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Feedback Flash Banner -->
    @if ($mensajeFlash)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="flex items-center justify-between p-4 rounded-2xl shadow-sm border animate-fade-in
                    {{ $tipoFlash === 'success' ? 'bg-secondary-container/40 border-secondary/30 text-on-secondary-container' : 'bg-error-container/40 border-error/30 text-error' }}">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-lg">{{ $tipoFlash === 'success' ? 'check_circle' : 'error' }}</span>
                <span class="font-bold text-xs sm:text-sm">{{ $mensajeFlash }}</span>
            </div>
            <button @click="show = false" class="opacity-70 hover:opacity-100">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
    @endif

    <!-- Sub-header Operativo (Aura Gastro Expressive OS) -->
    <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full bg-primary-fixed text-on-primary-fixed font-bold text-[11px] uppercase tracking-wider border border-primary-fixed-dim">
                    Spooler & Red ESC/POS
                </span>
                <span class="text-on-surface-variant text-xs">•</span>
                <span class="text-xs font-semibold text-secondary flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
                    IMP-01 · Cola Asíncrona Activa
                </span>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-on-surface mt-1">
                Servidor de Impresión & Spooler de Red
            </h1>
            <p class="text-xs text-on-surface-variant max-w-2xl mt-0.5">
                Despacho asíncrono de comandas de cocina (80mm), tickets fiscales DIAN, reimpresión histórica auditada y monitoreo de sockets TCP.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                wire:click="abrirModalNuevaImpresora"
                class="inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-xs font-black text-on-primary shadow-md shadow-primary/20 hover:bg-primary-container active:scale-95 transition-all"
            >
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>+ Configurar Impresora</span>
            </button>
        </div>
    </header>

    <!-- Bento KPIs de Spooler (4 Métricas Flash) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Trabajos Hoy -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Impresiones Hoy</span>
                <div class="w-8 h-8 rounded-xl bg-surface-container-high flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl font-black tracking-tight text-on-surface">{{ $kpis['trabajos_hoy'] }}</span>
                <span class="text-[11px] font-bold text-primary">tickets</span>
            </div>
            <span class="text-[11px] text-on-surface-variant mt-1">Comandas + Facturas</span>
        </div>

        <!-- KPI 2: En Cola Activa -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">En Cola / Queue</span>
                <div class="w-8 h-8 rounded-xl bg-tertiary-container/30 flex items-center justify-center text-tertiary">
                    <span class="material-symbols-outlined text-[18px]">hourglass_top</span>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl font-black tracking-tight text-on-surface">{{ $kpis['en_cola'] }}</span>
                <span class="text-[11px] font-bold text-tertiary">en proceso</span>
            </div>
            <span class="text-[11px] text-on-surface-variant mt-1">Worker asíncrono activo</span>
        </div>

        <!-- KPI 3: Errores Spooler -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border {{ $kpis['fallidos'] > 0 ? 'border-error/40 bg-error-container/10' : 'border-outline-variant/20' }} shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Errores de Red</span>
                <div class="w-8 h-8 rounded-xl bg-error-container/40 flex items-center justify-center text-error">
                    <span class="material-symbols-outlined text-[18px]">error</span>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl font-black tracking-tight {{ $kpis['fallidos'] > 0 ? 'text-error' : 'text-on-surface' }}">{{ $kpis['fallidos'] }}</span>
                <span class="text-[11px] font-bold text-error">fallidos</span>
            </div>
            <span class="text-[11px] text-on-surface-variant mt-1">Reintentos automáticos (3x)</span>
        </div>

        <!-- KPI 4: Impresoras en Línea -->
        <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Impresoras En Red</span>
                <div class="w-8 h-8 rounded-xl bg-secondary-container/40 flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl font-black tracking-tight text-on-surface">{{ $kpis['impresoras_activas'] }}</span>
                <span class="text-[11px] font-bold text-secondary">estaciones</span>
            </div>
            <span class="text-[11px] text-on-surface-variant mt-1">Cocina Fría, Calientes, Bar, Caja</span>
        </div>
    </div>

    <!-- Catálogo de Impresoras en Línea (Horizontales) -->
    <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-outline-variant/15">
            <h2 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">router</span>
                Estaciones de Impresión en Red Local
            </h2>
            <span class="text-xs text-on-surface-variant font-medium">Sockets TCP / Puerto 9100</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach ($impresoras as $imp)
                <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/20 flex flex-col justify-between gap-3 hover:border-primary/40 transition-colors">
                    <div>
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-surface-container flex items-center justify-center text-primary font-bold">
                                    <span class="material-symbols-outlined text-[18px]">print</span>
                                </div>
                                <div>
                                    <h3 class="font-extrabold text-xs text-on-surface leading-tight">{{ $imp->nombre }}</h3>
                                    <span class="text-[10px] text-on-surface-variant font-mono">
                                        @if(in_array($imp->tipo_conexion, ['usb_local', 'driver_sistema', 'usb_compartida'], true))
                                            USB: {{ $imp->driver_nombre ?: 'Controlador SO' }}
                                        @elseif($imp->tipo_conexion === 'driver_navegador')
                                            Navegador Web (@media print)
                                        @elseif($imp->ip_address)
                                            {{ $imp->ip_address }}:{{ $imp->puerto }}
                                        @else
                                            Simulador Virtual
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $imp->activa ? 'bg-secondary/15 text-secondary border border-secondary/30' : 'bg-surface-container text-on-surface-variant' }}">
                                {{ $imp->activa ? 'Online' : 'Pausa' }}
                            </span>
                        </div>

                        <div class="mt-3 flex items-center gap-1.5 text-[11px] text-on-surface-variant">
                            <span class="px-2 py-0.5 rounded-md bg-surface-container text-[10px] font-bold uppercase tracking-wider">
                                {{ str_replace('_', ' ', $imp->area) }}
                            </span>
                            <span>· {{ $imp->ancho_columnas }} col ({{ $imp->ancho_columnas == 32 ? '58mm' : '80mm' }})</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-outline-variant/15 flex items-center justify-between gap-2">
                        <button
                            wire:click="testConexionSocket({{ $imp->id }})"
                            class="px-2.5 py-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface text-[11px] font-bold flex items-center gap-1 transition-colors cursor-pointer"
                            title="Comprobar enlace o controlador"
                        >
                            <span class="material-symbols-outlined text-[14px]">
                                {{ in_array($imp->tipo_conexion, ['usb_local', 'driver_sistema'], true) ? 'usb' : 'network_ping' }}
                            </span>
                            <span>{{ in_array($imp->tipo_conexion, ['usb_local', 'driver_sistema'], true) ? 'Check SO' : 'Ping' }}</span>
                        </button>
                        <div class="flex items-center gap-1.5">
                            <button
                                wire:click="probarImpresora({{ $imp->id }})"
                                class="px-2.5 py-1.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-on-primary text-[11px] font-bold flex items-center gap-1 transition-colors cursor-pointer"
                                title="Imprimir ticket de prueba"
                            >
                                <span class="material-symbols-outlined text-[14px]">receipt</span>
                                <span>Test</span>
                            </button>
                            <button
                                wire:click="abrirModalEditarImpresora({{ $imp->id }})"
                                class="p-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer"
                                title="Editar configuración"
                            >
                                <span class="material-symbols-outlined text-[16px]">settings</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Workspace Split: Spooler (7 Cols) & Visor de Cinta Térmica 80mm (5 Cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Spooler de Trabajos Recientes (7 Cols) -->
        <section class="lg:col-span-7 space-y-4">
            <!-- Barra de Filtros por Tipo -->
            <div class="p-3 rounded-2xl bg-surface-container-lowest border border-outline-variant/20 shadow-sm flex items-center gap-2 overflow-x-auto scrollbar-none">
                <button
                    wire:click="$set('filtroTipo', 'todos')"
                    class="h-8 px-3 rounded-full text-xs font-bold transition-all cursor-pointer {{ $filtroTipo === 'todos' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container' }}"
                >
                    Todos
                </button>
                <button
                    wire:click="$set('filtroTipo', 'comanda_cocina')"
                    class="h-8 px-3 rounded-full text-xs font-bold transition-all cursor-pointer {{ $filtroTipo === 'comanda_cocina' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container' }}"
                >
                    Comandas
                </button>
                <button
                    wire:click="$set('filtroTipo', 'ticket_venta')"
                    class="h-8 px-3 rounded-full text-xs font-bold transition-all cursor-pointer {{ $filtroTipo === 'ticket_venta' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container' }}"
                >
                    Facturas POS
                </button>
                <button
                    wire:click="$set('filtroTipo', 'reporte_z')"
                    class="h-8 px-3 rounded-full text-xs font-bold transition-all cursor-pointer {{ $filtroTipo === 'reporte_z' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container' }}"
                >
                    Reportes Z
                </button>
                <button
                    wire:click="$set('filtroTipo', 'error')"
                    class="h-8 px-3 rounded-full text-xs font-bold transition-all cursor-pointer {{ $filtroTipo === 'error' ? 'bg-error text-on-error shadow-sm' : 'bg-surface-container-low text-error hover:bg-surface-container' }}"
                >
                    Fallidos
                </button>
            </div>

            <!-- Tabla Interactiva de Trabajos -->
            <div class="rounded-3xl bg-surface-container-lowest border border-outline-variant/20 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-on-surface">
                        <thead class="bg-surface-container-low text-[11px] font-bold text-on-surface-variant uppercase tracking-wider border-b border-outline-variant/20">
                            <tr>
                                <th class="p-3">ID / Tipo</th>
                                <th class="p-3">Destino / Área</th>
                                <th class="p-3">Hora</th>
                                <th class="p-3">Estado</th>
                                <th class="p-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                            @forelse($trabajos as $tr)
                                <tr
                                    wire:click="seleccionarTrabajo({{ $tr->id }})"
                                    class="hover:bg-surface-container/50 transition-colors cursor-pointer {{ $trabajoSeleccionadoId === $tr->id ? 'bg-primary-container/20 border-l-4 border-l-primary' : '' }}"
                                >
                                    <td class="p-3">
                                        <div class="font-bold flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px] text-primary">
                                                {{ $tr->tipo === 'comanda_cocina' ? 'skillet' : ($tr->tipo === 'ticket_venta' ? 'receipt' : 'finance') }}
                                            </span>
                                            <span>#{{ $tr->id }} · {{ str_replace('_', ' ', ucfirst($tr->tipo)) }}</span>
                                        </div>
                                        @if($tr->pedido)
                                            <span class="text-[10px] text-on-surface-variant font-mono">Orden: {{ $tr->pedido->codigo }}</span>
                                        @elseif($tr->turnoCaja)
                                            <span class="text-[10px] text-on-surface-variant font-mono">Turno Caja: #{{ $tr->turnoCaja->id }}</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <span class="font-bold block">{{ $tr->impresora->nombre }}</span>
                                        <span class="text-[10px] text-on-surface-variant font-mono uppercase">{{ $tr->area }}</span>
                                    </td>
                                    <td class="p-3 text-[11px] text-on-surface-variant">
                                        {{ $tr->created_at->format('H:i:s') }}
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold
                                            {{ $tr->estado === 'enviado' ? 'bg-secondary/15 text-secondary border border-secondary/30' : ($tr->estado === 'reimpreso' ? 'bg-tertiary-container/30 text-tertiary' : ($tr->estado === 'error' ? 'bg-error-container/30 text-error' : 'bg-surface-container text-on-surface-variant')) }}">
                                            {{ ucfirst($tr->estado) }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <button
                                            wire:click.stop="reimprimirTrabajo({{ $tr->id }})"
                                            class="p-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-colors cursor-pointer"
                                            title="Reimprimir ticket (auditado)"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">print</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-on-surface-variant text-xs">
                                        No se encontraron trabajos de impresión en la cola.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Inspector Lateral: Visor de Cinta Continua Térmica 80mm (5 Cols) -->
        <aside class="lg:col-span-5 space-y-4">
            @if ($trabajoSeleccionado)
                <div class="rounded-3xl bg-surface-container-lowest p-5 border border-outline-variant/20 shadow-sm space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-outline-variant/15">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary">Inspector Térmico</span>
                            <h3 class="text-sm font-black text-on-surface">Ticket #{{ $trabajoSeleccionado->id }} ({{ strtoupper($trabajoSeleccionado->tipo) }})</h3>
                        </div>
                        <div class="flex items-center gap-1.5 no-print">
                            <button
                                onclick="window.print()"
                                type="button"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-bold border border-surface-container-highest shadow-xs transition cursor-pointer"
                                title="Imprimir directamente desde el navegador"
                            >
                                <span class="material-symbols-outlined text-[15px]">print</span>
                                <span>Imprimir Web</span>
                            </button>
                            <button
                                wire:click="reimprimirTrabajo({{ $trabajoSeleccionado->id }})"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-bold shadow-sm hover:bg-primary/90 transition active:scale-95 cursor-pointer"
                            >
                                <span class="material-symbols-outlined text-[15px]">replay</span>
                                <span>Reimprimir</span>
                            </button>
                        </div>
                    </div>

                    <!-- Detalles del Metadato -->
                    <div class="grid grid-cols-2 gap-2 text-xs bg-surface-container-low p-3 rounded-2xl no-print">
                        <div>
                            <span class="text-[10px] text-on-surface-variant block">Impresora Asignada</span>
                            <span class="font-bold text-on-surface">{{ $trabajoSeleccionado->impresora->nombre }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-on-surface-variant block">Estado Actual</span>
                            <span class="font-bold text-on-surface">{{ ucfirst($trabajoSeleccionado->estado) }} (Intentos: {{ $trabajoSeleccionado->intentos }})</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-on-surface-variant block">Generado Por</span>
                            <span class="font-bold text-on-surface">{{ $trabajoSeleccionado->usuario->name ?? 'Sistema' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-on-surface-variant block">Fecha y Hora</span>
                            <span class="font-bold text-on-surface">{{ $trabajoSeleccionado->created_at->format('d/m/Y H:i:s') }}</span>
                        </div>
                    </div>

                    <!-- Representación Visual de Cinta Continua de Papel Térmico -->
                    <div class="print-ticket-termico relative rounded-2xl bg-white text-slate-900 shadow-md border border-slate-200 p-4 font-mono text-[11px] leading-tight overflow-hidden">
                        <!-- Efecto Corte Zig-zag Superior -->
                        <div class="no-print h-2 w-full bg-[linear-gradient(45deg,transparent_75%,#f1f5f9_75%),linear-gradient(-45deg,transparent_75%,#f1f5f9_75%)] bg-[size:10px_10px] mb-3"></div>

                        <pre class="whitespace-pre overflow-x-auto text-[10.5px] leading-relaxed select-all font-mono">{{ $trabajoSeleccionado->contenido_texto }}</pre>

                        <!-- Efecto Corte Zig-zag Inferior -->
                        <div class="no-print h-2 w-full bg-[linear-gradient(135deg,transparent_75%,#f1f5f9_75%),linear-gradient(-135deg,transparent_75%,#f1f5f9_75%)] bg-[size:10px_10px] mt-3"></div>
                    </div>
                </div>
            @else
                <div class="rounded-3xl bg-surface-container-lowest p-12 text-center border border-outline-variant/20">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">receipt</span>
                    <p class="text-xs font-bold text-on-surface">Selecciona un ticket de la lista</p>
                    <p class="text-[11px] text-on-surface-variant mt-0.5">Podrás ver la cinta térmica formateada a 48 columnas y enviarla a reimpresión.</p>
                </div>
            @endif
        </aside>
    </div>

    <!-- MODAL: CONFIGURAR / EDITAR IMPRESORA -->
    @if ($modalImpresoraOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/40 backdrop-blur-sm animate-fade-in">
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-[18px]">print</span>
                        </div>
                        <h3 class="text-base font-bold text-on-surface">
                            {{ $impresoraEditandoId ? 'Editar Impresora' : 'Nueva Impresora de Red' }}
                        </h3>
                    </div>
                    <button wire:click="$set('modalImpresoraOpen', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-[11px] text-on-surface-variant font-bold uppercase mb-1">Nombre Descriptivo *</label>
                        <input type="text" wire:model="formImpresora.nombre" placeholder="Ej: Térmica Cocina / Bar"
                               class="w-full h-10 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-xs focus:border-primary outline-none" />
                        @error('formImpresora.nombre') <span class="text-error text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-on-surface-variant font-bold uppercase mb-1">Tipo Conexión</label>
                            <select wire:model.live="formImpresora.tipo_conexion"
                                    class="w-full h-10 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-xs focus:border-primary outline-none">
                                <option value="usb_local">USB / Controlador Windows (Spooler)</option>
                                <option value="red_ip">Red Ethernet / Wi-Fi (TCP)</option>
                                <option value="driver_navegador">Navegador Web (@media print)</option>
                                <option value="virtual_simulador">Simulador Virtual</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-on-surface-variant font-bold uppercase mb-1">Área / Estación</label>
                            <select wire:model="formImpresora.area"
                                    class="w-full h-10 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-xs focus:border-primary outline-none">
                                <option value="caja_principal">Caja Principal</option>
                                <option value="cocina_sushi">Cocina Fría / Platos Fríos</option>
                                <option value="cocina_calientes">Cocina Calientes</option>
                                <option value="barra">Barra de Bebidas</option>
                                <option value="todas">Todas las Áreas</option>
                            </select>
                        </div>
                    </div>

                    @if(in_array($formImpresora['tipo_conexion'], ['usb_local', 'driver_sistema', 'usb_compartida'], true))
                        <div class="space-y-1.5 bg-surface-container-low/50 p-3 rounded-2xl border border-surface-container-high">
                            <div class="flex items-center justify-between">
                                <label class="block text-[11px] text-on-surface-variant font-bold uppercase">Impresora en Windows / SO *</label>
                                <button
                                    type="button"
                                    wire:click="detectarImpresorasSO"
                                    class="text-[10px] font-bold text-primary hover:underline flex items-center gap-1 cursor-pointer"
                                >
                                    <span class="material-symbols-outlined text-[14px]">search</span>
                                    <span>Detectar Impresoras del Equipo</span>
                                </button>
                            </div>
                            <input type="text" wire:model="formImpresora.driver_nombre" placeholder="Ej: POS-80, EPSON TM-T20III, Generic / Text Only"
                                   class="w-full h-10 px-3 rounded-xl bg-surface-container border border-surface-container-high text-on-surface text-xs focus:border-primary outline-none" />
                            @error('formImpresora.driver_nombre') <span class="text-error text-[10px]">{{ $message }}</span> @enderror

                            @if(!empty($impresorasDetectadasSO))
                                <div class="pt-1">
                                    <span class="text-[10px] text-on-surface-variant font-bold block mb-1">Detectadas en este equipo (click para seleccionar):</span>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($impresorasDetectadasSO as $drv)
                                            <button
                                                type="button"
                                                wire:click="seleccionarDriverDetectado('{{ $drv }}')"
                                                class="px-2 py-1 rounded-lg text-[10px] font-mono font-bold transition-all cursor-pointer {{ $formImpresora['driver_nombre'] === $drv ? 'bg-primary text-on-primary shadow-xs' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}"
                                            >
                                                🖨️ {{ $drv }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @elseif($formImpresora['tipo_conexion'] === 'red_ip')
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block text-[11px] text-on-surface-variant font-bold uppercase mb-1">Dirección IP</label>
                                <input type="text" wire:model="formImpresora.ip_address" placeholder="192.168.1.200"
                                       class="w-full h-10 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-xs font-mono focus:border-primary outline-none" />
                            </div>
                            <div>
                                <label class="block text-[11px] text-on-surface-variant font-bold uppercase mb-1">Puerto</label>
                                <input type="number" wire:model="formImpresora.puerto" placeholder="9100"
                                       class="w-full h-10 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-xs font-mono focus:border-primary outline-none" />
                            </div>
                        </div>
                    @elseif($formImpresora['tipo_conexion'] === 'driver_navegador')
                        <div class="p-3 rounded-2xl bg-secondary-container/20 border border-secondary/30 text-secondary text-xs flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">info</span>
                            <span>Se enviará al diálogo de impresión térmica del navegador (@media print 80mm). Ideal para cajas con Chrome/Edge kiosk.</span>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-on-surface-variant font-bold uppercase mb-1">Ancho Columnas</label>
                            <select wire:model="formImpresora.ancho_columnas"
                                    class="w-full h-10 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-xs focus:border-primary outline-none">
                                <option value="48">48 Columnas (80mm Estándar)</option>
                                <option value="42">42 Columnas (80mm Compacto)</option>
                                <option value="32">32 Columnas (58mm)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-on-surface-variant font-bold uppercase mb-1">Copias</label>
                            <input type="number" wire:model="formImpresora.copias" min="1" max="5"
                                   class="w-full h-10 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-xs focus:border-primary outline-none" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="checkActiva" wire:model="formImpresora.activa" class="rounded border-outline-variant text-primary focus:ring-primary" />
                        <label for="checkActiva" class="font-bold text-on-surface text-xs">Impresora activa en servicio</label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-surface-container-high">
                    <button wire:click="$set('modalImpresoraOpen', false)"
                            class="h-9 px-4 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-semibold">
                        Cancelar
                    </button>
                    <button wire:click="guardarImpresora"
                            class="h-9 px-4 rounded-xl bg-primary hover:bg-primary-container text-on-primary text-xs font-bold shadow-sm">
                        Guardar Impresora
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
