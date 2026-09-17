<?php

use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component
{
    // Modo de visualización: 'calendario' (estilo Google Calendar) o 'diario' (agenda del día)
    public string $modoVista = 'calendario';

    // Navegación de mes y año
    public int $mesActual = 9;

    public int $anioActual = 2026;

    // Fecha activa para filtros o creación
    public string $fecha = '';

    // Día seleccionado para el modal interactivo de agenda diaria
    public ?string $diaSeleccionado = null;

    // Modal de gestión de reservas del día seleccionado (estilo Google Calendar)
    public bool $modalDiaOpen = false;

    // Filtros visuales interactivos estilo calendario
    public bool $mostrarDiasSinReservas = true;

    public bool $mostrarSoloConfirmadas = false;

    public string $filtrarEstado = 'todas';

    public ?int $reservaSeleccionada = null;

    public array $mesaSeleccionadaIds = [];

    public ?string $errorModal = null;

    public bool $modalCrear = false;

    public array $crearForm = [
        'nombre_contacto' => '',
        'telefono_contacto' => '',
        'fecha' => '',
        'hora_llegada' => '13:00',
        'personas' => 2,
        'mesa_ids' => [],
    ];

    public function mount(): void
    {
        $hoy = now();
        $this->fecha = $hoy->toDateString();
        $this->diaSeleccionado = $hoy->toDateString();
        $this->mesActual = (int) $hoy->month;
        $this->anioActual = (int) $hoy->year;
        $this->crearForm['fecha'] = $hoy->toDateString();
    }

    public function cambiarFecha(): void
    {
        $this->fecha = $this->fecha ?: now()->toDateString();
        $this->diaSeleccionado = $this->fecha;
    }

    public function mesAnterior(): void
    {
        $fecha = Carbon::createFromDate($this->anioActual, $this->mesActual, 1)->subMonth();
        $this->mesActual = (int) $fecha->month;
        $this->anioActual = (int) $fecha->year;
    }

    public function mesSiguiente(): void
    {
        $fecha = Carbon::createFromDate($this->anioActual, $this->mesActual, 1)->addMonth();
        $this->mesActual = (int) $fecha->month;
        $this->anioActual = (int) $fecha->year;
    }

    public function irAHoy(): void
    {
        $hoy = now();
        $this->mesActual = (int) $hoy->month;
        $this->anioActual = (int) $hoy->year;
        $this->fecha = $hoy->toDateString();
        $this->diaSeleccionado = $hoy->toDateString();
    }

    public function seleccionarDia(string $fecha): void
    {
        $this->diaSeleccionado = $fecha;
        $this->fecha = $fecha;
        $this->modalDiaOpen = true;
    }

    public function cerrarModalDia(): void
    {
        $this->modalDiaOpen = false;
    }

    public function abrirAgendaDeDia(string $fecha): void
    {
        $this->fecha = $fecha;
        $this->diaSeleccionado = $fecha;
        $this->modalDiaOpen = false;
        $this->modoVista = 'diario';
    }

    public function cambiarModoVista(string $modo): void
    {
        if (in_array($modo, ['calendario', 'diario'], true)) {
            $this->modoVista = $modo;
            $this->modalDiaOpen = false;
        }
    }

    public function abrirCrear(): void
    {
        $this->modalCrear = true;
    }

    public function abrirCrearConFecha(?string $fecha = null): void
    {
        if ($fecha) {
            $this->crearForm['fecha'] = $fecha;
            $this->fecha = $fecha;
        }
        $this->modalDiaOpen = false;
        $this->modalCrear = true;
    }

    public function marcarLlegoId(int $id): void
    {
        $this->authorize('update', Reserva::class);
        $reserva = Reserva::findOrFail($id);
        app(ReservaService::class)->marcarLlego($reserva);
        session()->flash('status', "Cliente {$reserva->nombre_contacto} en la mesa.");
    }

    public function finalizarId(int $id): void
    {
        $this->authorize('update', Reserva::class);
        $reserva = Reserva::findOrFail($id);
        app(ReservaService::class)->finalizar($reserva);
        session()->flash('status', "Reserva de {$reserva->nombre_contacto} finalizada.");
    }

    public function cancelarReservaId(int $id): void
    {
        $this->authorize('cancelar', Reserva::class);
        $reserva = Reserva::findOrFail($id);
        app(ReservaService::class)->cancelar($reserva);
        session()->flash('status', "Reserva de {$reserva->nombre_contacto} cancelada.");
    }

    public function with(): array
    {
        // Sincronizar fecha con diaSeleccionado y mes/año
        if ($this->fecha && $this->fecha !== $this->diaSeleccionado) {
            $this->diaSeleccionado = $this->fecha;
            try {
                $carbonFecha = Carbon::parse($this->fecha);
                $this->mesActual = (int) $carbonFecha->month;
                $this->anioActual = (int) $carbonFecha->year;
            } catch (\Throwable) {
                // ignore
            }
        }

        // Si se filtran solicitudes web pendientes, conmutar a lista diaria
        if ($this->filtrarEstado === 'solicitadas_pendientes') {
            $this->modoVista = 'diario';
        }

        $service = app(ReservaService::class);
        $delDia = $service->reservasDelDia($this->fecha);

        if ($this->filtrarEstado === 'solicitadas_pendientes') {
            $reservas = Reserva::with(['mesas', 'cliente', 'confirmadoPor'])
                ->where('estado', 'solicitada')
                ->orderBy('fecha')
                ->orderBy('hora_llegada')
                ->get();
        } else {
            $reservas = $delDia
                ->when($this->filtrarEstado !== 'todas', fn ($col) => $col->where('estado', $this->filtrarEstado));
        }

        // Consultas agrupadas para el mes (1 sola consulta eficiente)
        $reservasDelMesQuery = Reserva::with(['mesas', 'cliente', 'confirmadoPor'])
            ->whereYear('fecha', $this->anioActual)
            ->whereMonth('fecha', $this->mesActual)
            ->orderBy('hora_llegada');

        if ($this->filtrarEstado !== 'todas' && $this->filtrarEstado !== 'solicitadas_pendientes') {
            $reservasDelMesQuery->where('estado', $this->filtrarEstado);
        }

        if ($this->mostrarSoloConfirmadas) {
            $reservasDelMesQuery->where('estado', 'confirmada');
        }

        $reservasDelMes = $reservasDelMesQuery->get();

        $reservasPorDia = $reservasDelMes->groupBy(fn ($r) => $r->fecha->format('Y-m-d'));

        // KPIs compactos del Mes
        $kpisMes = [
            'total_reservas' => $reservasDelMes->count(),
            'total_comensales' => (int) $reservasDelMes->sum('personas'),
            'confirmadas' => $reservasDelMes->where('estado', 'confirmada')->count(),
            'solicitadas' => $reservasDelMes->where('estado', 'solicitada')->count(),
            'canceladas' => $reservasDelMes->whereIn('estado', ['cancelada', 'no_mostro'])->count(),
            'dia_pico' => null,
            'dia_pico_conteo' => 0,
        ];

        if ($reservasPorDia->isNotEmpty()) {
            $diaPicoKey = $reservasPorDia->sortByDesc(fn ($grupo) => $grupo->count())->keys()->first();
            $kpisMes['dia_pico'] = Carbon::parse($diaPicoKey)->format('d/m');
            $kpisMes['dia_pico_conteo'] = $reservasPorDia[$diaPicoKey]->count();
        }

        // Matriz Google Calendar (7 columnas: Lunes a Domingo)
        $primerDiaMes = Carbon::createFromDate($this->anioActual, $this->mesActual, 1)->startOfDay();
        $ultimoDiaMes = (clone $primerDiaMes)->endOfMonth();
        $hoyStr = now()->toDateString();

        $offsetInicio = ($primerDiaMes->dayOfWeekIso - 1);
        $celdasCalendario = [];

        // Relleno mes anterior
        for ($i = $offsetInicio; $i > 0; $i--) {
            $fechaPadded = (clone $primerDiaMes)->subDays($i);
            $celdasCalendario[] = [
                'fecha' => $fechaPadded->format('Y-m-d'),
                'numeroDia' => (int) $fechaPadded->day,
                'esMesActual' => false,
                'esHoy' => $fechaPadded->toDateString() === $hoyStr,
                'esSeleccionado' => $fechaPadded->toDateString() === $this->diaSeleccionado,
                'totalReservas' => 0,
                'totalPersonas' => 0,
                'confirmadas' => 0,
                'solicitadas' => 0,
                'canceladas' => 0,
                'reservasEventos' => [],
            ];
        }

        // Días mes actual
        for ($d = 1; $d <= $ultimoDiaMes->day; $d++) {
            $fechaDia = Carbon::createFromDate($this->anioActual, $this->mesActual, $d)->startOfDay();
            $fechaDiaStr = $fechaDia->format('Y-m-d');
            $resDia = $reservasPorDia->get($fechaDiaStr, collect());

            $eventos = $resDia->map(function ($r) {
                return [
                    'id' => $r->id,
                    'nombre' => $r->nombre_contacto,
                    'hora' => Carbon::parse($r->hora_llegada)->format('H:i'),
                    'personas' => $r->personas,
                    'estado' => $r->estado,
                ];
            })->values()->toArray();

            $celdasCalendario[] = [
                'fecha' => $fechaDiaStr,
                'numeroDia' => $d,
                'esMesActual' => true,
                'esHoy' => $fechaDiaStr === $hoyStr,
                'esSeleccionado' => $fechaDiaStr === $this->diaSeleccionado,
                'totalReservas' => $resDia->count(),
                'totalPersonas' => (int) $resDia->sum('personas'),
                'confirmadas' => $resDia->where('estado', 'confirmada')->count(),
                'solicitadas' => $resDia->where('estado', 'solicitada')->count(),
                'canceladas' => $resDia->whereIn('estado', ['cancelada', 'no_mostro'])->count(),
                'reservasEventos' => $eventos,
            ];
        }

        // Relleno mes siguiente
        $totalCeldas = count($celdasCalendario);
        $resto = $totalCeldas % 7;
        if ($resto !== 0) {
            $offsetFin = 7 - $resto;
            for ($j = 1; $j <= $offsetFin; $j++) {
                $fechaPadded = (clone $ultimoDiaMes)->addDays($j);
                $celdasCalendario[] = [
                    'fecha' => $fechaPadded->format('Y-m-d'),
                    'numeroDia' => (int) $fechaPadded->day,
                    'esMesActual' => false,
                    'esHoy' => $fechaPadded->toDateString() === $hoyStr,
                    'esSeleccionado' => $fechaPadded->toDateString() === $this->diaSeleccionado,
                    'totalReservas' => 0,
                    'totalPersonas' => 0,
                    'confirmadas' => 0,
                    'solicitadas' => 0,
                    'canceladas' => 0,
                    'reservasEventos' => [],
                ];
            }
        }

        // Reservas del día seleccionado para el modal
        $reservasDiaSeleccionado = $this->diaSeleccionado
            ? Reserva::with(['mesas', 'cliente', 'confirmadoPor'])
                ->whereDate('fecha', $this->diaSeleccionado)
                ->orderBy('hora_llegada')
                ->get()
            : collect();

        $mesesNombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $nombreMesActual = $mesesNombres[$this->mesActual] ?? 'Mes';

        $reservaActiva = $this->reservaSeleccionada ? Reserva::with(['mesas', 'confirmadoPor'])->find($this->reservaSeleccionada) : null;
        $mesasDisponiblesModal = collect();
        if ($reservaActiva) {
            $mesasDisponiblesModal = $service->verificarDisponibilidad(
                $reservaActiva->fecha->toDateString(),
                $reservaActiva->hora_llegada,
                $reservaActiva->personas,
                $reservaActiva->duracion_min
            );
        }

        return [
            'reservas' => $reservas,
            'conteo' => [
                'solicitadas' => $delDia->where('estado', 'solicitada')->count(),
                'confirmadas' => $delDia->where('estado', 'confirmada')->count(),
                'canceladas' => $delDia->where('estado', 'cancelada')->count(),
            ],
            'solicitudesPendientesGlobales' => Reserva::where('estado', 'solicitada')->count(),
            'disponibles' => $service->verificarDisponibilidad($this->fecha, $this->crearForm['hora_llegada'], (int) $this->crearForm['personas']),
            'mesasDisponiblesModal' => $mesasDisponiblesModal,
            'celdasCalendario' => $celdasCalendario,
            'kpisMes' => $kpisMes,
            'nombreMesActual' => $nombreMesActual,
            'reservasDiaSeleccionado' => $reservasDiaSeleccionado,
        ];
    }

    public function crearReserva(): void
    {
        $this->authorize('create', Reserva::class);

        $validated = $this->validate([
            'crearForm.nombre_contacto' => ['required', 'string', 'max:255'],
            'crearForm.telefono_contacto' => ['required', 'string', 'max:30'],
            'crearForm.fecha' => ['required', 'date', 'after_or_equal:'.now()->toDateString()],
            'crearForm.hora_llegada' => ['required', 'date_format:H:i'],
            'crearForm.personas' => ['required', 'integer', 'min:1'],
            'crearForm.mesa_ids' => ['nullable', 'array'],
        ]);

        app(ReservaService::class)->crear($validated['crearForm'], 'sistema');

        $this->reset('crearForm', 'modalCrear');
        $this->crearForm['fecha'] = now()->toDateString();
        $this->crearForm['hora_llegada'] = '13:00';
        $this->crearForm['personas'] = 2;
        $this->fecha = $validated['crearForm']['fecha'];
        $this->diaSeleccionado = $validated['crearForm']['fecha'];
        session()->flash('status', 'Reserva creada exitosamente.');
    }

    public function abrirDetalle(int $id): void
    {
        $this->reservaSeleccionada = $id;
        $this->errorModal = null;
        $res = Reserva::with('mesas')->find($id);
        $this->mesaSeleccionadaIds = $res?->mesas->pluck('id')->map(fn ($i) => (int) $i)->toArray() ?? [];

        if (empty($this->mesaSeleccionadaIds) && $res) {
            $disp = app(ReservaService::class)->verificarDisponibilidad(
                $res->fecha->toDateString(),
                $res->hora_llegada,
                $res->personas,
                $res->duracion_min
            );
            $mejor = $disp->filter(fn ($m) => $m->capacidad >= $res->personas)->sortBy('capacidad')->first();
            if ($mejor) {
                $this->mesaSeleccionadaIds = [(int) $mejor->id];
            } else {
                $acum = 0;
                $asignadas = [];
                foreach ($disp->sortByDesc('capacidad') as $m) {
                    $asignadas[] = (int) $m->id;
                    $acum += $m->capacidad;
                    if ($acum >= $res->personas) {
                        break;
                    }
                }
                $this->mesaSeleccionadaIds = $asignadas;
            }
        }
    }

    public function confirmar(): void
    {
        $this->authorize('confirmar', Reserva::class);
        $this->errorModal = null;

        try {
            $reserva = Reserva::findOrFail($this->reservaSeleccionada);
            $mesaIds = ! empty($this->mesaSeleccionadaIds)
                ? array_map('intval', (array) $this->mesaSeleccionadaIds)
                : null;
            app(ReservaService::class)->confirmar($reserva, auth()->user(), $mesaIds);
            session()->flash('status', "Reserva de {$reserva->nombre_contacto} confirmada.");
            $this->cerrarDetalle();
        } catch (\Throwable $e) {
            $this->errorModal = $e->getMessage();
        }
    }

    public function marcarLlego(): void
    {
        $this->authorize('update', Reserva::class);

        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->marcarLlego($reserva);
        session()->flash('status', 'Cliente en la mesa.');
    }

    public function finalizar(): void
    {
        $this->authorize('update', Reserva::class);

        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->finalizar($reserva);
        session()->flash('status', 'Reserva finalizada.');
    }

    public function cancelarReserva(): void
    {
        $this->authorize('cancelar', Reserva::class);

        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->cancelar($reserva);
        session()->flash('status', 'Reserva cancelada.');
    }

    public function noShow(): void
    {
        $this->authorize('update', Reserva::class);

        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->marcarNoShow($reserva);
        session()->flash('status', 'Sin presentarse.');
    }

    public function cerrarDetalle(): void
    {
        $this->reset('reservaSeleccionada', 'mesaSeleccionadaIds', 'errorModal');
    }
}; ?>

<div class="space-y-4">
    <!-- HEADER COMPACTO Y MODERNO -->
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
                <span class="material-symbols-outlined text-[22px]">calendar_month</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-black tracking-tight text-on-surface">Reservas</h1>
                    <span class="rounded-full bg-secondary/15 px-2 py-0.2 text-[10px] font-extrabold text-secondary border border-secondary/30">RES-01</span>
                </div>
                <p class="text-[11px] text-on-surface-variant">Gestión ágil de agenda, confirmación y mesas</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- SELECTOR DE VISTA -->
            <div class="inline-flex rounded-xl bg-surface-container p-1 border border-surface-container-highest">
                <button
                    type="button"
                    wire:click="cambiarModoVista('calendario')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $modoVista === 'calendario' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                    title="Vista mensual estilo Google Calendar"
                >
                    <span class="material-symbols-outlined text-[16px]">calendar_view_month</span>
                    <span>Tablón Mensual</span>
                </button>
                <button
                    type="button"
                    wire:click="cambiarModoVista('diario')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $modoVista === 'diario' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                    title="Vista detallada de lista diaria"
                >
                    <span class="material-symbols-outlined text-[16px]">view_day</span>
                    <span>Agenda del Día</span>
                </button>
            </div>

            <button 
                wire:click="abrirCrear" 
                class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-black text-on-primary cursor-pointer hover:bg-primary/90 transition-all shadow-sm active:scale-95"
            >
                <span class="material-symbols-outlined text-[16px]">add</span>
                <span>Nueva reserva</span>
            </button>
        </div>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-secondary/40 bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary animate-fade-in flex items-center gap-2">
            <span class="material-symbols-outlined text-[16px]">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($solicitudesPendientesGlobales > 0 && $filtrarEstado !== 'solicitadas_pendientes')
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-3.5">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-amber-600 text-2xl animate-pulse">notifications_active</span>
                <div>
                    <p class="text-xs font-black text-on-surface">
                        Tienes {{ $solicitudesPendientesGlobales }} {{ $solicitudesPendientesGlobales === 1 ? 'solicitud' : 'solicitudes' }} de reserva web pendiente(s) por tomar/confirmar.
                    </p>
                    <p class="text-[11px] text-on-surface-variant">Clientes en espera de confirmación de mesa.</p>
                </div>
            </div>
            <button wire:click="$set('filtrarEstado', 'solicitadas_pendientes')" class="px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-black hover:bg-primary/90 transition-all cursor-pointer whitespace-nowrap">
                Ver solicitudes pendientes
            </button>
        </div>
    @endif

    <!-- VISTA 1: CALENDARIO MENSUAL ESTILO TABLA CLÁSICA (IMAGEN DE REFERENCIA) -->
    @if ($modoVista === 'calendario' && $filtrarEstado !== 'solicitadas_pendientes')
        <div class="space-y-3 animate-fade-in">

            <!-- BARRA DE NAVEGACIÓN: mes, año, hoy, nueva reserva -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-surface-container-lowest rounded-2xl border border-surface-container-highest px-4 py-3 shadow-sm">

                <!-- Izquierda: Navegación de mes -->
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        wire:click="mesAnterior"
                        class="w-8 h-8 flex items-center justify-center rounded-full border border-surface-container-highest bg-surface-container hover:bg-surface-container-high text-on-surface transition-all cursor-pointer"
                        title="Mes anterior"
                    >
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </button>

                    <h2 class="text-xl sm:text-2xl font-black text-on-surface tracking-tight capitalize min-w-[200px] text-center">
                        {{ mb_strtolower($nombreMesActual) }} <span class="text-on-surface-variant font-light">{{ $anioActual }}</span>
                    </h2>

                    <button
                        type="button"
                        wire:click="mesSiguiente"
                        class="w-8 h-8 flex items-center justify-center rounded-full border border-surface-container-highest bg-surface-container hover:bg-surface-container-high text-on-surface transition-all cursor-pointer"
                        title="Mes siguiente"
                    >
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </button>

                    <button
                        type="button"
                        wire:click="irAHoy"
                        class="ml-1 px-3 py-1 rounded-full border border-primary/30 text-primary text-xs font-bold hover:bg-primary/10 transition-all cursor-pointer"
                    >
                        Hoy
                    </button>
                </div>

                <!-- Derecha: Chips de KPIs + botón nueva reserva -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1 bg-surface-container border border-surface-container-highest rounded-full px-3 py-1 text-xs font-bold">
                        <span class="text-on-surface-variant">Reservas:</span>
                        <span class="text-primary font-black">{{ $kpisMes['total_reservas'] }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1 bg-surface-container border border-surface-container-highest rounded-full px-3 py-1 text-xs font-bold">
                        <span class="text-on-surface-variant">Comensales:</span>
                        <span class="text-secondary font-black">{{ $kpisMes['total_comensales'] }}</span>
                    </span>
                    @if ($kpisMes['solicitadas'] > 0)
                        <span class="inline-flex items-center gap-1 bg-amber-500/10 text-amber-700 border border-amber-400/30 rounded-full px-3 py-1 text-xs font-bold animate-pulse">
                            ⏳ {{ $kpisMes['solicitadas'] }} pendiente{{ $kpisMes['solicitadas'] !== 1 ? 's' : '' }}
                        </span>
                    @endif

                    <button
                        type="button"
                        wire:click="abrirCrear"
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 text-xs font-black text-on-primary hover:bg-primary/90 transition-all shadow-sm active:scale-95 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[15px]">add</span>
                        Nueva reserva
                    </button>
                </div>
            </div>

            <!-- CALENDARIO TIPO TABLA CLÁSICA -->
            <div class="bg-surface-container-lowest rounded-2xl border border-surface-container-highest shadow-sm overflow-hidden">
                <!-- Tabla -->
                <table class="w-full table-fixed border-collapse">
                    <!-- Cabecera: LUNES | MARTES | MIÉRCOLES | JUEVES | VIERNES | SÁBADO | DOMINGO -->
                    <thead>
                        <tr class="bg-surface-container-low/60 border-b border-surface-container-highest">
                            @foreach (['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO', 'DOMINGO'] as $i => $nombre)
                                <th class="text-center text-[11px] sm:text-xs font-black uppercase tracking-widest text-on-surface-variant py-3 px-1 border-r last:border-r-0 border-surface-container-highest {{ $i >= 5 ? 'text-rose-400' : '' }}">
                                    {{ $nombre }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <!-- Cuerpo: semanas/filas -->
                    <tbody>
                        @foreach (array_chunk($celdasCalendario, 7) as $semana)
                            <tr class="border-t border-surface-container-highest">
                                @foreach ($semana as $celda)
                                    @php
                                        $esFin = in_array(array_search($celda, $semana), [5, 6]);
                                    @endphp
                                    <td
                                        wire:click="seleccionarDia('{{ $celda['fecha'] }}')"
                                        class="align-top border-r last:border-r-0 border-surface-container-highest cursor-pointer transition-all select-none group
                                            {{ ! $celda['esMesActual'] ? 'bg-surface-container-low/20' : 'bg-surface-container-lowest hover:bg-primary/[0.02]' }}
                                            {{ $celda['esHoy'] ? 'bg-primary/[0.04] ring-2 ring-inset ring-primary/40' : '' }}
                                        "
                                        style="height: 110px; min-width: 0;"
                                    >
                                        <div class="h-full flex flex-col p-1.5 sm:p-2 overflow-hidden gap-1">
                                            <!-- NÚMERO DEL DÍA: arriba a la izquierda -->
                                            <div class="flex items-start justify-between shrink-0">
                                                @if ($celda['esHoy'])
                                                    <span class="inline-flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-primary text-on-primary text-xs font-black leading-none shadow-sm">
                                                        {{ $celda['numeroDia'] }}
                                                    </span>
                                                @elseif (! $celda['esMesActual'])
                                                    <span class="text-xs sm:text-sm font-bold text-on-surface-variant/30 leading-none px-0.5">
                                                        {{ $celda['numeroDia'] }}
                                                    </span>
                                                @else
                                                    <span class="text-xs sm:text-sm font-bold text-on-surface/70 leading-none px-0.5 group-hover:text-primary transition-colors">
                                                        {{ $celda['numeroDia'] }}
                                                    </span>
                                                @endif

                                                @if ($celda['esMesActual'] && $celda['totalReservas'] > 0)
                                                    <span class="text-[9px] font-bold text-on-surface-variant/60 hidden sm:inline leading-none">
                                                        {{ $celda['totalPersonas'] }}p
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- EVENTOS: barras horizontales de color dentro de la celda -->
                                            @if ($celda['esMesActual'] && $celda['totalReservas'] > 0)
                                                <div class="flex flex-col gap-0.5 flex-1 overflow-hidden">
                                                    @foreach (array_slice($celda['reservasEventos'], 0, 3) as $evento)
                                                        @php
                                                            $barClasses = match ($evento['estado']) {
                                                                'confirmada'  => 'bg-[#c8a96e] text-white',
                                                                'solicitada'  => 'bg-amber-400/80 text-amber-900',
                                                                'llego'       => 'bg-emerald-500/80 text-white',
                                                                'finalizada'  => 'bg-slate-300 text-slate-600',
                                                                'cancelada'   => 'bg-rose-200 text-rose-700 line-through',
                                                                default       => 'bg-surface-container-high text-on-surface-variant',
                                                            };
                                                        @endphp
                                                        <div
                                                            wire:click.stop="abrirDetalle({{ $evento['id'] }})"
                                                            class="w-full flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-bold truncate cursor-pointer transition-opacity hover:opacity-80 {{ $barClasses }}"
                                                            title="{{ $evento['hora'] }} · {{ $evento['nombre'] }} ({{ $evento['personas'] }} pers.) — {{ ucfirst($evento['estado']) }}"
                                                        >
                                                            <span class="material-symbols-outlined text-[11px] shrink-0 leading-none">bookmark</span>
                                                            <span class="truncate">
                                                                <span class="font-mono">{{ $evento['hora'] }}</span>
                                                                <span class="hidden sm:inline"> {{ $evento['nombre'] }}</span>
                                                            </span>
                                                        </div>
                                                    @endforeach

                                                    @if ($celda['totalReservas'] > 3)
                                                        <div
                                                            wire:click.stop="seleccionarDia('{{ $celda['fecha'] }}')"
                                                            class="text-[10px] font-black text-primary hover:underline cursor-pointer leading-tight pl-0.5"
                                                        >
                                                            +{{ $celda['totalReservas'] - 3 }} más
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- LEYENDA DE COLORES -->
            <div class="flex flex-wrap items-center gap-3 px-1 text-xs text-on-surface-variant">
                <span class="font-bold">Leyenda:</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#c8a96e] inline-block"></span>Confirmada</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-amber-400/80 inline-block"></span>Solicitud web</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-emerald-500/80 inline-block"></span>En sala</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-slate-300 inline-block"></span>Finalizada</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-rose-200 inline-block"></span>Cancelada</span>
            </div>

        </div>
    @endif


    <!-- MODAL INTERACTIVO DE AGENDA DEL DÍA (DESPLEGABLE AL TOCAR CUALQUIER DÍA) -->

    @if ($modalDiaOpen && $diaSeleccionado)
        <div 
            x-data 
            @keydown.escape.window="$wire.cerrarModalDia()" 
            role="dialog" 
            aria-modal="true" 
            aria-labelledby="modal-agenda-dia-title" 
            class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in" 
            wire:click.self="cerrarModalDia"
        >
            <div class="w-full max-w-2xl rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest space-y-4 max-h-[85vh] overflow-y-auto">
                <!-- CABECERA DEL MODAL DE DÍA -->
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
                            <span class="material-symbols-outlined text-[22px]">calendar_today</span>
                        </div>
                        <div>
                            <h3 id="modal-agenda-dia-title" class="text-base font-extrabold text-on-surface">
                                Agenda del {{ \Illuminate\Support\Carbon::parse($diaSeleccionado)->format('d/m/Y') }}
                            </h3>
                            <p class="text-xs text-on-surface-variant">
                                {{ $reservasDiaSeleccionado->count() }} {{ $reservasDiaSeleccionado->count() === 1 ? 'reserva programada' : 'reservas programadas' }}
                                @if ($reservasDiaSeleccionado->isNotEmpty())
                                    · Total {{ $reservasDiaSeleccionado->sum('personas') }} comensales
                                @endif
                            </p>
                        </div>
                    </div>

                    <button 
                        type="button" 
                        wire:click="cerrarModalDia" 
                        aria-label="Cerrar agenda del día" 
                        class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- SUB-BARRA DE ACCIONES DEL DÍA -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <button
                        wire:click="abrirCrearConFecha('{{ $diaSeleccionado }}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-all shadow-xs"
                    >
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        <span>+ Nueva reserva para este día</span>
                    </button>

                    <button
                        wire:click="abrirAgendaDeDia('{{ $diaSeleccionado }}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest text-xs font-bold text-on-surface transition-all"
                    >
                        <span class="material-symbols-outlined text-[16px] text-primary">view_day</span>
                        <span>Abrir en Agenda Diaria</span>
                    </button>
                </div>

                <!-- LISTA DE RESERVAS DEL DÍA CON GESTIÓN DIRECTA -->
                @if ($reservasDiaSeleccionado->isEmpty())
                    <div class="text-center py-10 text-on-surface-variant space-y-2 border border-dashed border-surface-container-high rounded-2xl">
                        <span class="material-symbols-outlined text-4xl text-on-surface-variant/40">event_busy</span>
                        <p class="text-xs font-bold">No hay reservas registradas para esta fecha.</p>
                        <button
                            wire:click="abrirCrearConFecha('{{ $diaSeleccionado }}')"
                            class="inline-flex items-center gap-1 text-xs font-black text-primary hover:underline cursor-pointer pt-1"
                        >
                            <span>+ Registrar la primera reserva</span>
                        </button>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($reservasDiaSeleccionado as $reserva)
                            <div class="rounded-2xl border border-surface-container-highest bg-surface-container-low p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-primary/40 transition-colors">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-black text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                                            {{ \Illuminate\Support\Carbon::parse($reserva->hora_llegada)->format('H:i') }}
                                        </span>
                                        <span class="text-sm font-extrabold text-on-surface">
                                            {{ $reserva->nombre_contacto }}
                                        </span>
                                        <span class="rounded-full px-2 py-0.2 text-[10px] font-bold
                                            {{ match($reserva->estado) {
                                                'confirmada' => 'bg-secondary/15 text-secondary border border-secondary/30',
                                                'solicitada' => 'bg-amber-500/15 text-amber-600 border border-amber-500/20 animate-pulse',
                                                'llego' => 'bg-emerald-500/15 text-emerald-600 border border-emerald-500/20',
                                                'finalizada' => 'bg-surface-container-high text-on-surface-variant',
                                                'cancelada' => 'bg-error/15 text-error border border-error/20',
                                                'no_mostro' => 'bg-error/15 text-error border border-error/20',
                                                default => 'bg-surface-container-high text-on-surface-variant',
                                            } }}">
                                            {{ ucwords(str_replace('_', ' ', $reserva->estado)) }}
                                        </span>
                                    </div>

                                    <div class="text-xs text-on-surface-variant flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span>👥 {{ $reserva->personas }} personas</span>
                                        <span>•</span>
                                        <span>📞 {{ $reserva->telefono_contacto }}</span>
                                        <span>•</span>
                                        @if ($reserva->mesas->isNotEmpty())
                                            <span class="text-secondary font-bold">
                                                {{ $reserva->mesas->pluck('nombre')->filter()->join(', ') ?: $reserva->mesas->pluck('numero')->map(fn ($n) => 'Mesa #' . $n)->join(', ') }}
                                            </span>
                                        @else
                                            <span class="text-amber-600 font-bold">⚠️ Sin mesa</span>
                                        @endif
                                    </div>
                                </div>

                                <!-- ACCIONES DIRECTAS EN EL MODAL -->
                                <div class="flex flex-wrap items-center gap-1.5 self-end sm:self-center">
                                    @if ($reserva->estado === 'solicitada')
                                        <button
                                            wire:click="abrirDetalle({{ $reserva->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-secondary text-on-secondary text-xs font-bold hover:bg-secondary/90 transition-all shadow-xs"
                                            title="Confirmar y asignar mesa"
                                        >
                                            <span class="material-symbols-outlined text-[15px]">check_circle</span>
                                            <span>Confirmar</span>
                                        </button>
                                    @elseif ($reserva->estado === 'confirmada')
                                        <button
                                            wire:click="marcarLlegoId({{ $reserva->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-all shadow-xs"
                                            title="Marcar llegada del cliente al restaurante"
                                        >
                                            <span class="material-symbols-outlined text-[15px]">how_to_reg</span>
                                            <span>Llegó</span>
                                        </button>
                                    @elseif ($reserva->estado === 'llego')
                                        <button
                                            wire:click="finalizarId({{ $reserva->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-surface-container-high text-on-surface text-xs font-bold hover:bg-surface-container-highest transition-all"
                                            title="Finalizar reserva y liberar mesa"
                                        >
                                            <span class="material-symbols-outlined text-[15px]">task_alt</span>
                                            <span>Finalizar</span>
                                        </button>
                                    @endif

                                    <button
                                        wire:click="abrirDetalle({{ $reserva->id }})"
                                        class="p-1.5 rounded-xl border border-surface-container-highest bg-surface-container hover:bg-surface-container-high text-on-surface transition-all"
                                        title="Ver detalles completos y mesas"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">info</span>
                                    </button>

                                    @if (in_array($reserva->estado, ['solicitada', 'confirmada']))
                                        <button
                                            wire:click="cancelarReservaId({{ $reserva->id }})"
                                            wire:confirm="¿Seguro que deseas cancelar la reserva de {{ $reserva->nombre_contacto }}?"
                                            class="p-1.5 rounded-xl bg-error/10 text-error hover:bg-error/20 border border-error/20 transition-all"
                                            title="Cancelar reserva"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">cancel</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="pt-3 border-t border-surface-container-high flex justify-end">
                    <button
                        type="button"
                        wire:click="cerrarModalDia"
                        class="rounded-xl border border-surface-container-high bg-surface-container px-4 py-2 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- VISTA 2: AGENDA DIARIA DETALLADA -->
    @if ($modoVista === 'diario' || $filtrarEstado === 'solicitadas_pendientes')
        <div class="space-y-4 animate-fade-in">
            <div class="flex flex-wrap items-center gap-3">
                <input 
                    type="date" 
                    wire:model.live="fecha" 
                    wire:change="cambiarFecha"
                    class="rounded-xl border border-surface-container-highest bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" 
                />
                <select 
                    wire:model.live="filtrarEstado" 
                    class="rounded-xl border border-surface-container-highest bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                >
                    <option value="todas">Todos los estados (fecha)</option>
                    <option value="solicitadas_pendientes">🔔 Solicitudes Web pendientes (todas las fechas: {{ $solicitudesPendientesGlobales }})</option>
                    <option value="solicitada">Solicitadas (fecha actual)</option>
                    <option value="confirmada">Confirmadas (fecha actual)</option>
                    <option value="llego">Llegó</option>
                    <option value="cancelada">Canceladas</option>
                    <option value="no_mostro">No se mostró</option>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-4">
                @foreach (['solicitadas' => 'Solicitadas', 'confirmadas' => 'Confirmadas', 'canceladas' => 'Canceladas'] as $k => $label)
                    <div class="rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-sm">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-black text-on-surface">{{ $conteo[$k] }}</p>
                    </div>
                @endforeach
            </div>

            @if ($reservas->isEmpty())
                <p class="rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-6 text-xs text-on-surface-variant">Sin reservas para este criterio de búsqueda.</p>
            @else
                <div class="space-y-3">
                    @foreach ($reservas as $reserva)
                        <button wire:click="abrirDetalle({{ $reserva->id }})" class="w-full rounded-2xl border border-surface-container-highest bg-surface-container-low p-4 text-left cursor-pointer hover:border-primary/30 transition-colors">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-extrabold text-on-surface">
                                        {{ $reserva->nombre_contacto }} · {{ $reserva->fecha->format('d/m') }} {{ \Illuminate\Support\Carbon::parse($reserva->hora_llegada)->format('H:i') }}
                                        @if ($reserva->origen === 'publico')
                                            <span class="ml-2 rounded-md bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-600 border border-amber-500/20">Web</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-on-surface-variant">
                                        {{ $reserva->personas }} personas · 
                                        @if ($reserva->mesas->isNotEmpty())
                                            {{ $reserva->mesas->pluck('nombre')->join(', ') ?: $reserva->mesas->pluck('numero')->map(fn ($n) => 'Mesa #' . $n)->join(', ') }}
                                        @else
                                            <span class="text-amber-500 font-bold">Sin mesa asignada</span>
                                        @endif
                                    </p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-[11px] font-bold
                                    {{ match($reserva->estado) {
                                        'confirmada' => 'bg-secondary/15 text-secondary border border-secondary/30',
                                        'solicitada' => 'bg-primary/10 text-primary border border-primary/20',
                                        'llego' => 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20',
                                        'finalizada' => 'bg-surface-container-high text-on-surface-variant',
                                        'cancelada' => 'bg-error/10 text-error border border-error/20',
                                        'no_mostro' => 'bg-error/10 text-error border border-error/20',
                                        default => 'bg-surface-container-high text-on-surface-variant',
                                    } }}">
                                    {{ ucwords(str_replace('_', ' ', $reserva->estado)) }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- MODAL: NUEVA RESERVA -->
    @if ($modalCrear)
        <div 
            x-data 
            @keydown.escape.window="$wire.set('modalCrear', false)" 
            role="dialog" 
            aria-modal="true" 
            aria-labelledby="modal-crear-reserva-title" 
            class="fixed inset-0 z-50 flex items-end justify-center bg-inverse-surface/40 backdrop-blur-sm sm:items-center animate-fade-in" 
            wire:click.self="$set('modalCrear', false)"
        >
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-6 shadow-2xl sm:max-w-md sm:rounded-3xl border border-surface-container-highest space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <h3 id="modal-crear-reserva-title" class="text-base font-extrabold text-on-surface">Nueva Reserva</h3>
                    <button 
                        type="button" 
                        wire:click="$set('modalCrear', false)" 
                        aria-label="Cerrar creación de reserva" 
                        class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form wire:submit="crearReserva" class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Nombre del cliente:</label>
                        <input type="text" wire:model="crearForm.nombre_contacto" class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" required />
                        @error('crearForm.nombre_contacto') <p class="mt-1 text-xs font-bold text-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Teléfono:</label>
                        <input type="text" wire:model="crearForm.telefono_contacto" class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" required />
                        @error('crearForm.telefono_contacto') <p class="mt-1 text-xs font-bold text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Fecha:</label>
                            <input type="date" wire:model.live="crearForm.fecha" class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" required />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Hora:</label>
                            <input type="time" wire:model.live="crearForm.hora_llegada" class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" required />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Personas / Comensales:</label>
                        <input type="number" min="1" wire:model.live="crearForm.personas" class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" required />
                    </div>
                    <p class="text-[11px] text-on-surface-variant">Mesas disponibles: {{ $disponibles->map(fn ($m) => $m->numero . ' (' . $m->capacidad . ' pax)')->join(' · ') ?: 'ninguna para este horario' }}</p>
                    
                    <div class="pt-3 border-t border-surface-container-high grid grid-cols-2 gap-2">
                        <button type="button" wire:click="$set('modalCrear', false)" class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-extrabold text-on-surface-variant hover:text-on-surface">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-xl bg-primary py-2.5 text-xs font-black text-on-primary shadow-md hover:bg-primary-container">
                            ✓ Crear reserva
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL: DETALLE Y CONFIRMACIÓN DE RESERVA -->
    @if ($reservaSeleccionada)
        @php $detalle = \App\Models\Reserva::with(['mesas', 'confirmadoPor'])->find($reservaSeleccionada); @endphp
        <div 
            x-data 
            @keydown.escape.window="$wire.cerrarDetalle()" 
            role="dialog" 
            aria-modal="true" 
            aria-labelledby="modal-detalle-reserva-title" 
            class="fixed inset-0 z-50 flex items-end justify-center bg-inverse-surface/40 backdrop-blur-sm sm:items-center animate-fade-in" 
            wire:click.self="cerrarDetalle"
        >
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-6 shadow-2xl sm:max-w-md sm:rounded-3xl border border-surface-container-highest space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <h3 id="modal-detalle-reserva-title" class="text-base font-extrabold text-on-surface">{{ $detalle?->nombre_contacto }}</h3>
                    <button 
                        type="button" 
                        wire:click="cerrarDetalle" 
                        aria-label="Cerrar detalle de reserva" 
                        class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                @if ($errorModal)
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-rose-600 text-base">error</span>
                        <span>{{ $errorModal }}</span>
                    </div>
                @endif

                @if ($detalle)
                    <dl class="space-y-2 text-xs">
                        <div class="flex justify-between"><dt class="text-on-surface-variant">Teléfono:</dt><dd class="font-bold">{{ $detalle->telefono_contacto }}</dd></div>
                        <div class="flex justify-between"><dt class="text-on-surface-variant">Fecha y hora:</dt><dd class="font-bold">{{ $detalle->fecha->format('d/m/Y') }} {{ \Illuminate\Support\Carbon::parse($detalle->hora_llegada)->format('H:i') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-on-surface-variant">Personas:</dt><dd class="font-bold">{{ $detalle->personas }}</dd></div>
                        @if ($detalle->mesas->isNotEmpty())
                            <div class="flex justify-between">
                                <dt class="text-on-surface-variant">Mesa(s) asignada(s):</dt>
                                <dd class="font-bold text-primary">
                                    {{ $detalle->mesas->pluck('nombre')->filter()->join(', ') ?: $detalle->mesas->pluck('numero')->map(fn ($n) => 'Mesa #' . $n)->join(', ') }} ({{ $detalle->mesas->sum('capacidad') }} pax)
                                </dd>
                            </div>
                        @endif
                        <div class="flex justify-between"><dt class="text-on-surface-variant">Estado:</dt><dd class="font-bold">{{ $detalle->estado }}</dd></div>
                        <div class="flex justify-between"><dt class="text-on-surface-variant">Origen:</dt><dd class="font-bold uppercase font-mono text-[11px]">{{ $detalle->origen }}</dd></div>
                        @if ($detalle->notas)<div class="flex justify-between"><dt class="text-on-surface-variant">Notas:</dt><dd class="font-bold">{{ $detalle->notas }}</dd></div>@endif
                    </dl>

                    @if ($detalle->estado === 'solicitada')
                        <div class="mt-4 p-3.5 rounded-2xl border border-surface-container-highest bg-surface-container-low space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-on-surface">Asignar Mesa(s)</label>
                                <span class="text-[10px] text-on-surface-variant font-mono">{{ $detalle->personas }} pax requeridos</span>
                            </div>
                            <div class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                                @forelse ($mesasDisponiblesModal as $m)
                                    <label class="flex items-center justify-between p-2.5 rounded-xl bg-surface-container-lowest border border-surface-container-highest hover:border-primary/40 cursor-pointer text-xs transition-colors">
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" wire:model.live="mesaSeleccionadaIds" value="{{ $m->id }}"
                                                class="rounded border-outline-variant/40 text-primary focus:ring-0">
                                            <span class="font-bold text-on-surface">Mesa {{ $m->numero }}</span>
                                            <span class="text-[10px] text-on-surface-variant uppercase font-mono">({{ ucfirst($m->zona) }})</span>
                                        </div>
                                        <span class="text-[11px] font-bold text-primary">{{ $m->capacidad }} pax</span>
                                    </label>
                                @empty
                                    <p class="text-xs text-amber-600 font-bold p-2 bg-amber-500/10 rounded-xl">No hay mesas libres para este horario</p>
                                @endforelse
                            </div>
                            <p class="text-[11px] text-on-surface-variant">Marca una o varias mesas si es un grupo grande. Se auto-asigna si lo dejas vacío.</p>
                        </div>
                    @endif

                    <div class="pt-3 border-t border-surface-container-high flex flex-wrap gap-2">
                        @if (in_array($detalle->estado, ['solicitada', 'confirmada']) && $detalle->estado !== 'confirmada')
                            <button wire:click="confirmar" class="rounded-xl bg-secondary px-4 py-2 text-xs font-bold text-white flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                <span>Confirmar / Tomar</span>
                            </button>
                        @endif
                        @if ($detalle->estado === 'confirmada')
                            <button wire:click="marcarLlego" class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-on-primary cursor-pointer">Llegó</button>
                        @endif
                        @if ($detalle->estado === 'llego')
                            <button wire:click="finalizar" class="rounded-xl bg-surface-container-high px-4 py-2 text-xs font-bold text-white cursor-pointer">Finalizar</button>
                        @endif
                        @if (in_array($detalle->estado, ['solicitada', 'confirmada']))
                            <button wire:click="cancelarReserva" class="rounded-xl bg-error px-4 py-2 text-xs font-bold text-white cursor-pointer">Cancelar</button>
                            <button wire:click="noShow" class="rounded-xl border border-error px-4 py-2 text-xs font-bold text-error cursor-pointer">No se mostró</button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>