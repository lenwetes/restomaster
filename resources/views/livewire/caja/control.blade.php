<?php

use App\Models\Caja;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use Livewire\Volt\Component;

new class extends Component
{
    // Shift selection and active shift
    public ?int $turnoId = null;

    public ?int $cajaSeleccionadaId = null;

    // Shift opening form
    public bool $mostrarModalApertura = false;

    public float $fondoInicial = 150000.0;

    public string $notasApertura = '';

    // Movement (Egreso / Retiro / Ingreso) modal
    public bool $mostrarModalMovimiento = false;

    public string $tipoMovimiento = 'egreso'; // egreso, retiro, ingreso

    public float $montoMovimiento = 0.0;

    public string $conceptoMovimiento = '';

    public string $comprobanteMovimiento = '';

    public string $autorizadoPor = '';

    // Arqueo y Cierre de Turno (CAJ-04 / CAJ-05)
    public bool $mostrarModalCierre = false;

    public float $montoContado = 0.0;

    public string $notasCierre = '';

    public ?array $reporteZ = null;

    public bool $mostrarModalReporteZ = false;

    // Nueva Terminal de Caja State
    public bool $modalNuevaCajaOpen = false;

    public array $formCaja = [
        'nombre' => '',
        'codigo' => '',
    ];

    // Gestión y Administración de Terminales State
    public bool $modalGestionTerminalesOpen = false;

    public ?int $cajaEditandoId = null;

    public array $formEditarCaja = [
        'nombre' => '',
        'codigo' => '',
    ];

    public function abrirModalGestionTerminales(): void
    {
        $this->modalGestionTerminalesOpen = true;
        $this->cajaEditandoId = null;
    }

    public function iniciarEdicionCaja(int $id): void
    {
        $caja = Caja::findOrFail($id);
        $this->cajaEditandoId = $caja->id;
        $this->formEditarCaja = [
            'nombre' => $caja->nombre,
            'codigo' => $caja->codigo,
        ];
    }

    public function cancelarEdicionCaja(): void
    {
        $this->cajaEditandoId = null;
        $this->formEditarCaja = ['nombre' => '', 'codigo' => ''];
    }

    public function guardarEdicionCaja(): void
    {
        if (! $this->cajaEditandoId) {
            return;
        }

        $caja = Caja::findOrFail($this->cajaEditandoId);
        $this->authorize('update', $caja);

        $this->validate([
            'formEditarCaja.nombre' => 'required|string|max:60',
            'formEditarCaja.codigo' => 'required|string|max:20|unique:cajas,codigo,'.$caja->id,
        ]);

        app(CajaService::class)->actualizarCaja($caja, $this->formEditarCaja, auth()->user());
        $this->cajaEditandoId = null;

        $this->dispatch('notificacion', [
            'mensaje' => "Terminal {$caja->fresh()->nombre} actualizada con éxito.",
            'tipo' => 'success',
        ]);
    }

    public function alternarEstadoCaja(int $id): void
    {
        $caja = Caja::findOrFail($id);
        $this->authorize('update', $caja);

        app(CajaService::class)->alternarEstadoCaja($caja, auth()->user());

        $this->dispatch('notificacion', [
            'mensaje' => "Terminal {$caja->nombre} ahora está ".($caja->fresh()->activa ? 'activa' : 'inactiva').'.',
            'tipo' => 'info',
        ]);
    }

    public function eliminarCaja(int $id): void
    {
        $caja = Caja::findOrFail($id);
        $this->authorize('delete', $caja);

        try {
            $nombre = $caja->nombre;
            app(CajaService::class)->eliminarCaja($caja, auth()->user());

            if ($this->cajaSeleccionadaId === $id) {
                $this->cajaSeleccionadaId = Caja::value('id');
            }

            $this->dispatch('notificacion', [
                'mensaje' => "Terminal {$nombre} eliminada permanentemente.",
                'tipo' => 'success',
            ]);
        } catch (\DomainException $e) {
            $this->dispatch('notificacion', [
                'mensaje' => $e->getMessage(),
                'tipo' => 'error',
            ]);
        }
    }

    public function abrirModalNuevaCaja(): void
    {
        $conteo = Caja::count() + 1;
        $this->formCaja = [
            'nombre' => "Caja {$conteo} Barra",
            'codigo' => "CAJA-0{$conteo}",
        ];
        $this->modalNuevaCajaOpen = true;
    }

    public function guardarNuevaCaja(): void
    {
        $this->authorize('create', Caja::class);

        $this->validate([
            'formCaja.nombre' => 'required|string|max:60',
            'formCaja.codigo' => 'required|string|max:20|unique:cajas,codigo',
        ]);

        $caja = app(CajaService::class)->crearCaja($this->formCaja, auth()->user());
        $this->cajaSeleccionadaId = $caja->id;
        $this->modalNuevaCajaOpen = false;

        $this->dispatch('notificacion', [
            'mensaje' => "Terminal {$caja->nombre} ({$caja->codigo}) creada exitosamente.",
            'tipo' => 'success',
        ]);
    }

    public function mount(): void
    {
        $userSucursalId = auth()->user()?->sucursal_id;
        $cajasQuery = Caja::query();
        if ($userSucursalId) {
            $cajasQuery->where('sucursal_id', $userSucursalId);
        }
        $caja = $cajasQuery->first() ?? Caja::first();
        if ($caja) {
            $this->cajaSeleccionadaId = $caja->id;
        }

        $turnosQuery = TurnoCaja::where('estado', 'abierto');
        if ($userSucursalId) {
            $turnosQuery->whereHas('caja', fn ($q) => $q->where('sucursal_id', $userSucursalId));
        }
        $turnoActivo = $turnosQuery->latest()->first();
        if ($turnoActivo) {
            $this->turnoId = $turnoActivo->id;
            $this->montoContado = 0.0;
        }
    }

    public function abrirModalApertura(): void
    {
        $this->mostrarModalApertura = true;
    }

    public function abrirTurno(): void
    {
        $this->authorize('abrir', TurnoCaja::class);

        $this->validate([
            'cajaSeleccionadaId' => 'required|exists:cajas,id',
            'fondoInicial' => 'required|numeric|min:0',
        ]);

        $caja = Caja::findOrFail($this->cajaSeleccionadaId);
        $cajaService = app(CajaService::class);

        try {
            $turno = $cajaService->abrirTurno($caja, auth()->user(), $this->fondoInicial, $this->notasApertura);
            $this->turnoId = $turno->id;
            $this->mostrarModalApertura = false;
            $this->notasApertura = '';

            $this->dispatch('notificacion', [
                'mensaje' => "¡Turno #{$turno->id} abierto con éxito en {$caja->nombre}!",
                'tipo' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->addError('fondoInicial', $e->getMessage());
        }
    }

    public function abrirModalMovimiento(string $tipo): void
    {
        $this->tipoMovimiento = in_array($tipo, ['ingreso', 'egreso', 'retiro']) ? $tipo : 'ingreso';
        $this->montoMovimiento = 0.0;
        $this->conceptoMovimiento = '';
        $this->comprobanteMovimiento = '';
        $this->autorizadoPor = '';
        $this->mostrarModalMovimiento = true;
    }

    public function abrirGavetaManual(): void
    {
        $this->authorize('guardarMovimiento', TurnoCaja::class);
        app(\App\Services\ImpresionService::class)->despacharAperturaGaveta(auth()->user());

        $this->dispatch('notificacion', [
            'mensaje' => 'Señal de apertura enviada a la gaveta de dinero.',
            'tipo' => 'success',
        ]);
    }

    public function registrarMovimiento(): void
    {
        $this->authorize('guardarMovimiento', TurnoCaja::class);

        $rules = [
            'tipoMovimiento' => 'required|in:ingreso,egreso,retiro',
            'montoMovimiento' => 'required|numeric|min:1',
            'conceptoMovimiento' => 'required|string|min:3',
        ];

        if (in_array($this->tipoMovimiento, ['egreso', 'retiro'])) {
            $rules['autorizadoPor'] = 'required|string|min:3';
        }

        $this->validate($rules);

        $usuarioActual = auth()->user();
        if (in_array($this->tipoMovimiento, ['egreso', 'retiro'])
            && strcasecmp(trim($this->autorizadoPor), trim($usuarioActual?->name ?? '')) === 0
            && ! in_array($usuarioActual?->role?->slug, ['admin', 'gerente'])) {
            $this->addError('autorizadoPor', 'Un cajero no puede auto-autorizarse un egreso o retiro. Requiere autorización de un superior.');
            return;
        }

        $turno = TurnoCaja::findOrFail($this->turnoId);
        $cajaService = app(CajaService::class);

        try {
            $cajaService->registrarMovimiento(
                $turno,
                $this->tipoMovimiento,
                $this->montoMovimiento,
                $this->conceptoMovimiento,
                'efectivo',
                $this->comprobanteMovimiento,
                $this->autorizadoPor,
                $usuarioActual
            );

            $this->mostrarModalMovimiento = false;
            $this->dispatch('notificacion', [
                'mensaje' => "Movimiento de {$this->tipoMovimiento} registrado correctamente.",
                'tipo' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->addError('montoMovimiento', $e->getMessage());
        }
    }

    public function abrirModalCierre(): void
    {
        $turno = TurnoCaja::findOrFail($this->turnoId);
        app(CajaService::class)->recalcularEsperado($turno);
        $this->montoContado = 0.0;
        $this->notasCierre = '';
        $this->mostrarModalCierre = true;
    }

    public function ejecutarCierreTurno(): void
    {
        $this->authorize('cerrar', TurnoCaja::class);

        $this->validate([
            'montoContado' => 'required|numeric|min:0',
        ]);

        $turno = TurnoCaja::findOrFail($this->turnoId);
        $cajaService = app(CajaService::class);

        try {
            $turno = $cajaService->cerrarTurno($turno, $this->montoContado, auth()->user(), $this->notasCierre);
            $this->reporteZ = $cajaService->generarReporteZ($turno);
            $this->mostrarModalCierre = false;
            $this->mostrarModalReporteZ = true;

            $this->dispatch('notificacion', [
                'mensaje' => "Turno #{$turno->id} cerrado correctamente. Arqueo completado.",
                'tipo' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->addError('montoContado', $e->getMessage());
        }
    }

    public function generarReporteX(): void
    {
        if (! $this->turnoId) {
            return;
        }
        $turno = TurnoCaja::findOrFail($this->turnoId);
        $this->reporteZ = app(CajaService::class)->generarReporteZ($turno);
        $this->mostrarModalReporteZ = true;
    }

    public function cerrarModalReporteZ(): void
    {
        $this->mostrarModalReporteZ = false;
        $this->reporteZ = null;

        // Refresh active shift
        $turnoActivo = TurnoCaja::where('estado', 'abierto')->latest()->first();
        $this->turnoId = $turnoActivo?->id;
    }

    public function with(): array
    {
        $turnoActivo = $this->turnoId ? TurnoCaja::with(['caja.sucursal', 'cajero', 'movimientos.usuario'])->withCount('pedidos')->find($this->turnoId) : null;
        $cajas = Caja::where('activa', true)->get();
        $ultimosTurnos = TurnoCaja::with(['caja', 'cajero'])->latest()->take(5)->get();

        return [
            'turno' => $turnoActivo,
            'cajas' => $cajas,
            'todasLasCajas' => Caja::withCount('turnos')->orderBy('id')->get(),
            'ultimosTurnos' => $ultimosTurnos,
        ];
    }
}; ?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">payments</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Control de Caja y Operaciones del Turno
                </h1>
                <span class="rounded-full bg-secondary-container/50 px-2.5 py-0.5 text-[11px] font-bold text-on-secondary-container border border-secondary/30">
                    CAJ-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Sesiones independientes, arqueo ciego, control de efectivo y cierre fiscal Z
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if(in_array(auth()->user()?->role?->slug, ['admin', 'gerente']))
                <button
                    wire:click="abrirModalGestionTerminales"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest px-3.5 py-2 text-xs font-extrabold text-on-surface transition-all active:scale-95"
                >
                    <span class="material-symbols-outlined text-[16px] text-primary">devices</span>
                    <span>Gestionar Terminales</span>
                </button>
                <button
                    wire:click="abrirModalNuevaCaja"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-secondary px-3.5 py-2 text-xs font-extrabold text-on-secondary shadow-sm hover:bg-secondary-fixed-dim transition-all active:scale-95"
                >
                    <span class="material-symbols-outlined text-[16px]">add_box</span>
                    <span>+ Nueva Terminal</span>
                </button>
            @endif
            <a
                href="{{ route('pos') }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest px-3.5 py-2 text-xs font-extrabold text-on-surface transition-all"
            >
                <span class="material-symbols-outlined text-[16px] text-primary">point_of_sale</span>
                <span>Ir al POS</span>
            </a>
        </div>
    </header>

    @if($turno && $turno->estado === 'abierto')
        <!-- SUB-HEADER CONTEXTUAL Y COMANDOS DE TURNO (Stitch CAJ-01 Aura Gastro Expressive OS) -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-extrabold text-on-surface tracking-tight">
                        {{ $turno->caja->nombre }}
                    </h2>
                    <div class="flex items-center gap-1.5 bg-secondary-container/40 border border-secondary/30 px-3 py-1 rounded-full">
                        <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                        <span class="text-xs text-on-secondary-container uppercase font-extrabold tracking-wider">
                            Turno #{{ $turno->id }} · ACTIVO
                        </span>
                    </div>
                    <span class="text-[10px] font-bold font-mono text-on-surface-variant bg-surface-container-low px-2 py-0.5 rounded-md border border-surface-container-high">
                        {{ $turno->caja->codigo }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-on-surface-variant">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-primary">badge</span>
                        <span><strong>Cajero:</strong> {{ $turno->cajero->name }}</span>
                    </div>
                    <span class="text-surface-container-highest hidden sm:inline">•</span>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-primary">schedule</span>
                        <span><strong>Apertura:</strong> {{ $turno->apertura_en->format('d/m/Y H:i') }} (hace {{ $turno->apertura_en->diffForHumans(null, true) }})</span>
                    </div>
                    <span class="text-surface-container-highest hidden sm:inline">•</span>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-secondary">encrypted</span>
                        <span class="text-secondary font-medium">Bóveda de Seguridad Conectada</span>
                    </div>
                </div>
            </div>

            <!-- BOTONES DE ACCIÓN RÁPIDA (Stitch CAJ-01) -->
            <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                <button 
                    wire:click="abrirModalMovimiento('ingreso')"
                    class="h-11 px-3.5 rounded-xl bg-secondary/15 hover:bg-secondary/25 text-secondary transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-secondary/30" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    <span>+ Registrar Ingreso</span>
                </button>
                <button 
                    wire:click="abrirModalMovimiento('egreso')"
                    class="h-11 px-3.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-surface-container-high" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px] text-error">price_change</span>
                    <span>Registrar Egreso</span>
                </button>
                <button 
                    wire:click="abrirModalMovimiento('retiro')"
                    class="h-11 px-3.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-surface-container-high" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px] text-tertiary">account_balance</span>
                    <span>Retiro a Banco</span>
                </button>
                <button 
                    wire:click="abrirGavetaManual"
                    class="h-11 px-3.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-surface-container-high" 
                    type="button"
                    title="Enviar comando ESC/POS para abrir cajón de dinero físicamente"
                >
                    <span class="material-symbols-outlined text-[18px] text-primary">meeting_room</span>
                    <span>Abrir Gaveta</span>
                </button>
                <button 
                    wire:click="generarReporteX"
                    class="h-11 px-3.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-surface-container-high" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">receipt</span>
                    <span>Corte X Parcial</span>
                </button>
                <button 
                    wire:click="abrirModalCierre"
                    class="h-11 px-4 rounded-xl bg-primary text-on-primary hover:bg-primary-container transition-all flex items-center gap-1.5 shadow-md shadow-primary/20 active:scale-95 text-xs font-black" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                    <span>Arquear y Cerrar Turno</span>
                </button>
            </div>
        </div>

        <!-- TARJETAS DE MÉTRICAS / TOTALES EN VIVO (Stitch CAJ-01) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Fondo Inicial -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col justify-between relative overflow-hidden hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Fondo Inicial de Apertura</span>
                        <div class="font-mono text-2xl lg:text-3xl font-black text-on-surface mt-1">
                            ${{ number_format($turno->monto_inicial, 2) }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-surface-container flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[22px]">savings</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center justify-between text-xs text-on-surface-variant">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px] text-secondary">check_circle</span>
                        <span>Registrado a las {{ $turno->apertura_en->format('H:i') }}h</span>
                    </span>
                    <span class="text-[10px] font-bold bg-surface-container px-2 py-0.5 rounded text-on-surface">Base Fija</span>
                </div>
            </div>

            <!-- Ventas Totales del Turno -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col justify-between relative overflow-hidden hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ventas Totales del Turno</span>
                        <div class="font-mono text-2xl lg:text-3xl font-black text-primary mt-1">
                            ${{ number_format($turno->total_ventas, 2) }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-primary-fixed text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">point_of_sale</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container-high flex flex-col gap-1 text-[11px] text-on-surface-variant">
                    <div class="flex justify-between items-center font-bold">
                        <span>{{ $turno->pedidos_count ?? 0 }} comandas cobradas</span>
                        <span class="text-secondary">En vivo</span>
                    </div>
                    <div class="flex items-center gap-1 font-mono text-[10px]">
                        <span>Ef: <strong class="text-on-surface">${{ number_format($turno->total_ventas_efectivo, 0) }}</strong></span>
                        <span>•</span>
                        <span>Tarj: <strong class="text-on-surface">${{ number_format($turno->total_ventas_tarjeta, 0) }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Egresos y Pagos Menores -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col justify-between relative overflow-hidden hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Egresos y Retiros</span>
                        <div class="font-mono text-2xl lg:text-3xl font-black text-error mt-1">
                            -${{ number_format((float)$turno->total_egresos + (float)$turno->total_retiros, 2) }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-error-container text-error flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">shopping_bag</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center justify-between text-xs text-on-surface-variant">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px] text-error">receipt_long</span>
                        <span>{{ $turno->movimientos->count() }} autorizados</span>
                    </span>
                    <span class="text-[10px] font-bold bg-error-container text-error px-2 py-0.5 rounded">Auditable</span>
                </div>
            </div>

            <!-- Efectivo Esperado en Gaveta -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-secondary/40 shadow-sm flex flex-col justify-between relative overflow-hidden ring-1 ring-secondary/20 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-secondary">Efectivo Esperado en Gaveta</span>
                            <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                        </div>
                        <div class="font-mono text-2xl lg:text-3xl font-black text-secondary mt-1">
                            ${{ number_format($turno->monto_esperado_efectivo, 2) }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-secondary-container text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">payments</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center justify-between text-xs text-on-surface-variant">
                    <span class="text-[10px] font-mono">Fondo + Ef. Ventas + Ingresos - Egresos</span>
                    <span class="text-[10px] font-bold text-on-secondary-container bg-secondary-container/50 px-2 py-0.5 rounded">Cuadre Automático</span>
                </div>
            </div>
        </div>

        <!-- MOVEMENTS & AUDIT LOG TABLE (Stitch CAJ-01) -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">history</span>
                    <h3 class="text-sm font-extrabold text-on-surface">Historial de Movimientos y Gastos del Turno</h3>
                </div>
                <span class="text-xs font-mono text-on-surface-variant">
                    {{ $turno->movimientos->count() }} transacciones de caja
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-container-high text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                            <th class="py-3 px-3 rounded-l-xl">Hora</th>
                            <th class="py-3 px-3">Tipo</th>
                            <th class="py-3 px-3">Concepto / Motivo</th>
                            <th class="py-3 px-3">Método</th>
                            <th class="py-3 px-3">Comprobante</th>
                            <th class="py-3 px-3">Autorizado Por</th>
                            <th class="py-3 px-3 text-right rounded-r-xl">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high font-medium">
                        @forelse($turno->movimientos as $mov)
                            @php
                                $tipoColor = match($mov->tipo) {
                                    'egreso' => 'text-error bg-error-container/60 border-error/30',
                                    'retiro' => 'text-tertiary bg-tertiary-container/30 border-tertiary/30',
                                    'ingreso' => 'text-secondary bg-secondary-container/60 border-secondary/30',
                                    default => 'text-on-surface-variant',
                                };
                            @endphp
                            <tr class="hover:bg-surface-container-low/50 transition-colors">
                                <td class="py-3 px-3 font-mono text-on-surface-variant">{{ $mov->created_at->format('H:i:s') }}</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded-full border text-[10px] font-extrabold uppercase {{ $tipoColor }}">
                                        {{ $mov->tipo }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-on-surface font-semibold">{{ $mov->concepto }}</td>
                                <td class="py-3 px-3 text-on-surface-variant capitalize">{{ $mov->metodo_pago }}</td>
                                <td class="py-3 px-3 font-mono text-on-surface-variant">{{ $mov->numero_comprobante ?? 'S/C' }}</td>
                                <td class="py-3 px-3 text-on-surface-variant">{{ $mov->autorizado_por ?? $mov->usuario->name }}</td>
                                <td class="py-3 px-3 text-right font-mono font-bold {{ $mov->tipo === 'ingreso' ? 'text-secondary' : 'text-error' }}">
                                    {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-on-surface-variant">
                                    No hay egresos o retiros registrados en este turno todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @else
        <!-- NO OPEN SHIFT STATE: Prompt to open a new shift -->
        <div class="rounded-3xl border-2 border-dashed border-surface-container-highest bg-surface-container-lowest p-12 text-center shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-primary-fixed text-primary flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-[36px]">point_of_sale</span>
            </div>
            <h2 class="text-xl font-extrabold text-on-surface">No hay ningún turno de caja abierto</h2>
            <p class="text-xs text-on-surface-variant max-w-md mx-auto mt-1.5 leading-relaxed">
                Para comenzar a recibir pagos en el POS, registrar comandas y gestionar cobros de mesas, es obligatorio realizar la apertura de turno con un fondo inicial en gaveta.
            </p>

            <button 
                wire:click="abrirModalApertura"
                class="mt-6 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container active:scale-95 transition-all"
            >
                <span class="material-symbols-outlined text-[18px]">lock_open</span>
                <span>Abrir Turno de Caja con Fondo Inicial</span>
            </button>
        </div>

        <!-- Recent Closed Shifts Summary -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm">
            <h3 class="text-sm font-extrabold text-on-surface mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">fact_check</span>
                <span>Últimos Turnos y Arqueos Finalizados</span>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-container-high text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                            <th class="py-2.5 px-3 rounded-l-xl">Turno</th>
                            <th class="py-2.5 px-3">Caja</th>
                            <th class="py-2.5 px-3">Cajero</th>
                            <th class="py-2.5 px-3">Apertura</th>
                            <th class="py-2.5 px-3">Cierre</th>
                            <th class="py-2.5 px-3 text-right">Ventas Totales</th>
                            <th class="py-2.5 px-3 text-right rounded-r-xl">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high">
                        @forelse($ultimosTurnos as $t)
                            <tr class="hover:bg-surface-container-low/50 transition-colors">
                                <td class="py-2.5 px-3 font-mono font-bold text-on-surface">#{{ $t->id }}</td>
                                <td class="py-2.5 px-3 text-on-surface">{{ $t->caja->nombre }}</td>
                                <td class="py-2.5 px-3 text-on-surface-variant">{{ $t->cajero->name }}</td>
                                <td class="py-2.5 px-3 font-mono text-on-surface-variant">{{ $t->apertura_en->format('d/m/y H:i') }}</td>
                                <td class="py-2.5 px-3 font-mono text-on-surface-variant">{{ $t->cierre_en?->format('d/m/y H:i') ?? 'Abierto' }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-primary">${{ number_format($t->total_ventas, 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold {{ $t->diferencia >= 0 ? 'text-secondary' : 'text-error' }}">
                                    {{ $t->diferencia >= 0 ? '+' : '' }}${{ number_format($t->diferencia, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-on-surface-variant">No hay historial previo de turnos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- MODAL: APERTURA DE CAJA -->
    @if($mostrarModalApertura)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">lock_open</span>
                        </div>
                        <h3 class="text-base font-extrabold text-on-surface">Apertura de Turno de Caja</h3>
                    </div>
                    <button wire:click="$set('mostrarModalApertura', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Seleccionar Caja Terminal:</label>
                        <select 
                            wire:model="cajaSeleccionadaId" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                        >
                            @foreach($cajas as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Fondo Inicial de Efectivo en Gaveta:</label>
                        <input 
                            type="number" 
                            step="1000" 
                            wire:model="fondoInicial" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                        />
                        @error('fondoInicial') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Notas de Apertura (Opcional):</label>
                        <textarea 
                            wire:model="notasApertura" 
                            rows="2"
                            placeholder="Ej: Base de cambio entregada por el gerente de turno..."
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2.5 text-xs text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary focus:ring-0"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button 
                        wire:click="$set('mostrarModalApertura', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cancelar
                    </button>
                    <button 
                        wire:click="abrirTurno" 
                        class="rounded-xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container"
                    >
                        ✓ Confirmar Apertura
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: REGISTRAR INGRESO / EGRESO / RETIRO -->
    @if($mostrarModalMovimiento)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        @if($tipoMovimiento === 'ingreso')
                            <div class="w-8 h-8 rounded-lg bg-secondary-container text-secondary flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">add_circle</span>
                            </div>
                            <h3 class="text-base font-extrabold text-on-surface">Registrar Ingreso de Efectivo</h3>
                        @elseif($tipoMovimiento === 'retiro')
                            <div class="w-8 h-8 rounded-lg bg-tertiary-container text-tertiary flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">account_balance</span>
                            </div>
                            <h3 class="text-base font-extrabold text-on-surface">Registrar Retiro a Banco</h3>
                        @else
                            <div class="w-8 h-8 rounded-lg bg-error-container text-error flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">price_change</span>
                            </div>
                            <h3 class="text-base font-extrabold text-on-surface">Registrar Egreso de Caja</h3>
                        @endif
                    </div>
                    <button wire:click="$set('mostrarModalMovimiento', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Monto del Movimiento:</label>
                        <input 
                            type="number" 
                            step="100" 
                            wire:model="montoMovimiento" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                            placeholder="0.00"
                        />
                        @error('montoMovimiento') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Concepto / Motivo:</label>
                        <input 
                            type="text" 
                            wire:model="conceptoMovimiento" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0"
                            placeholder="{{ $tipoMovimiento === 'ingreso' ? 'Ej: Inyección de cambio / sencillo, aporte a caja...' : 'Ej: Compra de hielo de urgencia, insumos...' }}"
                        />
                        @error('conceptoMovimiento') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">N° Recibo / Comprobante:</label>
                            <input 
                                type="text" 
                                wire:model="comprobanteMovimiento" 
                                class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0 font-mono"
                                placeholder="{{ $tipoMovimiento === 'ingreso' ? 'REC-001 (Opc.)' : 'FAC-1234' }}"
                            />
                        </div>
                        <div>
                            @if($tipoMovimiento === 'ingreso')
                                <label class="text-xs font-bold text-on-surface-variant">Entregado por (Opcional):</label>
                                <input 
                                    type="text" 
                                    wire:model="autorizadoPor" 
                                    class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0"
                                    placeholder="Nombre de quien aporta"
                                />
                            @else
                                <label class="text-xs font-bold text-on-surface-variant">Autorizado por:</label>
                                <input 
                                    type="text" 
                                    wire:model="autorizadoPor" 
                                    class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0"
                                    placeholder="Superior que autoriza"
                                />
                                @error('autorizadoPor') <span class="text-[11px] text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button 
                        wire:click="$set('mostrarModalMovimiento', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cancelar
                    </button>
                    @if($tipoMovimiento === 'ingreso')
                        <button 
                            wire:click="registrarMovimiento" 
                            class="rounded-xl bg-secondary py-3 text-xs font-black text-on-secondary shadow-md hover:bg-secondary-fixed-dim"
                        >
                            ✓ Registrar Ingreso
                        </button>
                    @else
                        <button 
                            wire:click="registrarMovimiento" 
                            class="rounded-xl bg-error py-3 text-xs font-black text-on-error shadow-md hover:opacity-90"
                        >
                            ✓ Registrar Salida
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: ARQUEO CIEGO Y CIERRE FISCAL Z (Stitch CAJ-04 / CAJ-05) -->
    @if($mostrarModalCierre && $turno)
        @php
            $diferenciaActual = (float)$montoContado - (float)$turno->monto_esperado_efectivo;
            $esCuadrada = abs($diferenciaActual) < 0.01;
            $esSobrante = $diferenciaActual > 0.01;
            $esFaltante = $diferenciaActual < -0.01;
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">lock_reset</span>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-on-surface">Arqueo Ciego y Cierre de Turno</h3>
                            <p class="text-[11px] text-on-surface-variant">Turno #{{ $turno->id }} · {{ $turno->caja->nombre }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('mostrarModalCierre', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <!-- Summary banner -->
                    <div class="grid grid-cols-2 gap-2 bg-surface-container-low p-3 rounded-2xl border border-surface-container-high text-xs">
                        <div>
                            <span class="text-on-surface-variant text-[10px] uppercase font-bold">Fondo Inicial:</span>
                            <p class="font-mono font-bold text-on-surface">${{ number_format($turno->monto_inicial, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[10px] uppercase font-bold">Ventas Efectivo:</span>
                            <p class="font-mono font-bold text-primary">+${{ number_format($turno->total_ventas_efectivo, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[10px] uppercase font-bold">Egresos / Retiros:</span>
                            <p class="font-mono font-bold text-error">-${{ number_format((float)$turno->total_egresos + (float)$turno->total_retiros, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[10px] uppercase font-bold">Efectivo Teórico:</span>
                            <p class="font-mono font-black text-secondary">${{ number_format($turno->monto_esperado_efectivo, 2) }}</p>
                        </div>
                    </div>

                    <!-- Blind Cash Input -->
                    <div>
                        <label class="text-xs font-extrabold text-on-surface flex items-center justify-between">
                            <span>Efectivo Físico Contado en Gaveta:</span>
                            <span class="text-[10px] text-primary font-mono">Conteo Real</span>
                        </label>
                        <input 
                            type="number" 
                            step="100" 
                            wire:model.live="montoContado" 
                            class="mt-1 w-full rounded-xl border-2 border-surface-container-high bg-surface-container-low p-3.5 font-mono text-2xl font-black text-on-surface focus:border-primary focus:ring-0"
                        />
                    </div>

                    <!-- Discrepancy Beacon Badge -->
                    <div class="p-3 rounded-2xl border flex items-center justify-between {{ $esCuadrada ? 'bg-secondary-container/40 border-secondary/30 text-secondary' : ($esSobrante ? 'bg-primary-fixed/50 border-primary/30 text-primary' : 'bg-error-container/40 border-error/30 text-error') }}">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[22px]">
                                {{ $esCuadrada ? 'check_circle' : ($esSobrante ? 'warning' : 'error') }}
                            </span>
                            <div class="flex flex-col">
                                <span class="text-xs font-extrabold uppercase tracking-wider">
                                    {{ $esCuadrada ? 'Caja Cuadrada Exacta' : ($esSobrante ? 'Sobrante en Caja (+)' : 'Faltante en Caja (-)') }}
                                </span>
                                <span class="text-[10px] opacity-80">
                                    {{ $esCuadrada ? 'El dinero físico coincide al 100% con el sistema.' : 'Se registrará asiento contable de ajuste.' }}
                                </span>
                            </div>
                        </div>
                        <div class="font-mono text-lg font-black">
                            {{ $diferenciaActual >= 0 ? '+' : '' }}${{ number_format($diferenciaActual, 2) }}
                        </div>
                    </div>

                    <!-- Closing notes -->
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Observaciones de Cierre (Justificación si hay descuadre):</label>
                        <textarea 
                            wire:model="notasCierre" 
                            rows="2"
                            placeholder="Comentario sobre el turno, detalle del recuento o justificación..."
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0 placeholder:text-on-surface-variant/50"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button 
                        wire:click="$set('mostrarModalCierre', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cancelar
                    </button>
                    <button 
                        wire:click="ejecutarCierreTurno" 
                        class="rounded-xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container"
                    >
                        ✓ Confirmar y Emitir Reporte Z
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- THERMAL REPORTE FISCAL Z MODAL (80mm Simulation) -->
    @if($mostrarModalReporteZ && $reporteZ)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-sm rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest font-mono text-xs">
                <!-- Header -->
                <div class="text-center border-b border-dashed border-surface-container-high pb-4">
                    <p class="text-base font-black tracking-tight text-primary">🍽️ RESTOMASTER 🍽️</p>
                    <p class="text-[11px] font-bold text-on-surface">CORTE DE CAJA — REPORTE FISCAL Z</p>
                    <p class="text-[10px] text-on-surface-variant">{{ $reporteZ['sucursal'] }} • {{ $reporteZ['caja_nombre'] }}</p>
                    <p class="text-[9px] text-on-surface-variant/60">NIT: 901.884.200-1 · Res. DIAN 18764022</p>
                </div>

                <!-- Shift context info -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span>TURNO:</span>
                        <span class="font-bold text-primary">#{{ $reporteZ['turno_id'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>CAJERO:</span>
                        <span>{{ $reporteZ['cajero'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>APERTURA:</span>
                        <span>{{ $reporteZ['apertura'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>CIERRE:</span>
                        <span>{{ $reporteZ['cierre'] }}</span>
                    </div>
                </div>

                <!-- Sales breakdown -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span>FONDO INICIAL:</span>
                        <span class="font-bold">${{ number_format($reporteZ['fondo_inicial'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>TRANSACCIONES POS:</span>
                        <span>{{ $reporteZ['total_transacciones'] }} pedidos</span>
                    </div>
                    <div class="flex justify-between font-bold text-on-surface pt-1 border-t border-dashed border-surface-container-high">
                        <span>VENTAS TOTALES:</span>
                        <span class="text-primary">${{ number_format($reporteZ['total_ventas'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pl-2">
                        <span>• Efectivo:</span>
                        <span>${{ number_format($reporteZ['desglose_pagos']['efectivo'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pl-2">
                        <span>• Tarjeta:</span>
                        <span>${{ number_format($reporteZ['desglose_pagos']['tarjeta'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pl-2">
                        <span>• Transferencia/Otros:</span>
                        <span>${{ number_format($reporteZ['desglose_pagos']['transferencia'], 2) }}</span>
                    </div>
                </div>

                <!-- Expenses and Cash Count -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between text-error">
                        <span>EGRESOS Y RETIROS:</span>
                        <span>-${{ number_format($reporteZ['total_egresos'] + $reporteZ['total_retiros'], 2) }}</span>
                    </div>
                    <div class="flex justify-between pt-1">
                        <span>EFECTIVO ESPERADO:</span>
                        <span class="font-bold text-secondary">${{ number_format($reporteZ['monto_esperado'], 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-on-surface">
                        <span>EFECTIVO CONTADO:</span>
                        <span>${{ number_format($reporteZ['monto_real'], 2) }}</span>
                    </div>
                    <div class="flex justify-between font-black pt-1 border-t border-dashed border-surface-container-high {{ $reporteZ['diferencia'] >= 0 ? 'text-secondary' : 'text-error' }}">
                        <span>DIFERENCIA ({{ $reporteZ['estado_cuadre'] }}):</span>
                        <span>{{ $reporteZ['diferencia'] >= 0 ? '+' : '' }}${{ number_format($reporteZ['diferencia'], 2) }}</span>
                    </div>
                </div>

                <!-- Fiscal Footer -->
                <div class="pt-3 text-center text-[9px] text-on-surface-variant space-y-1">
                    <p class="font-bold text-on-surface">CIERRE AUDITABLE Y CONCILIADO</p>
                    <p>Transmitido automáticamente a contabilidad interna</p>
                </div>

                <!-- Action buttons -->
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button 
                        onclick="window.print()" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high"
                    >
                        🖨️ Imprimir
                    </button>
                    <button 
                        wire:click="cerrarModalReporteZ" 
                        class="rounded-xl bg-primary py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container"
                    >
                        ✓ Entendido
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: GESTIÓN DE TERMINALES DE CAJA -->
    @if($modalGestionTerminalesOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
            <div class="w-full max-w-2xl rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
                            <span class="material-symbols-outlined text-[20px]">devices</span>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-on-surface">Gestión de Terminales de Caja</h3>
                            <p class="text-[11px] text-on-surface-variant">Edita nombres, códigos, activa/desactiva o elimina puntos de cobro físicos.</p>
                        </div>
                    </div>
                    <button wire:click="$set('modalGestionTerminalesOpen', false)" class="text-on-surface-variant hover:text-on-surface transition-colors p-1 rounded-lg">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="space-y-2.5 max-h-[60vh] overflow-y-auto pr-1">
                    @forelse($todasLasCajas as $cajaItem)
                        <div class="rounded-2xl border {{ $cajaItem->activa ? 'border-surface-container-highest bg-surface-container-low/40' : 'border-dashed border-surface-container-high bg-surface-container-highest/20 opacity-75' }} p-4 transition-all hover:shadow-sm">
                            @if($cajaEditandoId === $cajaItem->id)
                                <!-- MODO EDICIÓN EN LÍNEA -->
                                <form wire:submit="guardarEdicionCaja" class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-[11px] font-bold text-on-surface-variant block mb-1">Nombre Terminal:</label>
                                            <input 
                                                type="text" 
                                                wire:model="formEditarCaja.nombre" 
                                                class="w-full h-10 rounded-xl border border-primary/40 bg-surface-container-lowest px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                                                required
                                            />
                                            @error('formEditarCaja.nombre') <span class="text-[11px] text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="text-[11px] font-bold text-on-surface-variant block mb-1">Código Único:</label>
                                            <input 
                                                type="text" 
                                                wire:model="formEditarCaja.codigo" 
                                                class="w-full h-10 rounded-xl border border-primary/40 bg-surface-container-lowest px-3 font-mono text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                                                required
                                            />
                                            @error('formEditarCaja.codigo') <span class="text-[11px] text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 pt-1">
                                        <button 
                                            type="button" 
                                            wire:click="cancelarEdicionCaja" 
                                            class="rounded-xl border border-surface-container-high bg-surface-container px-3 py-1.5 text-xs font-bold text-on-surface-variant hover:text-on-surface"
                                        >
                                            Cancelar
                                        </button>
                                        <button 
                                            type="submit" 
                                            class="rounded-xl bg-primary px-4 py-1.5 text-xs font-black text-on-primary shadow-sm hover:bg-primary-container"
                                        >
                                            ✓ Guardar Cambios
                                        </button>
                                    </div>
                                </form>
                            @else
                                <!-- MODO VISUALIZACIÓN -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0 w-3 h-3 rounded-full {{ $cajaItem->activa ? 'bg-secondary' : 'bg-surface-container-highest' }}" title="{{ $cajaItem->activa ? 'Terminal activa' : 'Terminal inactiva' }}"></div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-extrabold text-on-surface">{{ $cajaItem->nombre }}</span>
                                                <span class="font-mono text-[11px] font-bold px-2 py-0.5 rounded-md bg-surface-container text-on-surface-variant border border-surface-container-highest">
                                                    {{ $cajaItem->codigo }}
                                                </span>
                                                @if($cajaItem->activa)
                                                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-secondary-container/40 text-on-secondary-container border border-secondary/20">
                                                        Activa
                                                    </span>
                                                @else
                                                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant">
                                                        Inactiva
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 text-[11px] text-on-surface-variant mt-1">
                                                <span>{{ $cajaItem->turnos_count }} {{ $cajaItem->turnos_count === 1 ? 'turno registrado' : 'turnos registrados' }}</span>
                                                @if($cajaItem->turnos_count > 0)
                                                    <span>•</span>
                                                    <span class="text-amber-600 font-medium">Contiene auditoría contable</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 self-end sm:self-center">
                                        <!-- BOTÓN ACTIVAR / DESACTIVAR -->
                                        <button 
                                            wire:click="alternarEstadoCaja({{ $cajaItem->id }})" 
                                            class="inline-flex items-center gap-1 rounded-xl px-2.5 py-1.5 text-xs font-bold transition-all {{ $cajaItem->activa ? 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 border border-amber-500/20' : 'bg-secondary/10 text-secondary hover:bg-secondary/20 border border-secondary/20' }}"
                                            title="{{ $cajaItem->activa ? 'Desactivar esta terminal' : 'Activar esta terminal' }}"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">{{ $cajaItem->activa ? 'power_settings_new' : 'check_circle' }}</span>
                                            <span>{{ $cajaItem->activa ? 'Desactivar' : 'Activar' }}</span>
                                        </button>

                                        <!-- BOTÓN EDITAR -->
                                        <button 
                                            wire:click="iniciarEdicionCaja({{ $cajaItem->id }})" 
                                            class="inline-flex items-center gap-1 rounded-xl border border-surface-container-highest bg-surface-container px-2.5 py-1.5 text-xs font-bold text-on-surface hover:bg-surface-container-high transition-all"
                                            title="Editar nombre y código"
                                        >
                                            <span class="material-symbols-outlined text-[16px] text-primary">edit</span>
                                            <span>Editar</span>
                                        </button>

                                        <!-- BOTÓN ELIMINAR (SOLO ADMIN) -->
                                        @if(auth()->user()?->role?->slug === 'admin')
                                            @if($cajaItem->turnos_count === 0)
                                                <button 
                                                    wire:click="eliminarCaja({{ $cajaItem->id }})" 
                                                    wire:confirm="¿Seguro que deseas eliminar permanentemente la terminal {{ $cajaItem->nombre }}? Esta acción no se puede deshacer."
                                                    class="inline-flex items-center gap-1 rounded-xl bg-error/10 text-error hover:bg-error/20 border border-error/20 px-2.5 py-1.5 text-xs font-bold transition-all"
                                                    title="Eliminar terminal (sin turnos)"
                                                >
                                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                                    <span>Eliminar</span>
                                                </button>
                                            @else
                                                <button 
                                                    disabled
                                                    class="inline-flex items-center gap-1 rounded-xl bg-surface-container text-on-surface-variant/40 border border-surface-container-highest px-2.5 py-1.5 text-xs font-medium cursor-not-allowed opacity-60"
                                                    title="No se puede eliminar porque tiene turnos y ventas asociadas. Puedes desactivarla."
                                                >
                                                    <span class="material-symbols-outlined text-[16px]">lock</span>
                                                    <span>Eliminar</span>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-6 text-xs text-on-surface-variant">
                            No hay terminales configuradas en el sistema.
                        </div>
                    @endforelse
                </div>

                <div class="pt-3 border-t border-surface-container-high flex items-center justify-between">
                    <button 
                        type="button" 
                        wire:click="abrirModalNuevaCaja" 
                        class="inline-flex items-center gap-1.5 rounded-xl bg-secondary/15 text-secondary border border-secondary/30 px-3 py-2 text-xs font-bold hover:bg-secondary/25 transition-all"
                    >
                        <span class="material-symbols-outlined text-[16px]">add_box</span>
                        <span>+ Nueva Terminal</span>
                    </button>
                    <button 
                        type="button"
                        wire:click="$set('modalGestionTerminalesOpen', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container px-4 py-2 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: NUEVA TERMINAL DE CAJA -->
    @if($modalNuevaCajaOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-secondary-container/50 text-secondary flex items-center justify-center border border-secondary/30">
                            <span class="material-symbols-outlined text-[20px]">add_box</span>
                        </div>
                        <h3 class="text-base font-extrabold text-on-surface">Nueva Terminal de Caja</h3>
                    </div>
                    <button wire:click="$set('modalNuevaCajaOpen', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form wire:submit="guardarNuevaCaja" class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Nombre Descriptivo de la Caja:</label>
                        <input 
                            type="text" 
                            wire:model="formCaja.nombre" 
                            placeholder="Ej. Caja 2 Barra & Coctelería"
                            class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                            required
                        />
                        @error('formCaja.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Código de Terminal (Único):</label>
                        <input 
                            type="text" 
                            wire:model="formCaja.codigo" 
                            placeholder="Ej. CAJA-02"
                            class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 font-mono text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                            required
                        />
                        @error('formCaja.codigo') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-3 border-t border-surface-container-high grid grid-cols-2 gap-2">
                        <button 
                            type="button"
                            wire:click="$set('modalNuevaCajaOpen', false)" 
                            class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            class="rounded-xl bg-primary py-2.5 text-xs font-black text-on-primary shadow-md hover:bg-primary-container"
                        >
                            ✓ Crear Terminal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
