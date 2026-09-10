<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        return Mesa::query()
            ->where('capacidad', '>=', $personas)
            ->where('estado', MesaEstado::LIBRE->value)
            ->whereNotIn('id', $mesasBloqueadas)
            ->orderBy('zona')
            ->orderBy('numero')
            ->get();
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
        if ($mesaIds !== null) {
            $reserva->mesas()->sync($mesaIds);
            $reserva->refresh();
        }

        if ($reserva->mesas->isEmpty()) {
            $disponibles = $this->verificarDisponibilidad($reserva->fecha->toDateString(), $reserva->hora_llegada, $reserva->personas, $reserva->duracion_min);

            if ($disponibles->isEmpty()) {
                throw new InvalidArgumentException('No hay mesas disponibles para esta reserva.');
            }

            $reserva->mesas()->attach($disponibles->sortByDesc('capacidad')->first()->id);
            $reserva->refresh();
        }

        $this->validarMesasParaConfirmar($reserva);

        $reserva->update([
            'estado' => 'confirmada',
            'confirmado_por' => $usuario?->id ?? auth()->id(),
        ]);

        $reserva->mesas->each(fn (Mesa $mesa) => $mesa->update(['estado' => MesaEstado::RESERVADA->value]));

        $this->auditar('reserva.confirmada', $reserva);

        return $reserva;
    }

    public function marcarLlego(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'llego']);
        $reserva->mesas->each(fn (Mesa $mesa) => $mesa->update(['estado' => MesaEstado::OCUPADA->value]));
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
            if (in_array($mesa->estado, [MesaEstado::OCUPADA->value, MesaEstado::POR_LIMPIAR->value])) {
                throw new InvalidArgumentException("La mesa {$this->nombreMesa($mesa)} no está disponible.");
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
            if ($mesa->estado === MesaEstado::RESERVADA->value || $mesa->estado === MesaEstado::OCUPADA->value) {
                $mesa->update(['estado' => MesaEstado::LIBRE->value]);
            }
        });
    }

    private function nombreMesa(Mesa $mesa): string
    {
        return $mesa->nombre ?? 'Mesa #'.$mesa->numero;
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
