<?php

use App\Models\Reserva;
use App\Services\ReservaService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $fecha = '';
    public string $filtrarEstado = 'todas';
    public ?int $reservaSeleccionada = null;
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

        return [
            'reservas' => $delDia
                ->when($this->filtrarEstado !== 'todas', fn ($col) => $col->where('estado', $this->filtrarEstado)),
            'conteo' => [
                'solicitadas' => $delDia->where('estado', 'solicitada')->count(),
                'confirmadas' => $delDia->where('estado', 'confirmada')->count(),
                'canceladas' => $delDia->where('estado', 'cancelada')->count(),
            ],
            'disponibles' => $service->verificarDisponibilidad($this->fecha, $this->crearForm['hora_llegada'], (int) $this->crearForm['personas']),
        ];
    }

    public function abrirCrear(): void
    {
        $this->modalCrear = true;
    }

    public function crearReserva(): void
    {
        $validated = $this->validate([
            'crearForm.nombre_contacto' => ['required', 'string', 'max:255'],
            'crearForm.telefono_contacto' => ['required', 'string', 'max:30'],
            'crearForm.fecha' => ['required', 'date', 'after_or_equal:' . now()->toDateString()],
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
    }

    public function confirmar(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->confirmar($reserva);
        session()->flash('status', 'Reserva confirmada.');
    }

    public function marcarLlego(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->marcarLlego($reserva);
        session()->flash('status', 'Cliente en la mesa.');
    }

    public function finalizar(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->finalizar($reserva);
        session()->flash('status', 'Reserva finalizada.');
    }

    public function cancelarReserva(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->cancelar($reserva);
        session()->flash('status', 'Reserva cancelada.');
    }

    public function noShow(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->marcarNoShow($reserva);
        session()->flash('status', 'Sin presentarse.');
    }

    public function cerrarDetalle(): void
    {
        $this->reset('reservaSeleccionada');
    }
}; ?>

<x-slot name="header">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">event_available</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Reservas</h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">RES-01</span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">Agenda del día, confirmación y bloqueo de mesas</p>
        </div>
        <button wire:click="abrirCrear" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary">
            <span class="material-symbols-outlined text-[18px]">add</span> Nueva reserva
        </button>
    </div>
</x-slot>

<div class="space-y-6">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @if (session('status'))
        <div class="rounded-xl border border-secondary/40 bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <input type="date" wire:model.live="fecha" wire:change="cambiarFecha"
            class="rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
        <select wire:model.live="filtrarEstado" class="rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
            <option value="todas">Todos los estados</option>
            <option value="solicitada">Solicitadas</option>
            <option value="confirmada">Confirmadas</option>
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
        <p class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 text-xs text-on-surface-variant">Sin reservas para esta fecha.</p>
    @else
        <div class="space-y-3">
            @foreach ($reservas as $reserva)
                <button wire:click="abrirDetalle({{ $reserva->id }})" class="w-full rounded-2xl border border-outline-variant/10 bg-surface-container-low p-4 text-left">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-extrabold text-on-surface">{{ $reserva->nombre_contacto }} · {{ \Illuminate\Support\Carbon::parse($reserva->hora_llegada)->format('H:i') }}</p>
                            <p class="text-xs text-on-surface-variant">{{ $reserva->personas }} personas · {{ $reserva->mesas->pluck('nombre')->join(', ') ?: $reserva->mesas->pluck('numero')->map(fn ($n) => 'Mesa #' . $n)->join(', ') }}</p>
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
                <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary">Crear reserva</button>
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
                <button wire:click="cerrarDetalle" class="text-on-surface-variant"><span class="material-symbols-outlined">close</span></button>
            </div>
            <dl class="mt-4 space-y-2 text-xs">
                <div class="flex justify-between"><dt class="text-on-surface-variant">Teléfono</dt><dd class="font-bold">{{ $detalle->telefono_contacto }}</dd></div>
                <div class="flex justify-between"><dt class="text-on-surface-variant">Fecha y hora</dt><dd class="font-bold">{{ $detalle->fecha->format('d/m/Y') }} {{ \Illuminate\Support\Carbon::parse($detalle->hora_llegada)->format('H:i') }}</dd></div>
                <div class="flex justify-between"><dt class="text-on-surface-variant">Personas</dt><dd class="font-bold">{{ $detalle->personas }}</dd></div>
                <div class="flex justify-between"><dt class="text-on-surface-variant">Estado</dt><dd class="font-bold">{{ $detalle->estado }}</dd></div>
                <div class="flex justify-between"><dt class="text-on-surface-variant">Origen</dt><dd class="font-bold">{{ $detalle->origen }}</dd></div>
                @if ($detalle->notas)<div class="flex justify-between"><dt class="text-on-surface-variant">Notas</dt><dd class="font-bold">{{ $detalle->notas }}</dd></div>@endif
            </dl>
            <div class="mt-5 flex flex-wrap gap-2">
                @if (in_array($detalle->estado, ['solicitada', 'confirmada']) && $detalle->estado !== 'confirmada')
                    <button wire:click="confirmar" class="rounded-xl bg-secondary px-4 py-2 text-xs font-bold text-white">Confirmar</button>
                @endif
                @if ($detalle->estado === 'confirmada')
                    <button wire:click="marcarLlego" class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-on-primary">Llegó</button>
                @endif
                @if ($detalle->estado === 'llego')
                    <button wire:click="finalizar" class="rounded-xl bg-surface-container-high px-4 py-2 text-xs font-bold text-white">Finalizar</button>
                @endif
                @if (in_array($detalle->estado, ['solicitada', 'confirmada']))
                    <button wire:click="cancelarReserva" class="rounded-xl bg-error px-4 py-2 text-xs font-bold text-white">Cancelar</button>
                    <button wire:click="noShow" class="rounded-xl border border-error px-4 py-2 text-xs font-bold text-error">No se mostró</button>
                @endif
            </div>
        </div>
    </div>
@endif