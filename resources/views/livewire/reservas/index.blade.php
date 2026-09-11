<?php

use App\Models\Reserva;
use App\Services\ReservaService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $fecha = '';

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
        $this->fecha = now()->toDateString();
        $this->crearForm['fecha'] = now()->toDateString();
    }

    public function cambiarFecha(): void
    {
        $this->fecha = $this->fecha ?: now()->toDateString();
    }

    public function with(): array
    {
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
        ];
    }

    public function abrirCrear(): void
    {
        $this->modalCrear = true;
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
        session()->flash('status', 'Reserva creada.');
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

<div class="space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">event_available</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Reservas</h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">RES-01</span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">Agenda del día, confirmación y bloqueo de mesas</p>
        </div>
        <button wire:click="abrirCrear" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary cursor-pointer">
            <span class="material-symbols-outlined text-[18px]">add</span> Nueva reserva
        </button>
    </header>

    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @if (session('status'))
        <div class="rounded-xl border border-secondary/40 bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary">{{ session('status') }}</div>
    @endif

    @if ($solicitudesPendientesGlobales > 0 && $filtrarEstado !== 'solicitadas_pendientes')
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-2xl border border-primary/30 bg-primary/10 p-4">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-2xl animate-pulse">notifications_active</span>
                <div>
                    <p class="text-xs font-black text-on-surface">Tienes {{ $solicitudesPendientesGlobales }} {{ $solicitudesPendientesGlobales === 1 ? 'solicitud' : 'solicitudes' }} de reserva web pendiente(s) por tomar/confirmar.</p>
                    <p class="text-[11px] text-on-surface-variant">Clientes no registrados o reservas en línea esperando confirmación.</p>
                </div>
            </div>
            <button wire:click="$set('filtrarEstado', 'solicitadas_pendientes')" class="px-3.5 py-1.5 rounded-xl bg-primary text-white text-xs font-black hover:bg-primary/90 transition-all cursor-pointer whitespace-nowrap">
                Ver solicitudes pendientes
            </button>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <input type="date" wire:model.live="fecha" wire:change="cambiarFecha"
            class="rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
        <select wire:model.live="filtrarEstado" class="rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
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
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-4">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p>
                <p class="mt-1 text-2xl font-black text-on-surface">{{ $conteo[$k] }}</p>
            </div>
        @endforeach
    </div>

    @if ($reservas->isEmpty())
        <p class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 text-xs text-on-surface-variant">Sin reservas para este criterio de búsqueda.</p>
    @else
        <div class="space-y-3">
            @foreach ($reservas as $reserva)
                <button wire:click="abrirDetalle({{ $reserva->id }})" class="w-full rounded-2xl border border-outline-variant/10 bg-surface-container-low p-4 text-left cursor-pointer hover:border-primary/30 transition-colors">
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

    @if ($modalCrear)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="$set('modalCrear', false)">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl">
                <h3 class="text-base font-extrabold text-on-surface">Nueva reserva</h3>
                <form wire:submit="crearReserva" class="mt-4 space-y-4">
                    <div><label class="text-xs font-bold text-on-surface-variant">Nombre del cliente</label>
                        <input type="text" wire:model="crearForm.nombre_contacto" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                        @error('crearForm.nombre_contacto') <p class="mt-1 text-xs font-bold text-error">{{ $message }}</p> @enderror</div>
                    <div><label class="text-xs font-bold text-on-surface-variant">Teléfono</label>
                        <input type="text" wire:model="crearForm.telefono_contacto" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                        @error('crearForm.telefono_contacto') <p class="mt-1 text-xs font-bold text-error">{{ $message }}</p> @enderror</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="text-xs font-bold text-on-surface-variant">Fecha</label>
                            <input type="date" wire:model.live="crearForm.fecha" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                        <div><label class="text-xs font-bold text-on-surface-variant">Hora</label>
                            <input type="time" wire:model.live="crearForm.hora_llegada" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                    </div>
                    <div><label class="text-xs font-bold text-on-surface-variant">Personas</label>
                        <input type="number" min="1" wire:model.live="crearForm.personas" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                    <p class="text-[11px] text-on-surface-variant">Mesas disponibles: {{ $disponibles->map(fn ($m) => $m->numero . 'PK' . $m->capacidad)->join(' · ') ?: 'ninguna para este horario' }}</p>
                    <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary cursor-pointer">Crear reserva</button>
                </form>
            </div>
        </div>
    @endif

    @if ($reservaSeleccionada)
        @php $detalle = \App\Models\Reserva::with(['mesas', 'confirmadoPor'])->find($reservaSeleccionada); @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="cerrarDetalle">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-on-surface">{{ $detalle->nombre_contacto }}</h3>
                    <button wire:click="cerrarDetalle" class="text-on-surface-variant cursor-pointer"><span class="material-symbols-outlined">close</span></button>
                </div>

                @if ($errorModal)
                    <div class="mt-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-rose-600 text-base">error</span>
                        <span>{{ $errorModal }}</span>
                    </div>
                @endif

                <dl class="mt-4 space-y-2 text-xs">
                    <div class="flex justify-between"><dt class="text-on-surface-variant">Teléfono</dt><dd class="font-bold">{{ $detalle->telefono_contacto }}</dd></div>
                    <div class="flex justify-between"><dt class="text-on-surface-variant">Fecha y hora</dt><dd class="font-bold">{{ $detalle->fecha->format('d/m/Y') }} {{ \Illuminate\Support\Carbon::parse($detalle->hora_llegada)->format('H:i') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-on-surface-variant">Personas</dt><dd class="font-bold">{{ $detalle->personas }}</dd></div>
                    @if ($detalle->mesas->isNotEmpty())
                        <div class="flex justify-between">
                            <dt class="text-on-surface-variant">Mesa(s) asignada(s)</dt>
                            <dd class="font-bold text-primary">
                                {{ $detalle->mesas->pluck('nombre')->filter()->join(', ') ?: $detalle->mesas->pluck('numero')->map(fn ($n) => 'Mesa #' . $n)->join(', ') }} ({{ $detalle->mesas->sum('capacidad') }} pax)
                            </dd>
                        </div>
                    @endif
                    <div class="flex justify-between"><dt class="text-on-surface-variant">Estado</dt><dd class="font-bold">{{ $detalle->estado }}</dd></div>
                    <div class="flex justify-between"><dt class="text-on-surface-variant">Origen</dt><dd class="font-bold uppercase font-mono text-[11px]">{{ $detalle->origen }}</dd></div>
                    @if ($detalle->notas)<div class="flex justify-between"><dt class="text-on-surface-variant">Notas</dt><dd class="font-bold">{{ $detalle->notas }}</dd></div>@endif
                </dl>

                @if ($detalle->estado === 'solicitada')
                    <div class="mt-4 p-3.5 rounded-2xl border border-outline-variant/20 bg-surface-container-low space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">Asignar Mesa(s)</label>
                            <span class="text-[10px] text-on-surface-variant font-mono">{{ $detalle->personas }} pax requeridos</span>
                        </div>
                        <div class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                            @forelse ($mesasDisponiblesModal as $m)
                                <label class="flex items-center justify-between p-2.5 rounded-xl bg-surface-container-lowest border border-outline-variant/20 hover:border-primary/40 cursor-pointer text-xs transition-colors">
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

                <div class="mt-5 flex flex-wrap gap-2">
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
        </div>
    @endif
</div>