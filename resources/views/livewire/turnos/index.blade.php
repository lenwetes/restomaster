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
        $this->cargarProgramacion();
        $this->cargarReglas();
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
        $svc->actualizarCelda($turnoId, ['zona_id' => $zonaId], Auth::user());
    }

    public function with(): array
    {
        $svc = app(TurnoSemanalService::class);
        $matriz = $svc->matrizSemanal($this->programacionId);
        $sucursalId = Auth::user()?->sucursal_id;

        return [
            'dias' => $matriz['dias'],
            'filas' => $matriz['filas'],
            'programacion' => $matriz['programacion'],
            'zonas' => Zona::where('sucursal_id', $sucursalId)->where('activa', true)->orderBy('orden')->get(),
            'plantillas' => PlantillaTurno::where('sucursal_id', $sucursalId)->where('activo', true)->orderBy('id')->get(),
            'proyeccion' => $svc->proyeccionRotacion($sucursalId, $this->semanaIso, $this->anio, 4),
        ];
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
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[26px] text-primary">calendar_month</span>
            <div>
                <h1 class="text-xl font-black tracking-tight text-on-surface">Programación Semanal de Turnos</h1>
                <p class="text-xs text-on-surface-variant font-medium">Matriz mesero × día · Semana ISO {{ $semanaIso }} / {{ $anio }}</p>
            </div>
        </div>
        @if ($estado === 'publicado')
            <span class="rounded-full bg-secondary/15 px-3 py-1 text-xs font-black text-secondary border border-secondary/30">✅ Publicado</span>
        @else
            <span class="rounded-full bg-surface-container-high px-3 py-1 text-xs font-black text-on-surface-variant border border-outline-variant/30">📝 Borrador</span>
        @endif
    </div>
</x-slot>

<div class="space-y-6">
    <!-- Barra superior de acento -->
    <div class="h-1.5 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    <!-- Mensajes de estado -->
    @if (session('status'))
        <div class="rounded-2xl border border-secondary/40 bg-secondary/10 p-4 text-xs font-bold text-secondary flex items-center gap-2 animate-fade-in">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Barra de acciones de la semana -->
    <div class="flex flex-wrap items-center gap-2 p-1.5 rounded-2xl bg-surface-container-low border border-outline-variant/20 shadow-xs animate-fade-in">
        <button type="button" wire:click="semanaAnterior" class="flex items-center gap-1 rounded-xl px-3.5 py-2 text-xs font-bold text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-all cursor-pointer min-h-[44px] min-w-[44px]">
            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
            <span>Anterior</span>
        </button>
        <span class="px-3 py-2 text-xs font-black text-on-surface">S{{ $semanaIso }} · {{ $anio }}</span>
        <button type="button" wire:click="semanaSiguiente" class="flex items-center gap-1 rounded-xl px-3.5 py-2 text-xs font-bold text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-all cursor-pointer min-h-[44px] min-w-[44px]">
            <span>Siguiente</span>
            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
        </button>
        <span class="flex-1"></span>
        <button type="button" wire:click="autoProgramar" class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-on-primary shadow-md hover:bg-primary/90 active:scale-95 transition cursor-pointer min-h-[44px] min-w-[44px]">
            <span class="material-symbols-outlined text-[18px]">bolt</span>
            <span>⚡ Auto-Programar</span>
        </button>
        <button type="button" wire:click="copiarAnterior" class="flex items-center gap-1.5 rounded-xl bg-surface-container-high border border-outline-variant/30 px-4 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-highest active:scale-95 transition cursor-pointer min-h-[44px] min-w-[44px]">
            <span class="material-symbols-outlined text-[18px]">content_copy</span>
            <span>📋 Copiar Anterior</span>
        </button>
        <button type="button" wire:click="publicar" wire:confirm="¿Publicar los horarios de la semana? Los meseros serán notificados." class="flex items-center gap-1.5 rounded-xl bg-secondary px-4 py-2 text-xs font-bold text-white shadow-md hover:bg-secondary/90 active:scale-95 transition cursor-pointer min-h-[44px] min-w-[44px]">
            <span class="material-symbols-outlined text-[18px]">publish</span>
            <span>✅ Publicar Horarios</span>
        </button>
    </div>

    <!-- Reglas de Rotación (Fase 7) -->
    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest shadow-sm animate-fade-in overflow-hidden">
        <div class="border-b border-outline-variant/15 px-5 py-3">
            <h2 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[18px]">autorenew</span>
                Reglas de Rotación
            </h2>
            <p class="text-[11px] text-on-surface-variant font-medium mt-0.5">Equidad entre semanas: evita repetir zona y rota los descansos por demanda.</p>
        </div>
        <form wire:submit="guardarReglas" class="p-5 grid grid-cols-1 lg:grid-cols-12 gap-4">
            <label class="lg:col-span-4 flex items-center gap-2.5 p-3 rounded-2xl bg-surface-container-low border border-outline-variant/20 cursor-pointer min-h-[44px]">
                <input type="checkbox" wire:model.live="reglasForm.impedir_zona_repetida" class="rounded text-primary focus:ring-0 w-5 h-5 shrink-0" />
                <span class="text-xs font-bold text-on-surface">Impedir zona repetida en semana consecutiva</span>
            </label>
            <div class="lg:col-span-2">
                <label class="text-xs font-bold text-on-surface-variant">Longitud del ciclo</label>
                <select wire:model="reglasForm.longitud_ciclo" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-xs text-on-surface focus:border-primary focus:ring-0 min-h-[44px]">
                    @for ($i = 2; $i <= 8; $i++)
                        <option value="{{ $i }}">{{ $i }} semanas</option>
                    @endfor
                </select>
                @error('reglasForm.longitud_ciclo')
                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="lg:col-span-2">
                <label class="text-xs font-bold text-on-surface-variant">Ciclo de descansos</label>
                <select wire:model="reglasForm.semanas_ciclo_descanso" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-xs text-on-surface focus:border-primary focus:ring-0 min-h-[44px]">
                    @for ($i = 2; $i <= 8; $i++)
                        <option value="{{ $i }}">{{ $i }} semanas</option>
                    @endfor
                </select>
                @error('reglasForm.semanas_ciclo_descanso')
                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="lg:col-span-4">
                <span class="text-xs font-bold text-on-surface-variant">Días de alta demanda</span>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    @foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $num => $nombre)
                        <label class="inline-flex items-center gap-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface cursor-pointer min-h-[44px]">
                            <input type="checkbox" wire:model.live="reglasForm.dias_alta_demanda" value="{{ $num }}" class="rounded text-primary focus:ring-0 w-4 h-4" />
                            <span>{{ $nombre }}</span>
                        </label>
                    @endforeach
                </div>
                @error('reglasForm.dias_alta_demanda')
                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="lg:col-span-12 flex flex-wrap items-center gap-2 pt-1">
                <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-on-primary shadow-md hover:bg-primary/90 active:scale-95 transition cursor-pointer min-h-[44px] min-w-[44px]">
                    Guardar reglas
                </button>
                <button type="button" wire:click="equilibrarDescansos" class="rounded-xl bg-surface-container-high border border-outline-variant/30 px-5 py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-highest active:scale-95 transition cursor-pointer min-h-[44px] min-w-[44px]">
                    Equilibrar descansos
                </button>
            </div>
        </form>
    </div>

    <!-- Proyección de Descansos — 4 semanas -->
    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest shadow-sm animate-fade-in overflow-hidden">
        <div class="border-b border-outline-variant/15 px-5 py-3">
            <h2 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[18px]">preview</span>
                Proyección de Descansos — 4 semanas
            </h2>
            <p class="text-[11px] text-on-surface-variant font-medium mt-0.5">Rotación de zonas y descansos por nivel de demanda: 🟢 baja · 🟡 media · 🔴 alta.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-outline-variant/15 bg-surface-container-low/50">
                        <th class="text-left px-5 py-2.5 text-xs font-black uppercase tracking-wider text-on-surface-variant">Mesero</th>
                        @foreach ((collect($proyeccion)->first()['semanas'] ?? []) as $semHeader)
                            <th class="text-center px-2 py-2.5 text-xs font-black text-on-surface">S{{ $semHeader['semana_iso'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($proyeccion as $item)
                        <tr class="border-b border-outline-variant/10 last:border-0">
                            <td class="px-5 py-2.5 text-xs font-bold text-on-surface">{{ $item['mesero']->name }}</td>
                            @foreach ($item['semanas'] as $sem)
                                <td class="text-center px-2 py-2.5">
                                    @if ($sem['descanso'])
                                        @php
                                            $diasCortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
                                            $emojiNivel = ['alta' => '🔴', 'media' => '🟡', 'baja' => '🟢'][$sem['descanso']['nivel']] ?? '';
                                        @endphp
                                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-container px-2.5 py-1 text-xs font-bold text-on-surface border border-outline-variant/30">
                                            <span>😴 {{ $diasCortos[$sem['descanso']['dia_iso']] ?? '' }}</span>
                                            <span>{{ $emojiNivel }}</span>
                                        </span>
                                    @else
                                        <span class="text-xs text-on-surface-variant/50">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-6 text-center text-xs text-on-surface-variant">Sin meseros activos para proyectar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Matriz mesero × día -->
    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest shadow-sm animate-fade-in overflow-hidden">
        <div class="border-b border-outline-variant/15 px-5 py-3 flex items-center justify-between gap-2">
            <h2 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[18px]">grid_view</span>
                Matriz de turnos por zona
            </h2>
            <p class="text-[11px] text-on-surface-variant font-medium">Toca una celda para cambiar la zona o marcar 😴 descanso</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-sm">
                <thead>
                    <tr class="border-b border-outline-variant/15 bg-surface-container-low/50">
                        <th class="text-left px-5 py-3 text-xs font-black uppercase tracking-wider text-on-surface-variant sticky left-0 bg-surface-container-low z-10">Mesero</th>
                        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $i => $nombreDia)
                            <th class="text-center px-2 py-3 min-w-[104px]">
                                <span class="block text-sm font-black text-on-surface">{{ $nombreDia }}</span>
                                <span class="inline-block mt-0.5 rounded-full bg-surface-container-high px-2 py-0.5 text-[10px] font-bold text-on-surface-variant">{{ $dias[$i]->format('d/m') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $fila)
                        @php
                            $celdas = collect($fila['celdas'])->filter();
                            $numDescansos = $celdas->where('es_descanso', true)->count();
                            $numTurnos = $celdas->where('es_descanso', false)->count();
                        @endphp
                        <tr class="border-b border-outline-variant/10 last:border-0 hover:bg-surface-container-low/40 transition-colors">
                            <td class="px-5 py-3 sticky left-0 bg-surface-container-lowest z-10">
                                <span class="block text-sm font-bold text-on-surface">{{ $fila['mesero']->name }}</span>
                                <span class="block text-[10px] font-medium text-on-surface-variant mt-0.5">{{ $numTurnos }} turnos · {{ $numDescansos }} descansos</span>
                            </td>
                            @foreach ($dias as $dia)
                                @php $celda = $fila['celdas'][$dia->toDateString()] ?? null; @endphp
                                <td class="text-center px-2 py-2 align-top">
                                    @if ($celda === null)
                                        <span class="inline-block px-2 py-2.5 text-sm text-on-surface-variant/50">—</span>
                                    @elseif ($celda->es_descanso)
                                        <button type="button" wire:click="alternarDescanso({{ $celda->id }})" title="Quitar descanso y reasignar" class="w-full rounded-xl bg-surface-container border border-outline-variant/30 px-2 py-2.5 text-sm cursor-pointer min-h-[44px] hover:border-secondary/50 active:scale-95 transition">
                                            <span>😴</span>
                                            <span class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant mt-0.5">Descanso</span>
                                        </button>
                                    @else
                                        @php $tinte = \App\Models\Zona::PALETA[$celda->zona?->color ?? ''] ?? ['tinte' => 'bg-primary-container/40', 'punto' => 'bg-primary']; @endphp
                                        <div class="rounded-xl border border-outline-variant/20 {{ $tinte['tinte'] }} px-2 py-2 space-y-1.5">
                                            <span class="flex items-center justify-center gap-1.5 text-xs font-bold text-on-surface">
                                                <span class="w-2 h-2 rounded-full {{ $tinte['punto'] }} shrink-0"></span>
                                                <span class="truncate">{{ $celda->zona?->nombre ?? 'Sin zona' }}</span>
                                            </span>
                                            @if ($celda->plantilla)
                                                <span class="block text-[10px] font-medium text-on-surface-variant">{{ $celda->plantilla->nombre }}</span>
                                            @endif
                                            <div class="flex items-center justify-center gap-1 pt-0.5">
                                                <select wire:change="cambiarZona({{ $celda->id }}, $event.target.value)" class="max-w-full rounded-lg border border-outline-variant/30 bg-surface-container-lowest px-1 py-1.5 text-[11px] text-on-surface focus:border-primary focus:ring-0 min-h-[44px]" aria-label="Cambiar zona de {{ $fila['mesero']->name }}">
                                                    @foreach ($zonas as $zona)
                                                        <option value="{{ $zona->id }}" @selected($celda->zona_id === $zona->id)>{{ $zona->nombre }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" wire:click="alternarDescanso({{ $celda->id }})" title="Marcar descanso" class="rounded-lg px-2 py-1.5 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high cursor-pointer min-h-[44px] min-w-[44px] active:scale-95 transition" aria-label="Marcar descanso">
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
                            <td colspan="8" class="px-5 py-10 text-center">
                                <span class="material-symbols-outlined text-[36px] text-on-surface-variant">calendar_add_on</span>
                                <p class="text-sm font-bold text-on-surface mt-2">Semana vacía, sin meseros activos</p>
                                <p class="text-xs text-on-surface-variant mt-1">Genera la programación equitativa con un solo toque.</p>
                                <button type="button" wire:click="autoProgramar" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-on-primary shadow-md hover:bg-primary/90 active:scale-95 transition cursor-pointer min-h-[44px]">
                                    <span class="material-symbols-outlined text-[18px]">bolt</span>
                                    <span>⚡ Auto-Programar semana</span>
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
