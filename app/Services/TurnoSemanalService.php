<?php

namespace App\Services;

use App\Events\HorarioSemanalPublicado;
use App\Models\Mesa;
use App\Models\NotificacionUsuario;
use App\Models\PlantillaTurno;
use App\Models\ProgramacionSemanal;
use App\Models\TurnoMeseroSemana;
use App\Models\User;
use App\Models\Zona;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TurnoSemanalService
{
    public function __construct(
        protected RotacionMeseroService $rotacionService,
        protected ConfiguracionService $configService
    ) {}

    /**
     * Obtiene la programación de la semana ISO o la crea en borrador (idempotente).
     */
    public function obtenerOCrearSemana(int $sucursalId, int $semanaIso, int $anio, User $usuario): ProgramacionSemanal
    {
        $this->autorizar($usuario);

        if ($semanaIso < 1 || $semanaIso > 53) {
            throw new \InvalidArgumentException('La semana ISO debe estar entre 1 y 53.');
        }

        if ($anio < 2000 || $anio > 2100) {
            throw new \InvalidArgumentException('El año no es válido.');
        }

        return ProgramacionSemanal::firstOrCreate(
            ['sucursal_id' => $sucursalId, 'semana_iso' => $semanaIso, 'anio' => $anio],
            ['estado' => ProgramacionSemanal::ESTADO_BORRADOR]
        );
    }

    /**
     * Motor de auto-programación equitativa: reparte zonas y plantillas en round-robin.
     *
     * Con $respetarHoy en la semana actual solo completa desde hoy hasta el domingo,
     * sin tocar los días ya transcurridos.
     *
     * @return int cantidad de turnos creados
     */
    public function autoProgramar(int $programacionId, User $usuario, bool $respetarHoy = true): int
    {
        $this->autorizar($usuario);

        $programacion = ProgramacionSemanal::findOrFail($programacionId);

        if ($programacion->estado === ProgramacionSemanal::ESTADO_ARCHIVADO) {
            throw new \InvalidArgumentException('No se puede programar una semana archivada.');
        }

        $meseros = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'mesero'))
            ->where('sucursal_id', $programacion->sucursal_id)
            ->where('activo', true)
            ->orderBy('id')
            ->get();

        if ($meseros->isEmpty()) {
            return 0;
        }

        $zonas = Zona::query()
            ->where('sucursal_id', $programacion->sucursal_id)
            ->where('activa', true)
            ->orderBy('orden')
            ->get();

        if ($zonas->isEmpty()) {
            throw new \InvalidArgumentException('La sucursal no tiene zonas activas para programar.');
        }

        $plantillas = PlantillaTurno::query()
            ->where('sucursal_id', $programacion->sucursal_id)
            ->where('activo', true)
            ->orderBy('id')
            ->get();

        $lunes = Carbon::now()->setISODate($programacion->anio, $programacion->semana_iso)->startOfDay();
        $domingo = $lunes->copy()->addDays(6)->endOfDay();

        $inicio = $lunes->copy();
        if ($respetarHoy) {
            $hoy = Carbon::today();
            if ($hoy->between($lunes, $domingo)) {
                $inicio = $hoy->copy()->startOfDay();
            } elseif ($hoy->greaterThan($domingo)) {
                return 0;
            }
        }

        $reglas = $this->obtenerReglasRotacion($programacion->sucursal_id);
        $historial = $reglas['impedir_zona_repetida']
            ? $this->historialZonas($programacion->sucursal_id, $programacion->semana_iso, $programacion->anio, $reglas['longitud_ciclo'])
            : [];

        $asignacion = $this->calcularSemana($meseros, $zonas, $plantillas, $programacion->semana_iso, $inicio, $domingo, $reglas, $historial);

        $creados = 0;

        DB::transaction(function () use ($programacion, $inicio, $domingo, $asignacion, &$creados) {
            $dia = $inicio->copy();

            while ($dia->lessThanOrEqualTo($domingo)) {
                $fechaStr = $dia->toDateString();

                TurnoMeseroSemana::where('programacion_semanal_id', $programacion->id)
                    ->whereDate('fecha', $fechaStr)
                    ->delete();

                foreach ($asignacion[$fechaStr] ?? [] as $fila) {
                    TurnoMeseroSemana::create([
                        'programacion_semanal_id' => $programacion->id,
                        'user_id' => $fila['user_id'],
                        'fecha' => $fechaStr,
                        'zona_id' => $fila['zona_id'],
                        'plantilla_turno_id' => $fila['plantilla_turno_id'],
                        'es_descanso' => $fila['es_descanso'],
                    ]);
                    $creados++;
                }

                $dia->addDay();
            }
        });

        return $creados;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FASE 7 — Autorrotación equitativa (no-repetición + ciclo de descansos)
    // ─────────────────────────────────────────────────────────────────────────

    public const GRUPO_REGLAS = 'turnos_rotacion';

    /**
     * Reglas de rotación de la sucursal con valores por defecto del plan.
     *
     * @return array{impedir_zona_repetida: bool, longitud_ciclo: int, dias_alta_demanda: array<int, int>, semanas_ciclo_descanso: int}
     */
    public function obtenerReglasRotacion(int $sucursalId): array
    {
        $grupo = self::GRUPO_REGLAS."_{$sucursalId}";
        $numZonas = Zona::where('sucursal_id', $sucursalId)->where('activa', true)->count();

        $ciclo = (int) $this->configService->obtener($grupo, 'longitud_ciclo', min(8, max(2, $numZonas ?: 3)));
        $semanas = (int) $this->configService->obtener($grupo, 'semanas_ciclo_descanso', 4);
        $diasAlta = $this->configService->obtener($grupo, 'dias_alta_demanda', [5, 6, 7]);

        if (! is_array($diasAlta)) {
            $diasAlta = [5, 6, 7];
        }
        $diasAlta = array_values(array_unique(array_filter(array_map('intval', $diasAlta), fn ($d) => $d >= 1 && $d <= 7)));
        sort($diasAlta);

        return [
            'impedir_zona_repetida' => (bool) $this->configService->obtener($grupo, 'impedir_zona_repetida', true),
            'longitud_ciclo' => min(8, max(2, $ciclo)),
            'dias_alta_demanda' => $diasAlta !== [] ? $diasAlta : [5, 6, 7],
            'semanas_ciclo_descanso' => min(8, max(2, $semanas)),
        ];
    }

    /**
     * Guarda las reglas de rotación (solo admin/gerente).
     */
    public function guardarReglasRotacion(int $sucursalId, array $datos, User $usuario): array
    {
        $this->autorizar($usuario);

        $grupo = self::GRUPO_REGLAS."_{$sucursalId}";

        if (array_key_exists('impedir_zona_repetida', $datos)) {
            $this->configService->guardar($grupo, 'impedir_zona_repetida', (bool) $datos['impedir_zona_repetida']);
        }

        if (array_key_exists('longitud_ciclo', $datos)) {
            $ciclo = (int) $datos['longitud_ciclo'];
            if ($ciclo < 2 || $ciclo > 8) {
                throw new \InvalidArgumentException('La longitud del ciclo debe estar entre 2 y 8 semanas.');
            }
            $this->configService->guardar($grupo, 'longitud_ciclo', $ciclo);
        }

        if (array_key_exists('dias_alta_demanda', $datos)) {
            $dias = is_array($datos['dias_alta_demanda']) ? $datos['dias_alta_demanda'] : [];
            $dias = array_values(array_unique(array_filter(array_map('intval', $dias), fn ($d) => $d >= 1 && $d <= 7)));
            if ($dias === []) {
                throw new \InvalidArgumentException('Debes marcar al menos un día de alta demanda.');
            }
            sort($dias);
            $this->configService->guardar($grupo, 'dias_alta_demanda', $dias);
        }

        if (array_key_exists('semanas_ciclo_descanso', $datos)) {
            $semanas = (int) $datos['semanas_ciclo_descanso'];
            if ($semanas < 2 || $semanas > 8) {
                throw new \InvalidArgumentException('El ciclo de descansos debe estar entre 2 y 8 semanas.');
            }
            $this->configService->guardar($grupo, 'semanas_ciclo_descanso', $semanas);
        }

        return $this->obtenerReglasRotacion($sucursalId);
    }

    /**
     * Nivel de demanda de un día ISO (1=lunes … 7=domingo).
     */
    public function nivelDemanda(int $diaIso, array $reglas): string
    {
        if (in_array($diaIso, $reglas['dias_alta_demanda'], true)) {
            return 'alta';
        }

        if ($diaIso === 3 || $diaIso === 4) {
            return 'media';
        }

        return 'baja';
    }

    /**
     * Historial de zonas trabajadas por mesero y día de semana en las últimas
     * N semanas ISO anteriores a la semana de referencia.
     *
     * @return array<int, array<int, array<int, int>>> [meseroId][diaIso] => [zonaIds]
     */
    public function historialZonas(int $sucursalId, int $semanaIso, int $anio, int $semanas): array
    {
        $referencia = Carbon::now()->setISODate($anio, $semanaIso);
        $pares = [];

        for ($i = 1; $i <= $semanas; $i++) {
            $pasada = $referencia->copy()->subWeeks($i);
            $pares[] = [(int) $pasada->isoWeek, (int) $pasada->isoWeekYear];
        }

        $turnos = TurnoMeseroSemana::query()
            ->join('programaciones_semanales', 'turnos_meseros_semana.programacion_semanal_id', '=', 'programaciones_semanales.id')
            ->where('programaciones_semanales.sucursal_id', $sucursalId)
            ->where(function ($q) use ($pares) {
                foreach ($pares as [$s, $a]) {
                    $q->orWhere(fn ($w) => $w->where('programaciones_semanales.semana_iso', $s)->where('programaciones_semanales.anio', $a));
                }
            })
            ->where('turnos_meseros_semana.es_descanso', false)
            ->whereNotNull('turnos_meseros_semana.zona_id')
            ->get(['turnos_meseros_semana.user_id', 'turnos_meseros_semana.fecha', 'turnos_meseros_semana.zona_id']);

        $historial = [];
        foreach ($turnos as $turno) {
            $diaIso = (int) Carbon::parse($turno->fecha)->dayOfWeekIso;
            $historial[$turno->user_id][$diaIso][] = (int) $turno->zona_id;
        }

        foreach ($historial as $meseroId => $dias) {
            foreach ($dias as $diaIso => $zonas) {
                $historial[$meseroId][$diaIso] = array_values(array_unique($zonas));
            }
        }

        return $historial;
    }

    /**
     * Día ISO de descanso del mesero según el ciclo con offset rotativo.
     * Retorna null si el día queda fuera del rango a programar.
     */
    public function diaDescansoSemana(int $indiceMesero, int $semanaIso, array $reglas, Carbon $lunes, ?Carbon $inicioRango = null): ?int
    {
        $ciclo = $reglas['semanas_ciclo_descanso'];
        $posicion = ($semanaIso + $indiceMesero) % $ciclo;
        $nivel = match (true) {
            $posicion === 0 => 'baja',
            $posicion === 1 => 'media',
            default => 'alta',
        };

        if ($nivel === 'alta') {
            $diasNivel = $reglas['dias_alta_demanda'];
        } elseif ($nivel === 'media') {
            $diasNivel = [3, 4];
        } else {
            $diasNivel = array_values(array_diff([1, 2, 3, 4, 5, 6, 7], $reglas['dias_alta_demanda'], [3, 4]));
            sort($diasNivel);
        }

        if ($diasNivel === []) {
            return null;
        }

        $diaIso = $diasNivel[($indiceMesero + $posicion) % count($diasNivel)];
        $fecha = $lunes->copy()->addDays($diaIso - 1);

        if ($inicioRango && $fecha->lessThan($inicioRango->copy()->startOfDay())) {
            return null;
        }

        return $diaIso;
    }

    /**
     * Cálculo puro de una semana: [fechaYmd => filas] reutilizado por
     * autoProgramar (persiste) y proyeccionRotacion (simula).
     *
     * @return array<string, array<int, array{user_id: int, zona_id: ?int, plantilla_turno_id: ?int, es_descanso: bool}>>
     */
    public function calcularSemana($meseros, $zonas, $plantillas, int $semanaIso, Carbon $inicio, Carbon $domingo, array $reglas, array $historial): array
    {
        $numZonas = $zonas->count();
        $numPlantillas = max(1, $plantillas->count());
        $lunes = Carbon::now()->setISODate($inicio->isoWeekYear, $inicio->isoWeek)->startOfDay();
        if ($lunes->greaterThan($inicio)) {
            $lunes = $inicio->copy()->startOfWeek();
        }

        $descansos = [];
        foreach ($meseros as $indiceMesero => $mesero) {
            $diaIso = $this->diaDescansoSemana($indiceMesero, $semanaIso, $reglas, $lunes, $inicio);
            if ($diaIso !== null) {
                $descansos[$mesero->id] = $diaIso;
            }
        }

        $carga = [];
        foreach ($zonas as $zona) {
            $carga[$zona->id] = 0;
        }

        $asignacion = [];
        $dia = $inicio->copy();
        $indiceDia = $inicio->copy()->dayOfWeekIso - 1;

        while ($dia->lessThanOrEqualTo($domingo)) {
            $fechaStr = $dia->toDateString();
            $diaIso = (int) $dia->dayOfWeekIso;
            $asignacion[$fechaStr] = [];

            foreach ($meseros as $indiceMesero => $mesero) {
                if (($descansos[$mesero->id] ?? null) === $diaIso) {
                    $asignacion[$fechaStr][] = [
                        'user_id' => $mesero->id,
                        'zona_id' => null,
                        'plantilla_turno_id' => null,
                        'es_descanso' => true,
                    ];

                    continue;
                }

                if ($reglas['impedir_zona_repetida']) {
                    $usadas = $historial[$mesero->id][$diaIso] ?? [];
                    $candidatas = $zonas->filter(fn ($z) => ! in_array($z->id, $usadas, true));
                    if ($candidatas->isEmpty()) {
                        $candidatas = $zonas;
                    }
                    $zona = $candidatas->sortBy(fn ($z) => [$carga[$z->id] ?? 0, $z->orden])->first();
                } else {
                    $zona = $zonas[($indiceMesero + $indiceDia) % $numZonas];
                }

                $plantilla = $plantillas->count() > 0
                    ? $plantillas[($indiceMesero + $indiceDia) % $numPlantillas]
                    : null;

                $carga[$zona->id] = ($carga[$zona->id] ?? 0) + 1;

                $asignacion[$fechaStr][] = [
                    'user_id' => $mesero->id,
                    'zona_id' => $zona->id,
                    'plantilla_turno_id' => $plantilla?->id,
                    'es_descanso' => false,
                ];
            }

            $dia->addDay();
            $indiceDia++;
        }

        return $asignacion;
    }

    /**
     * Proyección de rotación y descansos N semanas hacia adelante (sin persistir).
     */
    public function proyeccionRotacion(int $sucursalId, int $semanaIso, int $anio, int $semanas = 4): array
    {
        $reglas = $this->obtenerReglasRotacion($sucursalId);

        $meseros = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'mesero'))
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('id')
            ->get();

        $zonas = Zona::query()
            ->where('sucursal_id', $sucursalId)
            ->where('activa', true)
            ->orderBy('orden')
            ->get();

        $plantillas = PlantillaTurno::query()
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('id')
            ->get();

        $porId = $zonas->keyBy('id');
        $resultado = [];

        foreach ($meseros as $indiceMesero => $mesero) {
            $resultado[] = ['mesero' => $mesero, 'semanas' => []];
            $historial = $reglas['impedir_zona_repetida']
                ? $this->historialZonas($sucursalId, $semanaIso, $anio, $reglas['longitud_ciclo'])
                : [];

            for ($w = 0; $w < $semanas; $w++) {
                $base = Carbon::now()->setISODate($anio, $semanaIso)->addWeeks($w);
                $sIso = (int) $base->isoWeek;
                $sAnio = (int) $base->isoWeekYear;
                $lunes = $base->copy()->startOfDay();
                $domingo = $lunes->copy()->addDays(6)->endOfDay();

                $asignacion = $this->calcularSemana($meseros, $zonas, $plantillas, $sIso, $lunes, $domingo, $reglas, $historial);

                $celdas = [];
                $descanso = null;
                foreach ($asignacion as $fechaStr => $filas) {
                    $diaIso = (int) Carbon::parse($fechaStr)->dayOfWeekIso;
                    foreach ($filas as $fila) {
                        if ((int) $fila['user_id'] !== (int) $mesero->id) {
                            continue;
                        }
                        if ($fila['es_descanso']) {
                            $descanso = ['dia_iso' => $diaIso, 'nivel' => $this->nivelDemanda($diaIso, $reglas)];
                            $celdas[$diaIso] = null;
                        } else {
                            $celdas[$diaIso] = $porId[$fila['zona_id']]?->nombre;
                            $historial[$mesero->id][$diaIso][] = (int) $fila['zona_id'];
                            $historial[$mesero->id][$diaIso] = array_values(array_unique($historial[$mesero->id][$diaIso]));
                        }
                    }
                }

                $resultado[$indiceMesero]['semanas'][] = [
                    'semana_iso' => $sIso,
                    'anio' => $sAnio,
                    'descanso' => $descanso,
                    'zonas' => $celdas,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Reequilibra descansos: si un mesero acumula más descansos en días de
     * alta que otro, intercambian sus días de descanso de la semana.
     *
     * @return int intercambios realizados
     */
    public function equilibrarDescansos(int $programacionId, User $usuario): int
    {
        $this->autorizar($usuario);

        $programacion = ProgramacionSemanal::findOrFail($programacionId);
        $reglas = $this->obtenerReglasRotacion($programacion->sucursal_id);

        $referencia = Carbon::now()->setISODate($programacion->anio, $programacion->semana_iso);
        $historialAlta = [];
        for ($i = 1; $i <= 4; $i++) {
            $pasada = $referencia->copy()->subWeeks($i);
            $progPasada = ProgramacionSemanal::where('sucursal_id', $programacion->sucursal_id)
                ->where('semana_iso', (int) $pasada->isoWeek)
                ->where('anio', (int) $pasada->isoWeekYear)
                ->first();
            if (! $progPasada) {
                continue;
            }
            $descansos = TurnoMeseroSemana::where('programacion_semanal_id', $progPasada->id)
                ->where('es_descanso', true)
                ->get();
            foreach ($descansos as $turno) {
                if ($this->nivelDemanda((int) Carbon::parse($turno->fecha)->dayOfWeekIso, $reglas) === 'alta') {
                    $historialAlta[$turno->user_id] = ($historialAlta[$turno->user_id] ?? 0) + 1;
                }
            }
        }

        $actuales = TurnoMeseroSemana::where('programacion_semanal_id', $programacion->id)
            ->where('es_descanso', true)
            ->get()
            ->keyBy('user_id');

        if ($actuales->count() < 2) {
            return 0;
        }

        $swaps = 0;
        $bloqueados = [];

        DB::transaction(function () use ($actuales, $historialAlta, $reglas, $programacion, &$swaps, &$bloqueados) {
            $ids = $actuales->keys()->all();

            foreach ($ids as $idA) {
                foreach ($ids as $idB) {
                    if ($idA === $idB) {
                        continue;
                    }

                    if (in_array($idA, $bloqueados, true) || in_array($idB, $bloqueados, true)) {
                        continue;
                    }

                    $turnoA = TurnoMeseroSemana::find($actuales[$idA]->id);
                    $turnoB = TurnoMeseroSemana::find($actuales[$idB]->id);
                    if (! $turnoA || ! $turnoB) {
                        continue;
                    }

                    $nivelA = $this->nivelDemanda((int) Carbon::parse($turnoA->fecha)->dayOfWeekIso, $reglas);
                    $nivelB = $this->nivelDemanda((int) Carbon::parse($turnoB->fecha)->dayOfWeekIso, $reglas);

                    $histA = $historialAlta[$idA] ?? 0;
                    $histB = $historialAlta[$idB] ?? 0;

                    if ($nivelA === 'alta' && $nivelB !== 'alta' && $histA > $histB) {
                        $trabajoAY = TurnoMeseroSemana::where('programacion_semanal_id', $programacion->id)
                            ->where('user_id', $idA)->whereDate('fecha', $turnoB->fecha->toDateString())->first();
                        $trabajoBX = TurnoMeseroSemana::where('programacion_semanal_id', $programacion->id)
                            ->where('user_id', $idB)->whereDate('fecha', $turnoA->fecha->toDateString())->first();
                        if (! $trabajoAY || ! $trabajoBX) {
                            continue;
                        }

                        $zonaAY = $trabajoAY->zona_id;
                        $plantillaAY = $trabajoAY->plantilla_turno_id;
                        $zonaBX = $trabajoBX->zona_id;
                        $plantillaBX = $trabajoBX->plantilla_turno_id;

                        $turnoA->update(['es_descanso' => false, 'zona_id' => $zonaBX, 'plantilla_turno_id' => $plantillaBX]);
                        $trabajoAY->update(['es_descanso' => true, 'zona_id' => null, 'plantilla_turno_id' => null]);
                        $turnoB->update(['es_descanso' => false, 'zona_id' => $zonaAY, 'plantilla_turno_id' => $plantillaAY]);
                        $trabajoBX->update(['es_descanso' => true, 'zona_id' => null, 'plantilla_turno_id' => null]);

                        $actuales[$idA] = $trabajoAY->fresh();
                        $actuales[$idB] = $trabajoBX->fresh();
                        $historialAlta[$idA] = $histA - 1;
                        $historialAlta[$idB] = $histB + 1;
                        $bloqueados[] = $idA;
                        $bloqueados[] = $idB;
                        $swaps++;
                    }
                }
            }
        });

        return $swaps;
    }

    /**
     * Replica los turnos de la semana anterior en la semana destino (fechas +7 días).
     */
    public function copiarSemanaAnterior(int $sucursalId, int $semanaIsoDestino, int $anioDestino, User $usuario): ProgramacionSemanal
    {
        $this->autorizar($usuario);

        if ($semanaIsoDestino < 1 || $semanaIsoDestino > 53) {
            throw new \InvalidArgumentException('La semana ISO debe estar entre 1 y 53.');
        }

        $destinoCarbon = Carbon::now()->setISODate($anioDestino, $semanaIsoDestino);
        $origenCarbon = $destinoCarbon->copy()->subWeek();
        $semanaOrigen = (int) $origenCarbon->isoWeek;
        $anioOrigen = (int) $origenCarbon->isoWeekYear;

        $origen = ProgramacionSemanal::where('sucursal_id', $sucursalId)
            ->where('semana_iso', $semanaOrigen)
            ->where('anio', $anioOrigen)
            ->first();

        if (! $origen) {
            throw new \InvalidArgumentException("No existe programación en la semana anterior (S{$semanaOrigen}/{$anioOrigen}).");
        }

        $destino = ProgramacionSemanal::firstOrCreate(
            ['sucursal_id' => $sucursalId, 'semana_iso' => $semanaIsoDestino, 'anio' => $anioDestino],
            ['estado' => ProgramacionSemanal::ESTADO_BORRADOR]
        );

        DB::transaction(function () use ($origen, $destino) {
            foreach ($origen->turnos()->get() as $turno) {
                $nuevaFecha = Carbon::parse($turno->fecha)->addWeek()->toDateString();

                TurnoMeseroSemana::updateOrCreate(
                    [
                        'programacion_semanal_id' => $destino->id,
                        'user_id' => $turno->user_id,
                        'fecha' => $nuevaFecha,
                    ],
                    [
                        'zona_id' => $turno->zona_id,
                        'plantilla_turno_id' => $turno->plantilla_turno_id,
                        'mesas_especificas' => $turno->mesas_especificas,
                        'es_descanso' => $turno->es_descanso,
                    ]
                );
            }
        });

        return $destino->fresh();
    }

    /**
     * Publica la semana: cambia estado, dispara evento y sincroniza la rotación del día.
     */
    public function publicarSemana(int $programacionId, User $usuario): ProgramacionSemanal
    {
        $this->autorizar($usuario);

        $programacion = ProgramacionSemanal::findOrFail($programacionId);

        if ($programacion->sucursal_id !== $usuario->sucursal_id && ! $usuario->isAdmin()) {
            throw new AuthorizationException('No puedes publicar programaciones de otra sucursal.');
        }

        if ($programacion->esPublicada()) {
            return $programacion;
        }

        $programacion->update([
            'estado' => ProgramacionSemanal::ESTADO_PUBLICADO,
            'publicado_por' => $usuario->id,
            'publicado_en' => now(),
        ]);

        HorarioSemanalPublicado::dispatch($programacion->fresh(), $usuario->id);

        $this->sincronizarRotacionDelDia($programacion);

        return $programacion->fresh();
    }

    /**
     * Edita una celda de la matriz (zona, plantilla, descanso o mesas específicas).
     */
    public function actualizarCelda(int $turnoId, array $datos, User $usuario): TurnoMeseroSemana
    {
        $this->autorizar($usuario);

        $turno = TurnoMeseroSemana::findOrFail($turnoId);

        $cambios = [];

        if (array_key_exists('zona_id', $datos)) {
            $zonaId = $datos['zona_id'];
            if ($zonaId !== null) {
                $zona = Zona::find($zonaId);
                if (! $zona || $zona->sucursal_id !== $turno->programacion->sucursal_id) {
                    throw new \InvalidArgumentException('La zona no pertenece a la sucursal de la programación.');
                }
                $cambios['zona_id'] = $zona->id;
            } else {
                $cambios['zona_id'] = null;
            }
        }

        if (array_key_exists('plantilla_turno_id', $datos)) {
            $plantillaId = $datos['plantilla_turno_id'];
            if ($plantillaId !== null && ! PlantillaTurno::where('id', $plantillaId)->exists()) {
                throw new \InvalidArgumentException('La plantilla de turno no existe.');
            }
            $cambios['plantilla_turno_id'] = $plantillaId;
        }

        if (array_key_exists('es_descanso', $datos)) {
            $cambios['es_descanso'] = (bool) $datos['es_descanso'];
            if ($cambios['es_descanso']) {
                $cambios['zona_id'] = null;
                $cambios['plantilla_turno_id'] = null;
            }
        }

        if (array_key_exists('mesas_especificas', $datos)) {
            $mesas = $datos['mesas_especificas'];
            if ($mesas !== null) {
                if (! is_array($mesas)) {
                    throw new \InvalidArgumentException('mesas_especificas debe ser un arreglo de IDs.');
                }
                $ids = array_values(array_unique(array_map('intval', $mesas)));
                if (! empty($ids)) {
                    $existentes = Mesa::whereIn('id', $ids)->count();
                    if ($existentes !== count($ids)) {
                        throw new \InvalidArgumentException('Alguna mesa indicada no existe.');
                    }
                }
                $cambios['mesas_especificas'] = $ids;
            } else {
                $cambios['mesas_especificas'] = null;
            }
        }

        if (empty($cambios)) {
            return $turno;
        }

        $turno->update($cambios);

        return $turno->fresh();
    }

    /**
     * Matriz mesero × 7 días para la vista del administrador (con eager loading).
     *
     * @return array{dias: array<int, Carbon>, filas: array<int, array{mesero: User, celdas: array<string, ?TurnoMeseroSemana>}>, programacion: ProgramacionSemanal}
     */
    public function matrizSemanal(int $programacionId): array
    {
        $programacion = ProgramacionSemanal::findOrFail($programacionId);

        $lunes = Carbon::now()->setISODate($programacion->anio, $programacion->semana_iso)->startOfDay();
        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $dias[] = $lunes->copy()->addDays($i);
        }

        $turnos = TurnoMeseroSemana::with(['mesero', 'zona', 'plantilla'])
            ->where('programacion_semanal_id', $programacion->id)
            ->get();

        $meseroIds = $turnos->pluck('user_id')->unique()->values();
        $meseros = User::whereIn('id', $meseroIds)->orderBy('name')->get();
        if ($meseros->isEmpty()) {
            $meseros = User::query()
                ->whereHas('role', fn ($q) => $q->where('slug', 'mesero'))
                ->where('sucursal_id', $programacion->sucursal_id)
                ->where('activo', true)
                ->orderBy('name')
                ->get();
        }

        $porMeseroFecha = [];
        foreach ($turnos as $turno) {
            $porMeseroFecha[$turno->user_id][$turno->fecha->toDateString()] = $turno;
        }

        $filas = [];
        foreach ($meseros as $mesero) {
            $celdas = [];
            foreach ($dias as $dia) {
                $celdas[$dia->toDateString()] = $porMeseroFecha[$mesero->id][$dia->toDateString()] ?? null;
            }
            $filas[] = ['mesero' => $mesero, 'celdas' => $celdas];
        }

        return ['dias' => $dias, 'filas' => $filas, 'programacion' => $programacion];
    }

    protected function autorizar(User $usuario): void
    {
        if (! $usuario->isAdmin() && ! $usuario->isGerente()) {
            throw new AuthorizationException('Solo administradores y gerentes gestionan la programación semanal.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FASE 4 — Aviso interactivo al login del mesero (auto-consulta, sin admin)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Turnos sin confirmar del mesero en la semana ISO actual publicada.
     *
     * @return array{programacion: ProgramacionSemanal, turnos: Collection<int, TurnoMeseroSemana>}|null
     */
    public function pendienteConfirmacion(User $usuario): ?array
    {
        $programacion = ProgramacionSemanal::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->where('semana_iso', (int) Carbon::today()->isoWeek)
            ->where('anio', (int) Carbon::today()->isoWeekYear)
            ->where('estado', ProgramacionSemanal::ESTADO_PUBLICADO)
            ->first();

        if (! $programacion) {
            return null;
        }

        $turnos = TurnoMeseroSemana::with(['zona', 'plantilla'])
            ->where('programacion_semanal_id', $programacion->id)
            ->where('user_id', $usuario->id)
            ->whereNull('confirmado_por_mesero_en')
            ->orderBy('fecha')
            ->get();

        if ($turnos->isEmpty()) {
            return null;
        }

        return ['programacion' => $programacion, 'turnos' => $turnos];
    }

    /**
     * Registra la confirmación del horario y limpia el respaldo en campana.
     *
     * @return int turnos confirmados
     */
    public function confirmarHorario(User $usuario): int
    {
        $pendiente = $this->pendienteConfirmacion($usuario);

        if ($pendiente === null) {
            return 0;
        }

        $ids = $pendiente['turnos']->pluck('id')->all();

        TurnoMeseroSemana::whereIn('id', $ids)->update(['confirmado_por_mesero_en' => now()]);

        NotificacionUsuario::where('user_id', $usuario->id)
            ->where('tipo', 'turno_semanal')
            ->where('leida', false)
            ->update(['leida' => true, 'leida_en' => now()]);

        Cache::forget('notif.resumen.'.$usuario->id);

        return count($ids);
    }

    /**
     * Crea la notificación de respaldo una sola vez (idempotente) mientras
     * el mesero no confirme su horario.
     */
    public function asegurarNotificacionRespaldo(User $usuario): void
    {
        $pendiente = $this->pendienteConfirmacion($usuario);

        if ($pendiente === null) {
            return;
        }

        $existe = NotificacionUsuario::where('user_id', $usuario->id)
            ->where('tipo', 'turno_semanal')
            ->where('leida', false)
            ->exists();

        if ($existe) {
            return;
        }

        $programacion = $pendiente['programacion'];

        NotificacionUsuario::create([
            'user_id' => $usuario->id,
            'tipo' => 'turno_semanal',
            'titulo' => 'Tienes un horario semanal por confirmar',
            'cuerpo' => 'Revisa tu itinerario de la semana '.$programacion->semana_iso.'/'.$programacion->anio.' en el POS y confirma tu turno.',
            'datos' => [
                'programacion_id' => $programacion->id,
                'semana_iso' => $programacion->semana_iso,
                'anio' => $programacion->anio,
            ],
            'leida' => false,
            'created_at' => now(),
        ]);

        Cache::forget('notif.resumen.'.$usuario->id);
    }

    protected function sincronizarRotacionDelDia(ProgramacionSemanal $programacion): void
    {
        try {
            $hoy = Carbon::today()->toDateString();

            $turnosHoy = TurnoMeseroSemana::with('zona')
                ->where('programacion_semanal_id', $programacion->id)
                ->whereDate('fecha', $hoy)
                ->where('es_descanso', false)
                ->whereNotNull('zona_id')
                ->get();

            foreach ($turnosHoy as $turno) {
                if (! $turno->zona) {
                    continue;
                }

                $this->rotacionService->asignarMeseroAZona(
                    $programacion->sucursal_id,
                    $turno->zona->slug,
                    $turno->user_id,
                    $turno->zona->id
                );
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo sincronizar la rotación al publicar semana: '.$e->getMessage());
        }
    }
}
