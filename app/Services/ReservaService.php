<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReservaService
{
    public function verificarDisponibilidad(string $fecha, string $horaInicio, int $personas, int $duracionMin = 120): Collection
    {
        $horaFin = $this->sumarMinutos($horaInicio, $duracionMin);

        $bloqueadas = Reserva::query()
            ->whereIn('estado', ['confirmada', 'llego'])
            ->whereDate('fecha', $fecha)
            ->get()
            ->filter(fn (Reserva $r) => $this->seSolapan($horaInicio, $horaFin, $r->hora_llegada, $this->sumarMinutos($r->hora_llegada, $r->duracion_min)))
            ->pluck('id');

        $mesasBloqueadas = \DB::table('reserva_mesa')
            ->whereIn('reserva_id', $bloqueadas)
            ->pluck('mesa_id');

        $query = Mesa::query()
            ->whereNotIn('id', $mesasBloqueadas)
            ->whereNotIn('estado', [MesaEstado::OCUPADA->value, MesaEstado::POR_LIMPIAR->value]);

        $conCapacidad = (clone $query)->where('capacidad', '>=', $personas)->orderBy('capacidad')->get();
        if ($conCapacidad->isNotEmpty()) {
            return $conCapacidad;
        }

        if ($query->sum('capacidad') >= $personas) {
            return $query->orderBy('zona')->orderBy('numero')->get();
        }

        return collect();
    }

    public function crear(array $datos, string $origen = 'sistema'): Reserva
    {
        $personas = (int) ($datos['personas'] ?? 0);
        $fecha = $datos['fecha'] ?? null;

        if ($personas < 1) {
            throw new InvalidArgumentException('La reserva debe ser para al menos una persona.');
        }
        if (! $fecha || $fecha < now()->toDateString()) {
            throw new InvalidArgumentException('Debe elegirse una fecha igual o posterior a hoy.');
        }

        $reserva = Reserva::create([
            'sucursal_id' => $datos['sucursal_id'] ?? null,
            'cliente_id' => $datos['cliente_id'] ?? null,
            'nombre_contacto' => $datos['nombre_contacto'],
            'telefono_contacto' => $datos['telefono_contacto'],
            'email_contacto' => $datos['email_contacto'] ?? null,
            'fecha' => $fecha,
            'hora_llegada' => $datos['hora_llegada'],
            'duracion_min' => (int) ($datos['duracion_min'] ?? 120),
            'personas' => $personas,
            'estado' => 'solicitada',
            'origen' => $origen,
            'notas' => $datos['notas'] ?? null,
            'anticipo' => $datos['anticipo'] ?? null,
            'token_publico' => Str::random(32),
            'created_by' => $datos['created_by'] ?? auth()->id(),
        ]);

        if (! empty($datos['mesa_ids'])) {
            $reserva->mesas()->sync($datos['mesa_ids']);
        }

        app(AuditoriaService::class)->registrar(
            accion: 'reserva.creada',
            entidad: 'reserva',
            entidadId: $reserva->id,
            descripcion: "Reserva {$reserva->fecha->toDateString()} {$reserva->hora_llegada} para {$personas} personas",
            datos: ['origen' => $origen, 'personas' => $personas],
        );

        return $reserva;
    }

    public function confirmar(Reserva $reserva, ?User $usuario = null, ?array $mesaIds = null): Reserva
    {
        return DB::transaction(function () use ($reserva, $usuario, $mesaIds) {
            $reservaLocked = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            if ($mesaIds !== null && count($mesaIds) > 0) {
                $capacidadTotal = Mesa::whereIn('id', $mesaIds)->sum('capacidad');
                if ($capacidadTotal < $reservaLocked->personas) {
                    throw new InvalidArgumentException("La capacidad total de las mesas seleccionadas ({$capacidadTotal}) es insuficiente para {$reservaLocked->personas} personas.");
                }

                $reservaLocked->mesas()->sync($mesaIds);
                $reservaLocked->refresh();
            }

            if ($reservaLocked->mesas->isEmpty()) {
                $disponibles = $this->verificarDisponibilidad($reservaLocked->fecha->toDateString(), $reservaLocked->hora_llegada, $reservaLocked->personas, $reservaLocked->duracion_min);

                if ($disponibles->isEmpty()) {
                    throw new InvalidArgumentException('No hay mesas disponibles para esta reserva.');
                }

                $mejor = $disponibles->filter(fn ($m) => $m->capacidad >= $reservaLocked->personas)->sortBy('capacidad')->first();

                if ($mejor) {
                    $reservaLocked->mesas()->attach($mejor->id);
                } else {
                    $acum = 0;
                    $asignadas = [];
                    foreach ($disponibles->sortByDesc('capacidad') as $m) {
                        $asignadas[] = $m->id;
                        $acum += $m->capacidad;
                        if ($acum >= $reservaLocked->personas) {
                            break;
                        }
                    }
                    $reservaLocked->mesas()->sync($asignadas);
                }

                $reservaLocked->refresh();
            }

            $this->validarMesasParaConfirmar($reservaLocked);

            $reservaLocked->update([
                'estado' => 'confirmada',
                'confirmado_por' => $usuario?->id ?? auth()->id(),
            ]);

            $reservaLocked->mesas->each(fn (Mesa $mesa) => $mesa->update(['estado' => MesaEstado::RESERVADA->value]));
            Cache::forget('pos.terminal.mesas');

            $this->auditar('reserva.confirmada', $reservaLocked);

            return $reservaLocked;
        });
    }

    public function marcarLlego(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'llego']);
        $reserva->mesas->each(fn (Mesa $mesa) => $mesa->update(['estado' => MesaEstado::OCUPADA->value]));
        Cache::forget('pos.terminal.mesas');
        $this->auditar('reserva.llego', $reserva);

        return $reserva;
    }

    public function finalizar(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'finalizada']);
        $this->liberarMesas($reserva);
        $this->auditar('reserva.finalizada', $reserva);

        return $reserva;
    }

    public function cancelar(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'cancelada']);
        $this->liberarMesas($reserva);
        $this->auditar('reserva.cancelada', $reserva);

        return $reserva;
    }

    public function marcarNoShow(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'no_mostro']);
        $this->liberarMesas($reserva);
        $this->auditar('reserva.no_mostro', $reserva);

        return $reserva;
    }

    public function reservasDelDia(string $fecha): \Illuminate\Database\Eloquent\Collection
    {
        return Reserva::with(['mesas', 'cliente', 'confirmadoPor'])
            ->whereDate('fecha', $fecha)
            ->orderBy('hora_llegada')
            ->get();
    }

    private function validarMesasParaConfirmar(Reserva $reserva): void
    {
        $reserva->mesas->each(function (Mesa $mesa) use ($reserva) {
            if ($reserva->fecha->isToday()) {
                $minutosHastaLlegada = now()->diffInMinutes(Carbon::parse($reserva->hora_llegada), false);
                if ($minutosHastaLlegada <= 60 && $minutosHastaLlegada >= -120) {
                    if (in_array($mesa->estado, [MesaEstado::OCUPADA->value, MesaEstado::POR_LIMPIAR->value])) {
                        throw new InvalidArgumentException("La mesa {$this->nombreMesa($mesa)} no está disponible en este momento.");
                    }
                }
            }

            $solapada = Reserva::query()
                ->whereDate('fecha', $reserva->fecha->toDateString())
                ->whereIn('estado', ['confirmada', 'llego'])
                ->where('id', '!=', $reserva->id)
                ->whereHas('mesas', fn ($q) => $q->where('mesas.id', $mesa->id))
                ->get()
                ->filter(fn (Reserva $r) => $this->seSolapan(
                    $reserva->hora_llegada,
                    $this->sumarMinutos($reserva->hora_llegada, $reserva->duracion_min),
                    $r->hora_llegada,
                    $this->sumarMinutos($r->hora_llegada, $r->duracion_min),
                ))
                ->isNotEmpty();

            if ($solapada) {
                throw new InvalidArgumentException("La mesa {$this->nombreMesa($mesa)} ya está reservada para ese horario.");
            }
        });
    }

    private function liberarMesas(Reserva $reserva): void
    {
        $reserva->mesas->each(function (Mesa $mesa) {
            if ($mesa->pedidos()->activos()->exists()) {
                return;
            }

            if ($mesa->estado === MesaEstado::RESERVADA->value || $mesa->estado === MesaEstado::OCUPADA->value) {
                $mesa->update(['estado' => MesaEstado::LIBRE->value]);
            }
        });

        Cache::forget('pos.terminal.mesas');
    }

    private function nombreMesa(Mesa $mesa): string
    {
        return 'Mesa #'.$mesa->numero;
    }

    private function seSolapan(string $aIni, string $aFin, string $bIni, string $bFin): bool
    {
        $aIni = Carbon::parse($aIni)->format('H:i:s');
        $aFin = Carbon::parse($aFin)->format('H:i:s');
        $bIni = Carbon::parse($bIni)->format('H:i:s');
        $bFin = Carbon::parse($bFin)->format('H:i:s');

        return $aIni < $bFin && $bIni < $aFin;
    }

    private function sumarMinutos(string $hora, int $minutos): string
    {
        return Carbon::parse($hora)->addMinutes($minutos)->format('H:i:s');
    }

    private function auditar(string $accion, Reserva $reserva): void
    {
        app(AuditoriaService::class)->registrar(
            accion: $accion,
            entidad: 'reserva',
            entidadId: $reserva->id,
            descripcion: "{$reserva->nombre_contacto} · {$reserva->fecha->toDateString()} {$reserva->hora_llegada}",
        );
    }
}
