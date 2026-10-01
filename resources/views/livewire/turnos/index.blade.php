<?php

use App\Models\PlantillaTurno;
use App\Models\Zona;
use App\Services\TurnoSemanalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public int $semanaIso;

    public int $anio;

    public ?int $programacionId = null;

    public string $estado = 'borrador';

    public int $diaSeleccionadoIso = 1;

    public string $vistaActiva = 'pizarra'; // 'pizarra', 'semanal', 'proyeccion', 'reglas'

    public array $reglasForm = [
        'impedir_zona_repetida' => true,
        'longitud_ciclo' => 3,
        'dias_alta_demanda' => [5, 6, 7],
        'semanas_ciclo_descanso' => 4,
    ];

    public function mount(): void
    {
        $this->semanaIso = (int) Carbon::today()->isoWeek;
        $this->anio = (int) Carbon::today()->isoWeekYear;
        $this->diaSeleccionadoIso = (int) Carbon::today()->dayOfWeekIso;
        $this->cargarProgramacion();
        $this->cargarReglas();
    }

    public function seleccionarDia(int $diaIso): void
    {
        $this->diaSeleccionadoIso = max(1, min(7, $diaIso));
    }

    public function cambiarVista(string $vista): void
    {
        if (in_array($vista, ['pizarra', 'semanal', 'proyeccion', 'reglas'], true)) {
            $this->vistaActiva = $vista;
        }
    }

    public function semanaAnterior(): void
    {
        $fecha = Carbon::now()->setISODate($this->anio, $this->semanaIso)->subWeek();
        $this->semanaIso = (int) $fecha->isoWeek;
        $this->anio = (int) $fecha->isoWeekYear;
        $this->cargarProgramacion();
    }

    public function semanaSiguiente(): void
    {
        $fecha = Carbon::now()->setISODate($this->anio, $this->semanaIso)->addWeek();
        $this->semanaIso = (int) $fecha->isoWeek;
        $this->anio = (int) $fecha->isoWeekYear;
        $this->cargarProgramacion();
    }

    public function autoProgramar(): void
    {
        $this->autorizar();
        $svc = app(TurnoSemanalService::class);
        $svc->autoProgramar($this->programacionId, Auth::user());
        $this->cargarProgramacion();
        session()->flash('status', 'Programación semanal generada de forma equitativa.');
        $this->dispatch('notificacion', ['mensaje' => 'Turnos auto-programados', 'tipo' => 'success']);
    }

    public function copiarAnterior(): void
    {
        $this->autorizar();
        $svc = app(TurnoSemanalService::class);
        $destino = $svc->copiarSemanaAnterior(
            Auth::user()->sucursal_id,
            $this->semanaIso,
            $this->anio,
            Auth::user()
        );
        $this->programacionId = $destino->id;
        $this->estado = $destino->estado;
        session()->flash('status', 'Semana anterior copiada correctamente.');
        $this->dispatch('notificacion', ['mensaje' => 'Semana copiada', 'tipo' => 'success']);
    }

    public function publicar(): void
    {
        $this->autorizar();
        $svc = app(TurnoSemanalService::class);
        $publicada = $svc->publicarSemana($this->programacionId, Auth::user());
        $this->estado = $publicada->estado;
        session()->flash('status', 'Horarios publicados y notificados al equipo.');
        $this->dispatch('notificacion', ['mensaje' => 'Horarios publicados', 'tipo' => 'success']);
    }

    public function alternarDescanso(int $turnoId): void
    {
        $this->autorizar();
        $svc = app(TurnoSemanalService::class);
        $turno = \App\Models\TurnoMeseroSemana::findOrFail($turnoId);
        $svc->actualizarCelda($turnoId, ['es_descanso' => ! $turno->es_descanso], Auth::user());
    }

    public function cambiarZona(int $turnoId, ?int $zonaId): void
    {
        $this->autorizar();

        $this->validate([
            'zonaId' => ['nullable', 'integer', 'exists:zonas,id'],
        ], [], ['zonaId' => 'zona']);

        $svc = app(TurnoSemanalService::class);
        $svc->actualizarCelda($turnoId, ['zona_id' => $zonaId, 'es_descanso' => false], Auth::user());
    }

    public function with(): array
    {
        $sucursalId = Auth::user()?->sucursal_id;

        // Guard: si no hay programación aún, retornar estado vacío seguro
        if (! $this->programacionId) {
            return [
                'dias'             => [],
                'filas'            => [],
                'programacion'     => null,
                'zonas'            => collect(),
                'plantillas'       => collect(),
                'proyeccion'       => [],
                'scoreEquidad'     => 100,
                'fechaSeleccionada' => \Carbon\Carbon::now(),
                'turnosPorZona'    => [],
                'turnosDescanso'   => [],
                'turnosSinZona'    => [],
            ];
        }

        $svc = app(TurnoSemanalService::class);
        $matriz = $svc->matrizSemanal($this->programacionId);
        $proyeccion = $sucursalId ? $svc->proyeccionRotacion($sucursalId, $this->semanaIso, $this->anio, 4) : [];
        $zonas = Zona::where('sucursal_id', $sucursalId)->where('activa', true)->orderBy('orden')->get();

        $dias = $matriz['dias'];
        $diaIndex = max(0, min(6, $this->diaSeleccionadoIso - 1));
        $fechaSeleccionada = $dias[$diaIndex] ?? Carbon::now();
        $fechaStr = $fechaSeleccionada->toDateString();

        $turnosPorZona = [];
        foreach ($zonas as $zona) {
            $turnosPorZona[$zona->id] = [];
        }
        $turnosDescanso = [];
        $turnosSinZona = [];

        foreach ($matriz['filas'] as $fila) {
            $celda = $fila['celdas'][$fechaStr] ?? null;
            if ($celda) {
                $item = [
                    'celda' => $celda,
                    'mesero' => $fila['mesero'],
                ];
                if ($celda->es_descanso) {
                    $turnosDescanso[] = $item;
                } elseif ($celda->zona_id && isset($turnosPorZona[$celda->zona_id])) {
                    $turnosPorZona[$celda->zona_id][] = $item;
                } else {
                    $turnosSinZona[] = $item;
                }
            }
        }

        return [
            'dias' => $dias,
            'filas' => $matriz['filas'],
            'programacion' => $matriz['programacion'],
            'zonas' => $zonas,
            'plantillas' => PlantillaTurno::where('sucursal_id', $sucursalId)->where('activo', true)->orderBy('id')->get(),
            'proyeccion' => $proyeccion,
            'scoreEquidad' => $this->calcularScoreEquidad($proyeccion),
            'fechaSeleccionada' => $fechaSeleccionada,
            'turnosPorZona' => $turnosPorZona,
            'turnosDescanso' => $turnosDescanso,
            'turnosSinZona' => $turnosSinZona,
        ];
    }

    public function calcularScoreEquidad(array $proyeccion): int
    {
        if (empty($proyeccion)) {
            return 100;
        }

        $cuentasAlta = [];
        foreach ($proyeccion as $item) {
            $altas = 0;
            foreach ($item['semanas'] as $sem) {
                if (($sem['descanso']['nivel'] ?? null) === 'alta') {
                    $altas++;
                }
            }
            $cuentasAlta[] = $altas;
        }

        if (empty($cuentasAlta)) {
            return 98;
        }

        $diferencia = max($cuentasAlta) - min($cuentasAlta);

        if ($diferencia <= 1) {
            return 98;
        } elseif ($diferencia <= 2) {
            return 91;
        }

        return max(72, 100 - ($diferencia * 10));
    }

    public function guardarReglas(): void
    {
        $this->autorizar();

        $this->validate([
            'reglasForm.impedir_zona_repetida' => ['required', 'boolean'],
            'reglasForm.longitud_ciclo' => ['required', 'integer', 'min:2', 'max:8'],
            'reglasForm.dias_alta_demanda' => ['required', 'array', 'min:1'],
            'reglasForm.dias_alta_demanda.*' => ['integer', 'min:1', 'max:7'],
            'reglasForm.semanas_ciclo_descanso' => ['required', 'integer', 'min:2', 'max:8'],
        ]);

        $svc = app(TurnoSemanalService::class);
        $reglas = $svc->guardarReglasRotacion(Auth::user()->sucursal_id, [
            'impedir_zona_repetida' => (bool) $this->reglasForm['impedir_zona_repetida'],
            'longitud_ciclo' => (int) $this->reglasForm['longitud_ciclo'],
            'dias_alta_demanda' => array_map('intval', $this->reglasForm['dias_alta_demanda']),
            'semanas_ciclo_descanso' => (int) $this->reglasForm['semanas_ciclo_descanso'],
        ], Auth::user());

        $this->cargarReglas($reglas);
        session()->flash('status', 'Reglas de rotación guardadas.');
        $this->dispatch('notificacion', ['mensaje' => 'Reglas de rotación guardadas', 'tipo' => 'success']);
    }

    public function equilibrarDescansos(): void
    {
        $this->autorizar();
        $svc = app(TurnoSemanalService::class);
        $swaps = $svc->equilibrarDescansos($this->programacionId, Auth::user());
        session()->flash('status', $swaps > 0 ? "Descansos equilibrados ({$swaps} ajustes)." : 'Los descansos ya están equilibrados.');
        $this->dispatch('notificacion', ['mensaje' => 'Descansos equilibrados', 'tipo' => 'success']);
    }

    protected function cargarProgramacion(): void
    {
        $svc = app(TurnoSemanalService::class);
        $prog = $svc->obtenerOCrearSemana(
            Auth::user()->sucursal_id,
            $this->semanaIso,
            $this->anio,
            Auth::user()
        );
        $this->programacionId = $prog->id;
        $this->estado = $prog->estado;
    }

    protected function cargarReglas(?array $reglas = null): void
    {
        $reglas = $reglas ?? app(TurnoSemanalService::class)->obtenerReglasRotacion(Auth::user()->sucursal_id);
        $this->reglasForm = [
            'impedir_zona_repetida' => (bool) $reglas['impedir_zona_repetida'],
            'longitud_ciclo' => (int) $reglas['longitud_ciclo'],
            'dias_alta_demanda' => array_map('intval', $reglas['dias_alta_demanda']),
            'semanas_ciclo_descanso' => (int) $reglas['semanas_ciclo_descanso'],
        ];
    }

    protected function autorizar(): void
    {
        abort_unless(
            Auth::check() && (Auth::user()->isAdmin() || Auth::user()->isGerente()),
            403
        );
    }
}; ?>

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-primary/15 border border-primary/25 flex items-center justify-center text-primary shadow-sm shrink-0">
                <span class="material-symbols-outlined text-[24px]">calendar_month</span>
            </div>
            <div>
                <h1 class="text-xl font-black tracking-tight text-on-surface">Programación de Turnos</h1>
                <p class="text-xs text-on-surface-variant font-medium mt-0.5">Pizarra de salón y rotación equitativa · Semana ISO {{ $semanaIso }} / {{ $anio }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($estado === 'publicado')
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3.5 py-1.5 text-xs font-black text-emerald-400 border border-emerald-500/30 shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    Publicado
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-3.5 py-1.5 text-xs font-black text-amber-400 border border-amber-500/30">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    Borrador
                </span>
            @endif
        </div>
    </div>
</x-slot>

<div class="space-y-6">
    <!-- Barra superior de acento con gradiente sutil -->
    <div class="h-1.5 w-full rounded-full bg-gradient-to-r from-primary via-secondary to-amber-500 shadow-xs"></div>

    <!-- Mensajes de estado -->
    @if (session('status'))
        <div class="rounded-2xl border border-secondary/40 bg-secondary/10 p-4 text-xs font-bold text-secondary flex items-center gap-2.5 animate-fade-in shadow-xs">
            <span class="material-symbols-outlined text-[20px] shrink-0">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Barra de Navegación & Botones de Acción Principales -->
    <div class="flex flex-wrap items-center justify-between gap-3 p-2.5 rounded-3xl bg-surface-container-low border border-outline-variant/20 shadow-sm animate-fade-in">
        <!-- Navegador de Semana -->
        <div class="flex items-center gap-1.5 bg-surface-container-lowest rounded-2xl p-1 border border-outline-variant/15 shadow-xs">
            <button
                type="button"
                wire:click="semanaAnterior"
                class="flex items-center gap-1 rounded-xl px-3 py-2 text-xs font-bold text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-all cursor-pointer min-h-[40px]"
                title="Semana anterior"
            >
                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                <span class="hidden sm:inline">Anterior</span>
            </button>
            <div class="px-3.5 py-1.5 text-xs font-black text-on-surface bg-surface-container-high/60 rounded-xl border border-outline-variant/15">
                S{{ $semanaIso }} · {{ $anio }}
            </div>
            <button
                type="button"
                wire:click="semanaSiguiente"
                class="flex items-center gap-1 rounded-xl px-3 py-2 text-xs font-bold text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-all cursor-pointer min-h-[40px]"
                title="Semana siguiente"
            >
                <span class="hidden sm:inline">Siguiente</span>
                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
            </button>
        </div>

        <!-- Grupo de Botones de Acción Primarios -->
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="copiarAnterior"
                class="flex items-center gap-2 rounded-2xl bg-surface-container-high border border-outline-variant/30 px-3.5 py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-highest active:scale-95 transition-all cursor-pointer min-h-[44px] shadow-xs"
                title="Copiar turnos de la semana pasada"
            >
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">content_copy</span>
                <span class="hidden md:inline">Copiar Anterior</span>
            </button>

            <button
                type="button"
                wire:click="equilibrarDescansos"
                class="flex items-center gap-2 rounded-2xl bg-surface-container-high border border-outline-variant/30 px-3.5 py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-highest active:scale-95 transition-all cursor-pointer min-h-[44px] shadow-xs"
                title="Equilibrar equidad de descansos"
            >
                <span class="material-symbols-outlined text-[18px] text-amber-400">balance</span>
                <span>Equilibrar</span>
            </button>

            <!-- Botón 1: AUTOASIGNACIÓN DE TURNOS CON IA (Destacado) -->
            <button
                type="button"
                wire:click="autoProgramar"
                class="flex items-center gap-2 rounded-2xl bg-gradient-to-r from-primary to-primary/80 hover:from-primary/90 hover:to-primary text-on-primary px-4 sm:px-5 py-2.5 text-xs font-black shadow-md shadow-primary/20 active:scale-95 transition-all cursor-pointer min-h-[44px]"
                title="Asignación automática inteligente de turnos"
            >
                <span class="material-symbols-outlined text-[18px] animate-pulse">bolt</span>
                <span>⚡ Auto-Programar con IA</span>
            </button>

            <!-- Botón 2: PUBLICACIÓN DE HORARIOS (Destacado) -->
            <button
                type="button"
                wire:click="publicar"
                wire:confirm="¿Deseas publicar los horarios oficiales de la semana {{ $semanaIso }}? Se notificará al personal."
                class="flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-600 text-white px-4 sm:px-5 py-2.5 text-xs font-black shadow-md shadow-emerald-950/30 active:scale-95 transition-all cursor-pointer min-h-[44px]"
                title="Publicar turnos oficiales para el personal"
            >
                <span class="material-symbols-outlined text-[18px]">publish</span>
                <span>✅ Publicar Horarios</span>
            </button>
        </div>
    </div>

    <!-- Pestañas de Modo de Vista (Solución al agobio visual: 1 vista enfocada a la vez) -->
    <div class="flex items-center gap-2 border-b border-outline-variant/20 pb-1 overflow-x-auto">
        <button
            type="button"
            wire:click="cambiarVista('pizarra')"
            class="flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-black transition-all cursor-pointer whitespace-nowrap {{ $vistaActiva === 'pizarra' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}"
        >
            <span class="material-symbols-outlined text-[18px]">view_kanban</span>
            <span>🍽️ Pizarra Diaria por Zonas</span>
        </button>

        <button
            type="button"
            wire:click="cambiarVista('semanal')"
            class="flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-black transition-all cursor-pointer whitespace-nowrap {{ $vistaActiva === 'semanal' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}"
        >
            <span class="material-symbols-outlined text-[18px]">calendar_view_week</span>
            <span>📅 Malla Semanal (Lun - Dom)</span>
        </button>

        <button
            type="button"
            wire:click="cambiarVista('proyeccion')"
            class="flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-black transition-all cursor-pointer whitespace-nowrap {{ $vistaActiva === 'proyeccion' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}"
        >
            <span class="material-symbols-outlined text-[18px]">preview</span>
            <span>Proyección de Descansos</span>
        </button>

        <button
            type="button"
            wire:click="cambiarVista('reglas')"
            class="flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-black transition-all cursor-pointer whitespace-nowrap {{ $vistaActiva === 'reglas' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}"
        >
            <span class="material-symbols-outlined text-[18px]">settings</span>
            <span>Reglas de Rotación</span>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- MODO 1: PIZARRA DIARIA POR ZONAS (ALTERNATIVA 1 - PRINCIPAL Y ERGONÓMICA) -->
    <!-- ========================================================================= -->
    @if ($vistaActiva === 'pizarra')
        <div class="space-y-6 animate-fade-in">
            <!-- Pastillero Horizontal de Días (Lunes a Domingo) -->
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-3 shadow-sm">
                <div class="flex items-center justify-between px-2 pb-2.5 border-b border-outline-variant/15 text-xs">
                    <span class="font-extrabold text-on-surface flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-primary text-[18px]">today</span>
                        Selecciona el día a gestionar:
                    </span>
                    <span class="text-[11px] font-bold text-on-surface-variant">
                        Toca un día para ver y reasignar el personal del salón
                    </span>
                </div>
                <div class="grid grid-cols-7 gap-2 pt-2.5">
                    @foreach ($dias as $dia)
                        @php
                            $diaIso = (int) $dia->dayOfWeekIso;
                            $esSeleccionado = $diaSeleccionadoIso === $diaIso;
                            $esAlta = in_array($diaIso, $reglasForm['dias_alta_demanda'] ?? []);
                            $esHoy = $dia->isToday();
                            $nombreCorto = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][$diaIso - 1];
                        @endphp
                        <button
                            type="button"
                            wire:click="seleccionarDia({{ $diaIso }})"
                            class="p-2.5 sm:p-3 rounded-2xl border transition-all text-center cursor-pointer min-h-[58px] {{ $esSeleccionado ? 'bg-primary/20 border-primary text-on-surface shadow-md ring-2 ring-primary/40' : 'bg-surface-container-low border-outline-variant/20 text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                        >
                            <div class="flex items-center justify-center gap-1">
                                <span class="text-xs font-black uppercase tracking-wider">{{ $nombreCorto }}</span>
                                @if ($esAlta)
                                    <span class="text-[11px]" title="Día de alta afluencia">🔥</span>
                                @endif
                            </div>
                            <div class="text-sm font-black mt-0.5 {{ $esSeleccionado ? 'text-primary' : 'text-on-surface' }}">
                                {{ $dia->format('d/m') }}
                            </div>
                            @if ($esHoy)
                                <span class="inline-block mt-1 text-[9px] font-extrabold px-1.5 py-0.2 rounded-full bg-secondary/20 text-secondary border border-secondary/30">Hoy</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Cabecera del Día Seleccionado & Métricas de Dotación -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-1">
                @php
                    $diasNombresCompletos = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                    $diaNombre = $diasNombresCompletos[$diaSeleccionadoIso] ?? 'Día';
                    $esDiaAlta = in_array($diaSeleccionadoIso, $reglasForm['dias_alta_demanda'] ?? []);
                    $totalEnServicio = collect($turnosPorZona)->flatten(1)->count() + count($turnosSinZona);
                    $totalDescanso = count($turnosDescanso);
                @endphp
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-black text-on-surface flex items-center gap-2">
                        <span>{{ $diaNombre }}, {{ $fechaSeleccionada->format('d/m/Y') }}</span>
                    </h2>
                    @if ($esDiaAlta)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-500/15 text-rose-300 border border-rose-500/40 text-[11px] font-black shadow-xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                            🔥 Alta Demanda
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/40 text-[11px] font-black">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Demanda Regular
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-4 text-xs font-bold text-on-surface-variant">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                        <span>{{ $totalEnServicio }} en servicio</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-surface-container-highest border border-outline-variant"></span>
                        <span>{{ $totalDescanso }} en descanso</span>
                    </span>
                </div>
            </div>

            <!-- Columnas de Zonas Físicas de la Pizarra de Salón -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
                <!-- Columna por cada Zona activa del restaurante -->
                @foreach ($zonas as $zona)
                    @php
                        $turnosZona = $turnosPorZona[$zona->id] ?? [];
                        $tinte = \App\Models\Zona::PALETA[$zona->color ?? ''] ?? ['tinte' => 'bg-primary-container/30', 'punto' => 'bg-primary'];
                    @endphp
                    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-3 shadow-sm min-h-[320px] flex flex-col justify-between">
                        <div class="space-y-3">
                            <!-- Encabezado de la Zona -->
                            <div class="flex items-center justify-between pb-3 border-b border-outline-variant/15">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full {{ $tinte['punto'] }} shrink-0"></span>
                                    <h3 class="text-sm font-black text-on-surface">{{ $zona->nombre }}</h3>
                                </div>
                                <span class="text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-surface-container-high text-on-surface border border-outline-variant/20">
                                    {{ count($turnosZona) }}
                                </span>
                            </div>

                            <!-- Lista de Meseros en esta Zona -->
                            <div class="space-y-2.5">
                                @forelse ($turnosZona as $item)
                                    <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-low/80 p-3 space-y-2 hover:border-outline-variant/50 transition-all shadow-2xs">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-9 h-9 rounded-xl bg-primary/20 text-primary border border-primary/30 flex items-center justify-center font-black text-xs shrink-0">
                                                {{ strtoupper(substr($item['mesero']->name, 0, 2)) }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <span class="block text-xs font-bold text-on-surface truncate">{{ $item['mesero']->name }}</span>
                                                <span class="block text-[10px] font-medium text-on-surface-variant">
                                                    {{ $item['celda']->plantilla?->nombre ?? 'Jornada Continua' }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Acciones Rápidas: Mover a otra zona o mandar a descanso -->
                                        <div class="flex items-center gap-1.5 pt-1 border-t border-outline-variant/10">
                                            <select
                                                wire:change="cambiarZona({{ $item['celda']->id }}, $event.target.value)"
                                                class="flex-1 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-2 py-1 text-[11px] text-on-surface focus:border-primary focus:ring-0 min-h-[34px] cursor-pointer"
                                                title="Mover a otra zona"
                                            >
                                                @foreach ($zonas as $zOption)
                                                    <option value="{{ $zOption->id }}" @selected($item['celda']->zona_id === $zOption->id)>
                                                        {{ $zOption->nombre }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button
                                                type="button"
                                                wire:click="alternarDescanso({{ $item['celda']->id }})"
                                                title="Mandar a descanso hoy"
                                                class="px-2.5 py-1 rounded-xl bg-surface-container border border-outline-variant/30 hover:border-rose-500/50 hover:bg-rose-500/10 text-xs font-bold text-on-surface-variant hover:text-rose-300 transition-all cursor-pointer min-h-[34px] flex items-center gap-1"
                                            >
                                                <span>😴</span>
                                                <span class="text-[10px] hidden sm:inline">Libre</span>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-8 text-center text-xs text-on-surface-variant/60 font-medium border border-dashed border-outline-variant/20 rounded-2xl">
                                        Sin personal en esta zona
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="pt-2 text-[10px] text-on-surface-variant/70 text-center font-medium">
                            Usa el selector para transferir meseros
                        </div>
                    </div>
                @endforeach

                <!-- Columna: Descansos / Día Libre del Día -->
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-3 shadow-sm min-h-[320px] flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between pb-3 border-b border-outline-variant/15">
                            <div class="flex items-center gap-2">
                                <span class="text-base">😴</span>
                                <h3 class="text-sm font-black text-on-surface">Descansos / Libres</h3>
                            </div>
                            <span class="text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-surface-container-high text-on-surface border border-outline-variant/20">
                                {{ count($turnosDescanso) }}
                            </span>
                        </div>

                        <!-- Lista de Meseros que descansan este día -->
                        <div class="space-y-2.5">
                            @forelse ($turnosDescanso as $item)
                                <div class="rounded-2xl border border-outline-variant/25 bg-surface-container-low/50 p-3 space-y-2 hover:border-outline-variant/50 transition-all shadow-2xs">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-surface-container text-on-surface-variant border border-outline-variant/30 flex items-center justify-center font-black text-xs shrink-0">
                                            {{ strtoupper(substr($item['mesero']->name, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="block text-xs font-bold text-on-surface truncate">{{ $item['mesero']->name }}</span>
                                            <span class="block text-[10px] font-bold text-secondary">
                                                Día de Descanso Libre
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Acción rápida: Asignar a zona y quitar descanso -->
                                    <div class="flex items-center gap-1.5 pt-1 border-t border-outline-variant/10">
                                        <select
                                            wire:change="cambiarZona({{ $item['celda']->id }}, $event.target.value)"
                                            class="flex-1 rounded-xl border border-secondary/40 bg-surface-container-lowest px-2 py-1 text-[11px] font-bold text-secondary focus:border-secondary focus:ring-0 min-h-[34px] cursor-pointer"
                                            title="Activar turno asignando una zona"
                                        >
                                            <option value="">➕ Activar en Zona...</option>
                                            @foreach ($zonas as $zOption)
                                                <option value="{{ $zOption->id }}">{{ $zOption->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @empty
                                <div class="py-8 text-center text-xs text-on-surface-variant/60 font-medium border border-dashed border-outline-variant/20 rounded-2xl">
                                    Nadie descansa en esta fecha
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="pt-2 text-[10px] text-on-surface-variant/70 text-center font-medium">
                        Asigna una zona para reactivar a un colaborador
                    </div>
                </div>
            </div>

            <!-- Aviso si la semana está vacía -->
            @if ($totalEnServicio === 0 && $totalDescanso === 0)
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-8 text-center shadow-sm">
                    <div class="w-14 h-14 rounded-3xl bg-primary/10 border border-primary/20 text-primary mx-auto flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-[32px]">calendar_add_on</span>
                    </div>
                    <p class="text-base font-extrabold text-on-surface">Semana vacía, sin turnos generados</p>
                    <p class="text-xs text-on-surface-variant mt-1 max-w-sm mx-auto">Pulsa el botón de auto-programación para distribuir automáticamente las zonas y descansos.</p>
                    <button
                        type="button"
                        wire:click="autoProgramar"
                        class="mt-4 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-xs font-black text-on-primary shadow-md hover:bg-primary/90 active:scale-95 transition cursor-pointer min-h-[44px]"
                    >
                        <span class="material-symbols-outlined text-[18px]">bolt</span>
                        <span>⚡ Auto-Programar con IA</span>
                    </button>
                </div>
            @endif
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- MODO 2: MALLA SEMANAL COMPLETA (LUNES A DOMINGO)                          -->
    <!-- ========================================================================= -->
    @if ($vistaActiva === 'semanal')
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest shadow-sm animate-fade-in overflow-hidden">
            <div class="border-b border-outline-variant/15 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-primary text-[20px]">grid_view</span>
                        Matriz de turnos por zona
                    </h2>
                    <p class="text-xs text-on-surface-variant font-medium mt-0.5">Asignación táctica para la semana ISO {{ $semanaIso }} · Pulsa una celda para alternar descanso o reasignar zona.</p>
                </div>
                @if ($estado !== 'publicado')
                    <button
                        type="button"
                        wire:click="autoProgramar"
                        class="self-start sm:self-auto inline-flex items-center gap-1.5 rounded-xl bg-primary/10 border border-primary/30 px-3.5 py-1.5 text-xs font-bold text-primary hover:bg-primary/20 transition cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[16px]">bolt</span>
                        <span>Re-generar matriz</span>
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr class="border-b border-outline-variant/15 bg-surface-container-low/50">
                            <th class="text-left px-6 py-3.5 text-xs font-black uppercase tracking-wider text-on-surface-variant sticky left-0 bg-surface-container-low z-10">Colaborador</th>
                            @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $i => $nombreDia)
                                <th class="text-center px-2.5 py-3.5 min-w-[110px]">
                                    <span class="block text-sm font-black text-on-surface">{{ $nombreDia }}</span>
                                    <span class="inline-block mt-0.5 rounded-full bg-surface-container-high px-2 py-0.5 text-[10px] font-bold text-on-surface-variant">{{ $dias[$i]->format('d/m') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10">
                        @forelse ($filas as $fila)
                            @php
                                $celdas = collect($fila['celdas'])->filter();
                                $numDescansos = $celdas->where('es_descanso', true)->count();
                                $numTurnos = $celdas->where('es_descanso', false)->count();
                            @endphp
                            <tr class="hover:bg-surface-container-low/40 transition-colors">
                                <td class="px-6 py-3.5 sticky left-0 bg-surface-container-lowest z-10">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-primary/15 border border-primary/25 flex items-center justify-center text-primary font-black text-xs shrink-0">
                                            {{ strtoupper(substr($fila['mesero']->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <span class="block text-sm font-bold text-on-surface">{{ $fila['mesero']->name }}</span>
                                            <span class="block text-[10px] font-medium text-on-surface-variant mt-0.5">{{ $numTurnos }} turnos · {{ $numDescansos }} descansos</span>
                                        </div>
                                    </div>
                                </td>
                                @foreach ($dias as $dia)
                                    @php $celda = $fila['celdas'][$dia->toDateString()] ?? null; @endphp
                                    <td class="text-center px-2 py-2 align-top">
                                        @if ($celda === null)
                                            <span class="inline-block px-2 py-3 text-sm text-on-surface-variant/40">—</span>
                                        @elseif ($celda->es_descanso)
                                            <button
                                                type="button"
                                                wire:click="alternarDescanso({{ $celda->id }})"
                                                title="Descanso programado. Clic para reactivar turno."
                                                class="w-full rounded-2xl bg-surface-container border border-outline-variant/30 hover:border-primary/50 px-2 py-3 text-sm cursor-pointer min-h-[50px] shadow-2xs active:scale-95 transition-all group"
                                            >
                                                <span class="text-base group-hover:scale-110 inline-block transition-transform">😴</span>
                                                <span class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant mt-0.5">Descanso</span>
                                            </button>
                                        @else
                                            @php $tinte = \App\Models\Zona::PALETA[$celda->zona?->color ?? ''] ?? ['tinte' => 'bg-primary-container/40', 'punto' => 'bg-primary']; @endphp
                                            <div class="rounded-2xl border border-outline-variant/20 {{ $tinte['tinte'] }} p-2 space-y-1.5 shadow-2xs">
                                                <span class="flex items-center justify-center gap-1.5 text-xs font-bold text-on-surface">
                                                    <span class="w-2 h-2 rounded-full {{ $tinte['punto'] }} shrink-0"></span>
                                                    <span class="truncate">{{ $celda->zona?->nombre ?? 'Sin zona' }}</span>
                                                </span>
                                                @if ($celda->plantilla)
                                                    <span class="block text-[10px] font-medium text-on-surface-variant">{{ $celda->plantilla->nombre }}</span>
                                                @endif
                                                <div class="flex items-center justify-center gap-1 pt-0.5">
                                                    <select wire:change="cambiarZona({{ $celda->id }}, $event.target.value)" class="max-w-full rounded-lg border border-outline-variant/30 bg-surface-container-lowest px-1.5 py-1.5 text-[11px] text-on-surface focus:border-primary focus:ring-0 min-h-[40px]" aria-label="Cambiar zona de {{ $fila['mesero']->name }}">
                                                        @foreach ($zonas as $zona)
                                                            <option value="{{ $zona->id }}" @selected($celda->zona_id === $zona->id)>{{ $zona->nombre }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button
                                                        type="button"
                                                        wire:click="alternarDescanso({{ $celda->id }})"
                                                        title="Marcar día como descanso"
                                                        class="rounded-lg px-2 py-1.5 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high cursor-pointer min-h-[40px] min-w-[36px] active:scale-95 transition"
                                                        aria-label="Marcar descanso"
                                                    >
                                                        <span class="text-sm">😴</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-xs text-on-surface-variant">
                                    Semana vacía, sin meseros activos configurados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- MODO 3: PROYECCIÓN DE DESCANSOS (4 SEMANAS)                               -->
    <!-- ========================================================================= -->
    @if ($vistaActiva === 'proyeccion')
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest shadow-sm animate-fade-in overflow-hidden">
            <div class="border-b border-outline-variant/15 px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-primary text-[20px]">preview</span>
                        Proyección de Descansos — 4 semanas
                    </h2>
                    <p class="text-xs text-on-surface-variant font-medium mt-0.5">Rotación de zonas y descansos proyectados por nivel de demanda.</p>
                </div>
                <!-- Leyenda de Demanda -->
                <div class="flex items-center gap-3 text-xs font-bold text-on-surface-variant bg-surface-container-low px-3.5 py-1.5 rounded-full border border-outline-variant/15">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Baja</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Media</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-400"></span> Alta demanda</span>
                </div>
            </div>

            <div class="overflow-x-auto p-4 sm:p-6">
                <div class="min-w-[800px] space-y-3">
                    <!-- Encabezados de Semanas -->
                    <div class="grid grid-cols-12 gap-3 px-4 py-2 text-xs font-black uppercase tracking-wider text-on-surface-variant">
                        <div class="col-span-4">Colaborador</div>
                        @foreach ((collect($proyeccion)->first()['semanas'] ?? []) as $semHeader)
                            <div class="col-span-2 text-center bg-surface-container-low py-1.5 rounded-xl border border-outline-variant/15 text-on-surface font-extrabold">
                                Semana {{ $semHeader['semana_iso'] }}
                            </div>
                        @endforeach
                    </div>

                    <!-- Filas de Colaboradores -->
                    @forelse ($proyeccion as $item)
                        <div class="grid grid-cols-12 gap-3 p-3 rounded-2xl bg-surface-container-low/50 border border-outline-variant/15 hover:border-outline-variant/35 hover:bg-surface-container-low transition-all items-center">
                            <div class="col-span-4 flex items-center gap-3 pr-2">
                                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-primary/25 to-secondary/25 border border-primary/30 flex items-center justify-center text-primary font-black text-xs shrink-0 shadow-xs">
                                    {{ strtoupper(substr($item['mesero']->name, 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <span class="block text-xs font-extrabold text-on-surface truncate">{{ $item['mesero']->name }}</span>
                                    <span class="block text-[10px] font-semibold text-on-surface-variant mt-0.5">Mesero de Sala</span>
                                </div>
                            </div>

                            @foreach ($item['semanas'] as $sem)
                                @php
                                    $diasCortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
                                    $zonasAsignadas = collect($sem['zonas'] ?? [])->filter();
                                    $zonaPrincipal = $zonasAsignadas->first();
                                    $descanso = $sem['descanso'];
                                @endphp
                                <div class="col-span-2 rounded-2xl bg-surface-container-lowest border border-outline-variant/20 p-2.5 space-y-1.5 shadow-2xs">
                                    <div class="flex items-center justify-between text-[11px] font-medium text-on-surface-variant">
                                        <span class="text-[9px] uppercase tracking-wider font-extrabold text-on-surface-variant/70">Zona:</span>
                                        <span class="inline-flex items-center gap-1 font-bold text-on-surface truncate max-w-[100px]" title="{{ $zonaPrincipal ?: 'Rotativa' }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary shrink-0"></span>
                                            <span class="truncate">{{ $zonaPrincipal ?: 'Rotativa' }}</span>
                                        </span>
                                    </div>

                                    @if ($descanso)
                                        @php
                                            $nivel = $descanso['nivel'] ?? 'media';
                                            $estiloPildora = match ($nivel) {
                                                'alta' => 'bg-rose-500/15 border-rose-500/40 text-rose-300',
                                                'media' => 'bg-amber-500/15 border-amber-500/40 text-amber-300',
                                                default => 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300',
                                            };
                                            $puntoColor = match ($nivel) {
                                                'alta' => 'bg-rose-400',
                                                'media' => 'bg-amber-400',
                                                default => 'bg-emerald-400',
                                            };
                                            $textoDemanda = match ($nivel) {
                                                'alta' => 'Alta',
                                                'media' => 'Media',
                                                default => 'Baja',
                                            };
                                        @endphp
                                        <div class="inline-flex items-center justify-between w-full rounded-xl border px-2 py-1 text-xs font-black {{ $estiloPildora }}">
                                            <span class="flex items-center gap-1">
                                                <span>😴</span>
                                                <span>{{ $diasCortos[$descanso['dia_iso']] ?? '' }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $puntoColor }}"></span>
                                                <span class="text-[9px] uppercase tracking-wider font-extrabold">{{ $textoDemanda }}</span>
                                            </span>
                                        </div>
                                    @else
                                        <div class="text-center py-1 text-[10px] text-on-surface-variant/50 font-bold">
                                            — Sin descanso —
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="py-10 text-center rounded-2xl bg-surface-container-low border border-outline-variant/15 text-xs text-on-surface-variant font-medium">
                            Sin meseros activos para proyectar en esta sucursal.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- MODO 4: REGLAS DE ROTACIÓN & EQUIDAD (CONFIGURACIÓN)                      -->
    <!-- ========================================================================= -->
    @if ($vistaActiva === 'reglas')
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest shadow-sm animate-fade-in overflow-hidden">
            <div class="border-b border-outline-variant/15 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-primary text-[20px]">settings</span>
                        Reglas de Rotación
                    </h2>
                    <p class="text-xs text-on-surface-variant font-medium mt-0.5">Control de equidad algorítmica: optimiza descansos por nivel de afluencia y rota zonas.</p>
                </div>
                <span class="self-start sm:self-auto text-[11px] font-bold text-on-surface-variant bg-surface-container-high px-3 py-1 rounded-full border border-outline-variant/20">
                    Ciclo activo: {{ $reglasForm['longitud_ciclo'] }} semanas
                </span>
            </div>

            <form wire:submit="guardarReglas" class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Parámetros del Algoritmo -->
                    <div class="lg:col-span-8 space-y-5">
                        <label class="relative flex items-center justify-between p-4 rounded-2xl bg-surface-container-low border border-outline-variant/20 hover:border-outline-variant/40 transition cursor-pointer select-none">
                            <div class="pr-4">
                                <span class="block text-xs font-bold text-on-surface">Impedir zona repetida en semana consecutiva</span>
                                <span class="block text-[11px] text-on-surface-variant font-medium mt-0.5">Rota meseros de manera justa entre Terraza, Salón Principal y Barra.</span>
                            </div>
                            <div class="relative shrink-0">
                                <input type="checkbox" wire:model.live="reglasForm.impedir_zona_repetida" class="sr-only peer" />
                                <div class="w-12 h-6 bg-surface-container-highest peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-6 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </div>
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="rounded-2xl bg-surface-container-low border border-outline-variant/20 p-4">
                                <label class="block text-xs font-bold text-on-surface">Longitud del ciclo de rotación</label>
                                <span class="block text-[10px] text-on-surface-variant font-medium mt-0.5 mb-2">Semanas antes de repetir zona</span>
                                <select wire:model="reglasForm.longitud_ciclo" class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0 min-h-[42px]">
                                    @for ($i = 2; $i <= 8; $i++)
                                        <option value="{{ $i }}">{{ $i }} semanas</option>
                                    @endfor
                                </select>
                                @error('reglasForm.longitud_ciclo')
                                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="rounded-2xl bg-surface-container-low border border-outline-variant/20 p-4">
                                <label class="block text-xs font-bold text-on-surface">Ciclo de compensación de descansos</label>
                                <span class="block text-[10px] text-on-surface-variant font-medium mt-0.5 mb-2">Ventana de equilibrio de fines de semana</span>
                                <select wire:model="reglasForm.semanas_ciclo_descanso" class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0 min-h-[42px]">
                                    @for ($i = 2; $i <= 8; $i++)
                                        <option value="{{ $i }}">{{ $i }} semanas</option>
                                    @endfor
                                </select>
                                @error('reglasForm.semanas_ciclo_descanso')
                                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Chips de Días de Alta Demanda -->
                        <div class="rounded-2xl bg-surface-container-low border border-outline-variant/20 p-4 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="block text-xs font-bold text-on-surface">Días de alta demanda en restaurante</span>
                                    <span class="block text-[10px] text-on-surface-variant font-medium mt-0.5">Días críticos donde los descansos deben rotarse de forma estrictamente equitativa.</span>
                                </div>
                                <span class="material-symbols-outlined text-[18px] text-amber-400">local_fire_department</span>
                            </div>
                            <div class="flex flex-wrap gap-2 pt-1">
                                @foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $num => $nombre)
                                    @php $esAlta = in_array($num, $reglasForm['dias_alta_demanda'] ?? []); @endphp
                                    <label class="cursor-pointer select-none rounded-xl border px-3.5 py-2 text-xs font-black transition-all flex items-center gap-1.5 min-h-[40px] {{ $esAlta ? 'bg-amber-500/20 border-amber-500/50 text-amber-300 shadow-[0_0_12px_rgba(245,158,11,0.20)]' : 'bg-surface-container-lowest border-outline-variant/30 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                                        <input type="checkbox" wire:model.live="reglasForm.dias_alta_demanda" value="{{ $num }}" class="sr-only" />
                                        <span>{{ $nombre }}</span>
                                        @if ($esAlta)
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            @error('reglasForm.dias_alta_demanda')
                                <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-on-primary shadow-md hover:bg-primary/90 active:scale-95 transition cursor-pointer min-h-[44px]">
                                Guardar reglas
                            </button>
                        </div>
                    </div>

                    <!-- Widget IA Equilibrador -->
                    <div class="lg:col-span-4">
                        <div class="h-full rounded-2xl bg-gradient-to-br from-surface-container-high/90 via-surface-container-low to-surface-container-lowest border border-outline-variant/30 p-5 shadow-sm flex flex-col justify-between space-y-4">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-extrabold text-on-surface uppercase tracking-wider">
                                        <span class="material-symbols-outlined text-primary text-[18px]">auto_awesome</span>
                                        IA Equilibrador de Descansos
                                    </span>
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                </div>

                                <div class="py-2">
                                    <div class="text-3xl font-black text-emerald-400 tracking-tight flex items-baseline gap-1">
                                        {{ $scoreEquidad }}%
                                        <span class="text-xs font-bold text-on-surface-variant font-sans">score de equidad</span>
                                    </div>
                                    <p class="text-[11px] text-on-surface-variant font-medium mt-1.5 leading-relaxed">
                                        La rotación asegura que ningún colaborador descanse más de una vez consecutiva en fin de semana mientras otros cubren la alta afluencia.
                                    </p>
                                </div>

                                <div class="w-full bg-surface-container-highest rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-emerald-500 to-primary h-2 rounded-full transition-all duration-500" :style="'width: {{ (int) $scoreEquidad }}%'"></div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-outline-variant/15 space-y-2">
                                <button
                                    type="button"
                                    wire:click="equilibrarDescansos"
                                    class="w-full rounded-xl bg-surface-container-highest border border-outline-variant/40 px-4 py-2.5 text-xs font-extrabold text-on-surface hover:bg-surface-container hover:border-primary/40 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer min-h-[44px]"
                                >
                                    <span class="material-symbols-outlined text-[16px] text-amber-400">balance</span>
                                    <span>Reequilibrar Descansos Ahora</span>
                                </button>
                                <p class="text-[10px] text-center text-on-surface-variant/80 font-medium">Ejecuta swaps automáticos si detecta desbalance en días pico.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
