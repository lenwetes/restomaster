<?php

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Services\MesaService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $filtroZona = 'todas';
    public string $filtroEstado = 'todas';
    public string $filtroMesero = 'todos'; // 'todos', 'mis_mesas'

    // Asignación / Transferencia Mesas State
    public bool $modalTransferirOpen = false;
    public ?int $mesaTransferirId = null;
    public ?int $nuevoMeseroId = null;

    // CRUD Mesas State
    public bool $modalMesaOpen = false;
    public ?int $mesaEditandoId = null;
    public array $formMesa = [
        'numero' => '',
        'zona' => 'salon',
        'capacidad' => 4,
        'sucursal_id' => 1,
    ];
    // Modal QR de Mesa State
    public bool $modalQrOpen = false;
    public ?int $mesaQrId = null;
    public string $qrSvg = '';
    public string $qrUrl = '';
    public ?string $mensajeFlash = null;
    public ?string $tipoFlash = 'success';

    public function abrirModalNuevaMesa(): void
    {
        $maxNumero = Mesa::all()->map(fn($m) => (int) preg_replace('/\D/', '', $m->numero))->max();
        $siguienteNumero = $maxNumero ? $maxNumero + 1 : 1;

        $this->mesaEditandoId = null;
        $this->formMesa = [
            'numero' => (string) $siguienteNumero,
            'zona' => 'salon',
            'capacidad' => 4,
            'sucursal_id' => \App\Models\Sucursal::value('id') ?? 1,
        ];
        $this->modalMesaOpen = true;
    }

    public function abrirModalEditarMesa(int $id): void
    {
        $mesa = Mesa::findOrFail($id);
        $this->mesaEditandoId = $mesa->id;
        $this->formMesa = [
            'numero' => $mesa->numero,
            'zona' => $mesa->zona,
            'capacidad' => $mesa->capacidad,
            'sucursal_id' => $mesa->sucursal_id,
        ];
        $this->modalMesaOpen = true;
    }

    public function guardarMesa(): void
    {
        $this->authorize($this->mesaEditandoId ? 'update' : 'create', Mesa::class);

        $this->validate([
            'formMesa.numero' => 'required|string|max:10',
            'formMesa.zona' => 'required|string|in:salon,barra,terraza,vip',
            'formMesa.capacidad' => 'required|integer|min:1|max:30',
            'formMesa.sucursal_id' => 'required|exists:sucursales,id',
        ]);

        $mesaService = app(MesaService::class);

        try {
            if ($this->mesaEditandoId) {
                $mesa = Mesa::findOrFail($this->mesaEditandoId);
                $mesaService->actualizarMesa($mesa, $this->formMesa, auth()->user());
                $this->mensajeFlash = "Mesa #{$mesa->numero} actualizada exitosamente.";
            } else {
                $mesa = $mesaService->crearMesa($this->formMesa, auth()->user());
                $this->mensajeFlash = "Mesa #{$mesa->numero} creada y disponible en {$mesa->zona}.";
            }

            $this->modalMesaOpen = false;
            $this->tipoFlash = 'success';
        } catch (\Exception $e) {
            $this->mensajeFlash = $e->getMessage();
            $this->tipoFlash = 'error';
        }
    }

    public function eliminarMesa(int $id): void
    {
        $this->authorize('delete', Mesa::class);

        $mesa = Mesa::findOrFail($id);
        $mesaService = app(MesaService::class);

        try {
            $numero = $mesa->numero;
            $mesaService->eliminarMesa($mesa, auth()->user());
            $this->mensajeFlash = "Mesa #{$numero} eliminada del sistema.";
            $this->tipoFlash = 'success';
        } catch (\Exception $e) {
            $this->mensajeFlash = $e->getMessage();
            $this->tipoFlash = 'error';
        }
    }

    public function cambiarEstado(int $mesaId, string $nuevoEstado): void
    {
        $this->authorize('cambiarEstado', Mesa::class);

        $estadosValidos = array_map(fn ($case) => $case->value, \App\Enums\MesaEstado::cases());
        if (! in_array($nuevoEstado, $estadosValidos, true)) {
            abort(422, "Estado de mesa inválido: {$nuevoEstado}");
        }

        $mesa = Mesa::findOrFail($mesaId);

        try {
            app(\App\Services\MesaService::class)->cambiarEstado($mesa, $nuevoEstado);

            $this->dispatch('notificacion', [
                'mensaje' => "Mesa {$mesa->numero} actualizada a {$nuevoEstado}",
                'tipo' => 'success',
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->dispatch('notificacion', [
                'mensaje' => $e->getMessage(),
                'tipo' => 'error',
            ]);
        }
    }

    public function abrirModalQr(int $mesaId): void
    {
        $mesa = Mesa::findOrFail($mesaId);
        $this->mesaQrId = $mesa->id;
        $qrService = app(\App\Services\QrCodeService::class);
        $this->qrUrl = $qrService->urlParaMesa($mesa->numero);
        $this->qrSvg = $qrService->generarSvg($this->qrUrl, 260);
        $this->modalQrOpen = true;
    }

    public function atenderPedidoQr(int $pedidoId): void
    {
        try {
            $pedidoService = app(\App\Services\PedidoService::class);
            $pedido = $pedidoService->asignarMeseroAPedidoQr($pedidoId, auth()->user());
            $this->mensajeFlash = "¡Has tomado la comanda de la Mesa #{$pedido->mesa?->numero}! Pedido enviado a cocina.";
            $this->tipoFlash = 'success';
            $this->dispatch('notificacion', [
                'mensaje' => $this->mensajeFlash,
                'tipo' => 'success',
            ]);
        } catch (\DomainException $e) {
            $this->mensajeFlash = $e->getMessage();
            $this->tipoFlash = 'error';
            $this->dispatch('notificacion', [
                'mensaje' => $this->mensajeFlash,
                'tipo' => 'warning',
            ]);
        } catch (\Throwable $e) {
            $this->mensajeFlash = "Error al tomar pedido: " . $e->getMessage();
            $this->tipoFlash = 'error';
        }
    }

    public function autoasignarMesa(int $mesaId): void
    {
        $mesa = Mesa::findOrFail($mesaId);
        app(\App\Services\MesaService::class)->autoasignarMesa($mesa, auth()->user());

        $this->mensajeFlash = "¡Te has asignado la Mesa #{$mesa->numero}!";
        $this->tipoFlash = 'success';
        $this->dispatch('notificacion', [
            'mensaje' => $this->mensajeFlash,
            'tipo' => 'success',
        ]);
    }

    public function abrirModalTransferir(int $mesaId): void
    {
        abort_unless(in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true), 403, 'Solo administradores o gerentes pueden reasignar mesas a otros compañeros.');

        $mesa = Mesa::findOrFail($mesaId);
        $this->mesaTransferirId = $mesa->id;
        $this->nuevoMeseroId = $mesa->mesero_id;
        $this->modalTransferirOpen = true;
    }

    public function ejecutarTransferenciaMesa(): void
    {
        abort_unless(in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true), 403, 'Solo administradores o gerentes pueden reasignar mesas a otros compañeros.');

        $this->validate([
            'mesaTransferirId' => 'required|exists:mesas,id',
            'nuevoMeseroId' => 'required|exists:users,id',
        ], [
            'nuevoMeseroId.required' => 'Selecciona el mesero receptor de la mesa.',
        ]);

        $mesa = Mesa::findOrFail($this->mesaTransferirId);
        $nuevoMesero = \App\Models\User::findOrFail($this->nuevoMeseroId);

        app(\App\Services\MesaService::class)->transferirMesa($mesa, $nuevoMesero, auth()->user());

        $this->modalTransferirOpen = false;
        $this->mensajeFlash = "Mesa #{$mesa->numero} asignada / transferida a {$nuevoMesero->name}.";
        $this->tipoFlash = 'success';
        $this->dispatch('notificacion', [
            'mensaje' => $this->mensajeFlash,
            'tipo' => 'success',
        ]);
    }

    public function liberarParaRelevo(int $mesaId): void
    {
        $mesa = Mesa::findOrFail($mesaId);

        if (! in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true)) {
            abort_unless($mesa->mesero_id === auth()->id(), 403, 'Solo puedes liberar para relevo las mesas que atiendes actualmente.');
        }

        app(\App\Services\MesaService::class)->liberarParaRelevo($mesa, auth()->user());

        $this->mensajeFlash = "Mesa #{$mesa->numero} liberada para relevo. Tus compañeros ya pueden tomarla.";
        $this->tipoFlash = 'success';
        $this->dispatch('notificacion', [
            'mensaje' => $this->mensajeFlash,
            'tipo' => 'success',
        ]);
    }

    public function desasignarMesero(int $mesaId): void
    {
        abort_unless(in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true), 403, 'Solo administradores o gerentes pueden desasignar meseros.');

        $mesa = Mesa::findOrFail($mesaId);
        app(\App\Services\MesaService::class)->asignarMesero($mesa, null, auth()->user());

        $this->mensajeFlash = "Mesa #{$mesa->numero} liberada de mesero asignado.";
        $this->tipoFlash = 'success';
        $this->dispatch('notificacion', [
            'mensaje' => $this->mensajeFlash,
            'tipo' => 'success',
        ]);
    }

    public function with(): array
    {
        $query = Mesa::query()->with(['sucursal', 'mesero', 'pedidos' => function ($q) {
            $q->activos()->latest()->with(['items', 'usuario', 'mesero']);
        }]);

        if ($this->filtroZona !== 'todas') {
            $query->where('zona', $this->filtroZona);
        }

        if ($this->filtroEstado !== 'todas') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroMesero === 'mis_mesas' && auth()->check()) {
            $query->where('mesero_id', auth()->id());
        }

        $mesas = $query->orderBy('numero')->get();

        $meserosDisponibles = \App\Models\User::whereHas('role', fn ($q) => $q->where('slug', 'mesero'))
            ->where('activo', true)
            ->orderBy('name')
            ->get();

        // Global counters optimizados por agregación SQL
        $conteosPorEstado = Mesa::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $conteo = [
            'total' => (int) $conteosPorEstado->sum(),
            'libres' => (int) ($conteosPorEstado[MesaEstado::LIBRE->value] ?? 0),
            'ocupadas' => (int) ($conteosPorEstado[MesaEstado::OCUPADA->value] ?? 0),
            'por_limpiar' => (int) ($conteosPorEstado[MesaEstado::POR_LIMPIAR->value] ?? 0),
            'reservadas' => (int) ($conteosPorEstado[MesaEstado::RESERVADA->value] ?? 0),
        ];

        $mesaQr = $this->mesaQrId ? Mesa::find($this->mesaQrId) : null;

        return [
            'mesas' => $mesas,
            'conteo' => $conteo,
            'sucursales' => \App\Models\Sucursal::all(),
            'mesaQr' => $mesaQr,
            'meserosDisponibles' => $meserosDisponibles,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header Operativo (Aura Gastro Expressive OS) -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-surface-container-lowest p-5 rounded-3xl border border-surface-container-highest shadow-sm">
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
            @if(in_array(auth()->user()?->role?->slug, ['admin', 'gerente']))
                <button 
                    wire:click="abrirModalNuevaMesa"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-secondary px-3.5 py-2.5 text-xs font-extrabold text-on-secondary shadow-md hover:bg-secondary-fixed-dim transition-all active:scale-95 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    <span>+ Nueva Mesa</span>
                </button>
            @endif
            <a 
                href="{{ route('pos') }}" 
                wire:navigate
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container transition-all active:scale-95"
            >
                <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
                <span>Abrir POS Táctil</span>
            </a>
        </div>
    </header>
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
                Barra / Bar
            </button>
            <button 
                wire:click="$set('filtroZona', 'terraza')"
                class="rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all {{ $filtroZona === 'terraza' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
            >
                Terraza
            </button>

            <!-- Filtro Mesero Asignado -->
            <div class="flex items-center gap-1 pl-2 sm:border-l border-surface-container-high">
                <span class="text-xs font-bold text-on-surface-variant flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-primary">person</span>
                    Mesero:
                </span>
                <button 
                    wire:click="$set('filtroMesero', 'todos')"
                    class="rounded-xl px-3 py-1.5 text-xs font-extrabold transition-all {{ $filtroMesero === 'todos' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                >
                    Todas
                </button>
                @if(auth()->user()?->role?->slug === 'mesero')
                    <button 
                        wire:click="$set('filtroMesero', 'mis_mesas')"
                        class="rounded-xl px-3 py-1.5 text-xs font-extrabold transition-all {{ $filtroMesero === 'mis_mesas' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                    >
                        Mis Mesas
                    </button>
                @endif
            </div>
        </div>

        @if($filtroEstado !== 'todas' || $filtroZona !== 'todas' || $filtroMesero !== 'todos')
            <button 
                wire:click="$set('filtroEstado', 'todas'); $set('filtroZona', 'todas'); $set('filtroMesero', 'todos')"
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
                            <div class="flex items-center gap-1.5">
                                <span class="text-2xl font-mono font-black text-on-surface tracking-tight">
                                    {{ $mesa->numero }}
                                </span>
                                <!-- Botón Ver / Imprimir Código QR -->
                                <button 
                                    wire:click="abrirModalQr({{ $mesa->id }})"
                                    class="p-1 rounded-lg hover:bg-primary/10 text-on-surface-variant hover:text-primary transition-colors cursor-pointer"
                                    title="Código QR / Auto-pedido"
                                >
                                    <span class="material-symbols-outlined text-[16px]">qr_code_2</span>
                                </button>
                                @if(in_array(auth()->user()?->role?->slug, ['admin', 'gerente']))
                                    <div class="flex items-center gap-0.5 opacity-60 hover:opacity-100 transition-opacity">
                                        <button 
                                            wire:click="abrirModalEditarMesa({{ $mesa->id }})"
                                            class="p-1 rounded-lg hover:bg-surface-container text-on-surface-variant hover:text-on-surface transition-colors"
                                            title="Editar Mesa"
                                        >
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                        </button>
                                        @if($mesa->estado === 'libre')
                                            <button 
                                                wire:click="eliminarMesa({{ $mesa->id }})"
                                                wire:confirm="¿Deseas eliminar la Mesa #{{ $mesa->numero }}?"
                                                class="p-1 rounded-lg hover:bg-error-container/20 text-on-surface-variant hover:text-error transition-colors"
                                                title="Eliminar Mesa"
                                            >
                                                <span class="material-symbols-outlined text-[15px]">delete</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <span class="text-[10px] font-bold text-on-surface-variant block uppercase tracking-wider mt-0.5">
                                Zona {{ $mesa->zona }}
                            </span>
                            @if($mesa->mesero)
                                <div class="mt-1.5 flex items-center gap-1 flex-wrap">
                                    @if($mesa->mesero_id === auth()->id())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-primary/10 text-primary border border-primary/25" title="Mesa a tu cargo">
                                            <span class="material-symbols-outlined text-[13px]">person</span>
                                            <span>Atendida por ti</span>
                                        </span>
                                        <button 
                                            type="button"
                                            wire:click="liberarParaRelevo({{ $mesa->id }})"
                                            wire:confirm="¿Deseas liberar la Mesa #{{ $mesa->numero }} para que un compañero tome el relevo de tu turno?"
                                            class="text-[10px] font-extrabold text-amber-700 bg-amber-500/10 hover:bg-amber-500/20 px-2 py-0.5 rounded-full flex items-center gap-0.5 cursor-pointer transition border border-amber-500/20"
                                            title="Liberar mesa para relevo de descanso o cambio de turno"
                                        >
                                            <span class="material-symbols-outlined text-[12px]">pause_circle</span>
                                            <span>Liberar Relevo</span>
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-surface-container text-on-surface-variant border border-surface-container-high">
                                            <span class="material-symbols-outlined text-[13px]">badge</span>
                                            <span class="truncate max-w-[90px]">Atiende: {{ $mesa->mesero->name }}</span>
                                        </span>
                                        @if(in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true))
                                            <button 
                                                type="button"
                                                wire:click="abrirModalTransferir({{ $mesa->id }})"
                                                class="text-[10px] font-extrabold text-primary hover:underline flex items-center gap-0.5 cursor-pointer"
                                                title="Transferir / Reasignar mesa"
                                            >
                                                <span class="material-symbols-outlined text-[12px]">sync_alt</span>
                                                <span>Transferir</span>
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            @elseif($mesa->estado === 'ocupada')
                                <div class="mt-1.5 flex items-center gap-1 flex-wrap">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/15 text-amber-800 border border-amber-500/30 animate-pulse">
                                        <span class="material-symbols-outlined text-[13px]">hourglass_empty</span>
                                        <span>En Relevo</span>
                                    </span>
                                    @if(auth()->user()?->role?->slug === 'mesero')
                                        <button 
                                            type="button"
                                            wire:click="autoasignarMesa({{ $mesa->id }})"
                                            class="text-[10px] font-extrabold text-white bg-primary hover:bg-primary-container px-2 py-0.5 rounded-full flex items-center gap-0.5 cursor-pointer transition shadow-xs"
                                            title="Tomar el relevo y continuar atendiendo comanda activa"
                                        >
                                            <span class="material-symbols-outlined text-[12px]">handshake</span>
                                            <span>+ Tomar Relevo</span>
                                        </button>
                                    @elseif(in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true))
                                        <button 
                                            type="button"
                                            wire:click="abrirModalTransferir({{ $mesa->id }})"
                                            class="text-[10px] font-extrabold text-primary hover:underline flex items-center gap-0.5 cursor-pointer"
                                            title="Asignar mesero a esta mesa"
                                        >
                                            <span class="material-symbols-outlined text-[12px]">person_add</span>
                                            <span>Asignar</span>
                                        </button>
                                    @endif
                                </div>
                            @elseif(auth()->user()?->role?->slug === 'mesero')
                                <button 
                                    type="button"
                                    wire:click="autoasignarMesa({{ $mesa->id }})"
                                    class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-surface-container-high text-on-surface-variant hover:bg-primary hover:text-on-primary transition cursor-pointer border border-surface-container-highest"
                                    title="Autoasignarme esta mesa"
                                >
                                    <span class="material-symbols-outlined text-[12px]">person_add</span>
                                    <span>+ Atender Mesa</span>
                                </button>
                            @elseif(in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true))
                                <button 
                                    type="button"
                                    wire:click="abrirModalTransferir({{ $mesa->id }})"
                                    class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest transition cursor-pointer border border-surface-container-highest"
                                    title="Asignar mesero a esta mesa"
                                >
                                    <span class="material-symbols-outlined text-[12px]">person_add</span>
                                    <span>Asignar Mesero</span>
                                </button>
                            @endif
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

                    <!-- Active Order Container (if occupied / QR pending) -->
                    @if($pedidoActivo && $pedidoActivo->estado === 'solicitado_qr' && !$pedidoActivo->usuario_id)
                        <div class="mt-3.5 rounded-2xl bg-amber-50 border border-amber-300/80 p-2.5 space-y-1.5 shadow-sm animate-pulse">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-amber-900 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">notifications_active</span>
                                    Pedido QR Recibido
                                </span>
                                <span class="text-[11px] font-mono font-black text-amber-900">${{ number_format($pedidoActivo->total, 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[10px] text-amber-800 font-medium">
                                {{ $pedidoActivo->nombre_cliente ?? 'Comensal' }} · {{ $pedidoActivo->items->count() }} platos
                            </p>
                            <button 
                                wire:click="atenderPedidoQr({{ $pedidoActivo->id }})"
                                class="w-full py-1.5 rounded-xl bg-primary text-on-primary text-[11px] font-black shadow-sm hover:bg-primary/90 active:scale-95 transition flex items-center justify-center gap-1 cursor-pointer"
                            >
                                <span class="material-symbols-outlined text-[14px]">handshake</span>
                                <span>⚡ Atender Mesa</span>
                            </button>
                        </div>
                    @elseif($pedidoActivo)
                        <div class="mt-3.5 rounded-2xl bg-surface-container-low p-2.5 text-xs border border-surface-container-high">
                            <div class="flex items-center justify-between font-bold">
                                <span class="text-on-surface font-mono">{{ $pedidoActivo->codigo }}</span>
                                <span class="text-primary font-mono font-extrabold">${{ number_format($pedidoActivo->total, 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[10px] text-on-surface-variant font-medium">
                                <span class="capitalize">Estado: {{ $pedidoActivo->estado }}</span>
                                <span>{{ $pedidoActivo->items->count() }} items</span>
                            </div>
                            @if($pedidoActivo->usuario)
                                <div class="mt-1 text-[10px] text-on-surface-variant flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[12px] text-secondary">person</span>
                                    <span>Mesero: {{ $pedidoActivo->usuario->name }}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="mt-3.5 rounded-2xl border border-dashed border-surface-container-highest p-2 text-center text-[11px] text-on-surface-variant/70 font-medium">
                            Mesa disponible
                        </div>
                    @endif
                </div>

                <!-- Touch Interaction Button (Tactile 48px standard) -->
                <div class="mt-4 pt-3 border-t border-surface-container-high">
                    @if($pedidoActivo && $pedidoActivo->estado === 'solicitado_qr' && !$pedidoActivo->usuario_id)
                        <button 
                            wire:click="atenderPedidoQr({{ $pedidoActivo->id }})" 
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-primary text-xs font-extrabold text-on-primary shadow-sm hover:bg-primary-container transition-all active:scale-95 cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[18px]">handshake</span>
                            <span>⚡ Atender Pedido QR</span>
                        </button>
                    @elseif($mesa->estado === 'libre')
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

    <!-- Modal Crear / Editar Mesa -->
    @if ($modalMesaOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-scrim/60 backdrop-blur-sm animate-fade-in">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/30 space-y-5">
                <div class="flex items-center justify-between border-b border-outline-variant/20 pb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-secondary-container/50 flex items-center justify-center text-secondary border border-secondary/30">
                            <span class="material-symbols-outlined text-[20px]">table_restaurant</span>
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-on-surface">
                                {{ $mesaEditandoId ? "Editar Mesa #{$formMesa['numero']}" : "Crear Nueva Mesa" }}
                            </h2>
                            <p class="text-[11px] text-on-surface-variant">Configuración espacial del salón</p>
                        </div>
                    </div>
                    <button 
                        wire:click="$set('modalMesaOpen', false)"
                        class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface"
                    >
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <form wire:submit="guardarMesa" class="space-y-4">
                    <!-- Número de Mesa -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Identificador / Número de Mesa *</label>
                        <input 
                            type="text" 
                            wire:model="formMesa.numero" 
                            class="w-full h-11 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3.5 text-sm font-mono font-bold text-on-surface focus:border-primary focus:ring-1 focus:ring-primary"
                            placeholder="Ej. 11, B-02, T-05"
                            required
                        />
                        @error('formMesa.numero') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Zona del Local -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Zona del Local *</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['salon' => 'Salón Principal', 'barra' => 'Barra / Bar', 'terraza' => 'Terraza Exterior', 'vip' => 'Área VIP'] as $val => $label)
                                <button 
                                    type="button"
                                    wire:click="$set('formMesa.zona', '{{ $val }}')"
                                    class="h-10 px-3 rounded-xl text-xs font-bold text-left border transition-all flex items-center justify-between
                                           {{ $formMesa['zona'] === $val ? 'bg-primary/10 border-primary text-primary shadow-sm' : 'bg-surface-container-low border-outline-variant/30 text-on-surface-variant hover:bg-surface-container' }}"
                                >
                                    <span>{{ $label }}</span>
                                    @if($formMesa['zona'] === $val)
                                        <span class="material-symbols-outlined text-[16px]">check</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        @error('formMesa.zona') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Capacidad comensales -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Capacidad de Comensales (Pax) *</label>
                        <div class="flex items-center gap-2">
                            @foreach ([2, 4, 6, 8, 12] as $pax)
                                <button 
                                    type="button"
                                    wire:click="$set('formMesa.capacidad', {{ $pax }})"
                                    class="flex-1 h-10 rounded-xl text-xs font-mono font-extrabold border transition-all
                                           {{ $formMesa['capacidad'] === $pax ? 'bg-secondary text-on-secondary border-secondary shadow-sm' : 'bg-surface-container-low border-outline-variant/30 text-on-surface-variant hover:bg-surface-container' }}"
                                >
                                    {{ $pax }}p
                                </button>
                            @endforeach
                        </div>
                        <input 
                            type="number" 
                            wire:model="formMesa.capacidad" 
                            min="1" 
                            max="30"
                            class="mt-2 w-full h-10 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 text-xs font-mono text-on-surface"
                            placeholder="Capacidad personalizada..."
                        />
                        @error('formMesa.capacidad') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Sucursal -->
                    @if(count($sucursales) > 1)
                        <div>
                            <label class="block text-xs font-bold text-on-surface mb-1">Sede / Sucursal *</label>
                            <select 
                                wire:model="formMesa.sucursal_id"
                                class="w-full h-11 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3.5 text-xs font-bold text-on-surface"
                            >
                                @foreach ($sucursales as $suc)
                                    <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="pt-3 border-t border-outline-variant/20 flex items-center justify-end gap-2">
                        <button 
                            type="button"
                            wire:click="$set('modalMesaOpen', false)"
                            class="h-10 px-4 rounded-xl text-xs font-bold bg-surface-container hover:bg-surface-container-high text-on-surface-variant"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            class="h-10 px-5 rounded-xl text-xs font-extrabold bg-primary hover:bg-primary-container text-on-primary shadow-sm"
                        >
                            {{ $mesaEditandoId ? "Actualizar Mesa" : "Guardar Mesa" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Ver / Imprimir Código QR de Mesa (Aura Gastro Expressive OS) -->
    @if ($modalQrOpen && $mesaQr)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-scrim/60 backdrop-blur-sm animate-fade-in print:p-0 print:bg-white print:static">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/30 space-y-5 print:border-none print:shadow-none print:p-0">
                <!-- Modal Header (hidden when printing) -->
                <div class="flex items-center justify-between border-b border-outline-variant/20 pb-4 print:hidden">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                            <span class="material-symbols-outlined text-[20px]">qr_code_2</span>
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-on-surface">
                                Código QR · Mesa #{{ $mesaQr->numero }}
                            </h2>
                            <p class="text-[11px] text-on-surface-variant">Menú público y auto-pedido online</p>
                        </div>
                    </div>
                    <button 
                        wire:click="$set('modalQrOpen', false)"
                        class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Printable Table Stand Card (Soporte Acrílico de Mesa) -->
                <div id="stand-mesa-imprimible" class="rounded-3xl border-2 border-dashed border-stone-300 p-6 bg-white text-center space-y-4 shadow-sm print:border-solid print:border-stone-800 print:rounded-2xl">
                    <!-- Brand / Restaurant Header -->
                    <div class="space-y-1">
                        <div class="inline-flex items-center justify-center gap-1.5 px-3 py-1 rounded-full bg-stone-900 text-white text-[10px] font-black tracking-widest uppercase">
                            <span>🍽️ RESTOMASTER GOURMET</span>
                        </div>
                        <h3 class="text-3xl font-black text-stone-900 tracking-tight mt-1 font-mono">
                            MESA #{{ $mesaQr->numero }}
                        </h3>
                        <p class="text-[11px] text-stone-500 font-extrabold uppercase tracking-wider">
                            Zona {{ $mesaQr->zona }} · Capacidad {{ $mesaQr->capacidad }} pax
                        </p>
                    </div>

                    <!-- QR Code SVG Container -->
                    <div class="p-3 bg-white rounded-2xl border border-stone-200 inline-block shadow-inner">
                        {!! $qrSvg !!}
                    </div>

                    <!-- Scan Instructions -->
                    <div class="space-y-1 max-w-xs mx-auto">
                        <p class="text-xs font-black text-stone-900 leading-snug">
                            Escanea con tu celular para ver el menú y ordenar
                        </p>
                        <p class="text-[10px] text-stone-500">
                            Sin esperas · Tu comanda se envía directamente a sala y cocina.
                        </p>
                    </div>

                    <!-- Short URL Link -->
                    <div class="pt-2 border-t border-stone-100 font-mono text-[10px] text-stone-600 font-bold">
                        {{ $qrUrl }}
                    </div>
                </div>

                <!-- Modal Actions (hidden when printing) -->
                <div class="space-y-2 pt-1 print:hidden" x-data="{ copiado: false }">
                    <div class="flex items-center gap-2">
                        <button 
                            @click="navigator.clipboard.writeText('{{ $qrUrl }}'); copiado = true; setTimeout(() => copiado = false, 2500)"
                            class="flex-1 py-2.5 px-4 rounded-xl border border-stone-200 bg-surface-container-low text-xs font-extrabold text-on-surface hover:bg-surface-container flex items-center justify-center gap-1.5 transition cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[16px]">content_copy</span>
                            <span x-text="copiado ? '¡Enlace Copiado!' : 'Copiar Enlace Directo'"></span>
                        </button>
                        <a 
                            href="{{ $qrUrl }}" 
                            target="_blank"
                            class="py-2.5 px-3 rounded-xl border border-stone-200 bg-surface-container-low text-xs font-extrabold text-on-surface hover:bg-surface-container flex items-center justify-center transition"
                            title="Probar menú en pestaña nueva"
                        >
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                        </a>
                    </div>

                    <button 
                        onclick="window.print()"
                        class="w-full py-3 rounded-2xl bg-stone-900 text-white text-xs font-black shadow-lg hover:bg-stone-800 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">print</span>
                        <span>Imprimir Soporte de Mesa (Stand Acrílico)</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Transferir / Asignar Mesa -->
    @if($modalTransferirOpen && in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true))
        @php
            $mesaParaTransferir = $mesas->firstWhere('id', $mesaTransferirId);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="w-full max-w-md bg-surface-container-lowest rounded-3xl p-6 shadow-2xl border border-surface-container-highest space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-surface-container-high">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-container text-on-primary-container">
                            <span class="material-symbols-outlined text-[20px]">swap_horiz</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-on-surface">Transferir / Asignar Mesa</h3>
                            <p class="text-xs text-on-surface-variant">
                                Mesa #{{ $mesaParaTransferir?->numero }} · Zona {{ ucfirst($mesaParaTransferir?->zona ?? '') }}
                            </p>
                        </div>
                    </div>
                    <button 
                        wire:click="$set('modalTransferirOpen', false)"
                        class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                @if($mesaParaTransferir?->mesero)
                    <div class="flex items-center justify-between p-3 rounded-2xl bg-surface-container-low border border-surface-container-high">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">badge</span>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Atendida actualmente por:</p>
                                <p class="text-xs font-black text-on-surface">{{ $mesaParaTransferir->mesero->name }}</p>
                            </div>
                        </div>
                        <button 
                            type="button"
                            wire:click="desasignarMesero({{ $mesaParaTransferir->id }}); $set('modalTransferirOpen', false)"
                            class="px-2.5 py-1 text-[11px] font-bold text-error bg-error-container/40 hover:bg-error-container rounded-xl transition cursor-pointer"
                        >
                            Liberar
                        </button>
                    </div>
                @else
                    <div class="p-3 rounded-2xl bg-secondary-container/30 border border-secondary/20 text-xs text-secondary font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">info</span>
                        <span>Esta mesa actualmente no tiene mesero asignado.</span>
                    </div>
                @endif

                <div class="space-y-2">
                    <label class="block text-xs font-black uppercase tracking-wider text-on-surface-variant">
                        Seleccionar Mesero Receptor:
                    </label>
                    <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                        @forelse($meserosDisponibles as $mesero)
                            <label class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition-all {{ $nuevoMeseroId === $mesero->id ? 'border-primary bg-primary/5 shadow-sm' : 'border-surface-container-high bg-surface-container-low hover:bg-surface-container' }}">
                                <div class="flex items-center gap-3">
                                    <input 
                                        type="radio" 
                                        wire:model.live="nuevoMeseroId" 
                                        value="{{ $mesero->id }}" 
                                        class="text-primary focus:ring-primary h-4 w-4"
                                    />
                                    <div>
                                        <p class="text-xs font-black text-on-surface">{{ $mesero->name }}</p>
                                        <p class="text-[10px] text-on-surface-variant">{{ $mesero->email }}</p>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">person</span>
                            </label>
                        @empty
                            <p class="text-xs text-on-surface-variant italic py-3 text-center">No hay otros meseros activos registrados.</p>
                        @endforelse
                    </div>
                    @error('nuevoMeseroId') 
                        <p class="text-xs text-error font-bold mt-1">{{ $message }}</p> 
                    @enderror
                </div>

                <div class="p-3 rounded-2xl bg-surface-container-low text-[11px] text-on-surface-variant flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-primary shrink-0 mt-0.5">verified_user</span>
                    <span>La transferencia es libre e inmediata. Se registrará la entrega y recepción en el log de auditoría del sistema.</span>
                </div>

                <div class="flex items-center gap-2 pt-2 border-t border-surface-container-high">
                    <button 
                        type="button"
                        wire:click="$set('modalTransferirOpen', false)"
                        class="flex-1 py-2.5 px-4 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-extrabold text-on-surface hover:bg-surface-container transition cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button 
                        type="button"
                        wire:click="ejecutarTransferenciaMesa"
                        class="flex-1 py-2.5 px-4 rounded-xl bg-primary text-on-primary text-xs font-black shadow hover:opacity-90 active:scale-98 transition flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[16px]">check</span>
                        <span>Confirmar</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
