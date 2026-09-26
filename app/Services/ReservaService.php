<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReservaService
{
    public function verificarDisponibilidad(string $fecha, string $horaInicio, int $personas, int $duracionMin = 120, ?int $sucursalId = null): Collection
    {
        $horaFin = $this->sumarMinutos($horaInicio, $duracionMin);

        $bloqueadas = Reserva::query()
            ->whereIn('estado', ['confirmada', 'llego'])
            ->whereDate('fecha', $fecha)
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get()
            ->filter(fn (Reserva $r) => $this->seSolapan($horaInicio, $horaFin, $r->hora_llegada, $this->sumarMinutos($r->hora_llegada, $r->duracion_min)))
            ->pluck('id');

        $mesasBloqueadas = DB::table('reserva_mesa')
            ->whereIn('reserva_id', $bloqueadas)
            ->pluck('mesa_id');

        $query = Mesa::query()
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
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

        $sucursalId = (int) ($datos['sucursal_id'] ?? auth()->user()?->sucursal_id);
        if ($sucursalId === 0) {
            $sucursalId = (int) (Sucursal::value('id') ?? 1);
        }
        if (auth()->user()?->sucursal_id && $sucursalId !== (int) auth()->user()->sucursal_id) {
            throw new AuthorizationException('No puede crear reservas en otra sucursal.');
        }

        $reserva = Reserva::create([
            'sucursal_id' => $sucursalId,
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
            'zona_preferida_id' => $datos['zona_preferida_id'] ?? null,
            'mesero_id' => $datos['mesero_id'] ?? null,
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
                $mesasValidas = Mesa::whereIn('id', $mesaIds);
                if ($reservaLocked->sucursal_id) {
                    $mesasValidas->where('sucursal_id', $reservaLocked->sucursal_id);
                }
                if ($mesasValidas->count() !== count($mesaIds)) {
                    throw new AuthorizationException('Una o más mesas no pertenecen a la sucursal de la reserva.');
                }

                $capacidadTotal = (clone $mesasValidas)->sum('capacidad');
                if ($capacidadTotal < $reservaLocked->personas) {
                    throw new InvalidArgumentException("La capacidad total de las mesas seleccionadas ({$capacidadTotal}) es insuficiente para {$reservaLocked->personas} personas.");
                }

                $reservaLocked->mesas()->sync($mesaIds);
                $reservaLocked->refresh();
            }

            if ($reservaLocked->mesas->isEmpty()) {
                $disponibles = $this->verificarDisponibilidad(
                    $reservaLocked->fecha->toDateString(),
                    $reservaLocked->hora_llegada,
                    $reservaLocked->personas,
                    $reservaLocked->duracion_min,
                    $reservaLocked->sucursal_id
                );

                if ($disponibles->isEmpty()) {
                    throw new InvalidArgumentException('No hay mesas disponibles para esta reserva.');
                }

                $zonaSlug = $reservaLocked->zonaPreferida?->slug;
                $disponiblesZona = $zonaSlug ? $disponibles->filter(fn ($m) => $m->zona === $zonaSlug) : collect();
                $candidatos = $disponiblesZona->isNotEmpty() ? $disponiblesZona : $disponibles;

                $mejor = $candidatos->filter(fn ($m) => $m->capacidad >= $reservaLocked->personas)->sortBy('capacidad')->first();

                if (! $mejor && $disponiblesZona->isNotEmpty()) {
                    $mejor = $disponibles->filter(fn ($m) => $m->capacidad >= $reservaLocked->personas)->sortBy('capacidad')->first();
                }

                if ($mejor) {
                    $reservaLocked->mesas()->attach($mejor->id);
                } else {
                    $acum = 0;
                    $asignadas = [];
                    foreach ($candidatos->sortByDesc('capacidad') as $m) {
                        $asignadas[] = $m->id;
                        $acum += $m->capacidad;
                        if ($acum >= $reservaLocked->personas) {
                            break;
                        }
                    }
                    if ($acum < $reservaLocked->personas) {
                        foreach ($disponibles->sortByDesc('capacidad') as $m) {
                            if (! in_array($m->id, $asignadas, true)) {
                                $asignadas[] = $m->id;
                                $acum += $m->capacidad;
                                if ($acum >= $reservaLocked->personas) {
                                    break;
                                }
                            }
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

            $primerMeseroId = null;
            $rotacionService = app(RotacionMeseroService::class);
            $reservaLocked->mesas->each(function (Mesa $mesa) use ($rotacionService, &$primerMeseroId) {
                $mesa->update(['estado' => MesaEstado::RESERVADA->value]);
                $mesero = $rotacionService->asignarMesaAutomatico($mesa) ?? $rotacionService->autoasignarMesa($mesa);
                if ($mesero && ! $primerMeseroId) {
                    $primerMeseroId = $mesero->id;
                }
            });

            if ($primerMeseroId) {
                $reservaLocked->update([
                    'mesero_id' => $primerMeseroId,
                    'asignacion_automatica' => true,
                ]);
            }
            Cache::forget('pos.terminal.mesas');
            $this->auditar('reserva.confirmada', $reservaLocked);

            try {
                app(CrmAutomatizacionService::class)->procesarConfirmacionReserva($reservaLocked);
            } catch (\Throwable $e) {
                Log::warning("CRM Error en confirmación de reserva: {$e->getMessage()}");
            }

            return $reservaLocked;
        });
    }

    /**
     * Asignar mesa específica a una reserva y vincular de inmediato el mesero de turno de esa zona.
     */
    public function asignarMesa(Reserva $reserva, Mesa|int $mesa): Reserva
    {
        return DB::transaction(function () use ($reserva, $mesa) {
            $reservaLocked = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();
            $mesaModel = $mesa instanceof Mesa ? $mesa : Mesa::findOrFail($mesa);

            if ($reservaLocked->sucursal_id && $mesaModel->sucursal_id && $reservaLocked->sucursal_id !== $mesaModel->sucursal_id) {
                throw new AuthorizationException('La mesa seleccionada no pertenece a la misma sucursal de la reserva.');
            }

            $reservaLocked->mesas()->sync([$mesaModel->id]);

            $nuevoEstado = $reservaLocked->estado === 'llego' ? MesaEstado::OCUPADA->value : MesaEstado::RESERVADA->value;
            $mesaModel->update(['estado' => $nuevoEstado]);

            $rotacionService = app(RotacionMeseroService::class);
            $mesero = $rotacionService->asignarMesaAutomatico($mesaModel)
                ?? $rotacionService->asignarMesaYMeseroAReserva($reservaLocked, $mesaModel)
                ?? $mesaModel->mesero;

            if ($mesero) {
                $reservaLocked->update([
                    'mesero_id' => $mesero->id,
                    'asignacion_automatica' => true,
                ]);
            }

            Cache::forget('pos.terminal.mesas');
            $this->auditar('reserva.mesa_asignada', $reservaLocked, [
                'mesa_id' => $mesaModel->id,
                'mesero_id' => $mesero?->id,
            ]);

            return $reservaLocked->fresh(['mesas', 'mesero']);
        });
    }

    /**
     * Auto-asignar la mejor mesa disponible para la reserva y el mesero de turno de esa zona.
     */
    public function autoAsignarMesa(Reserva $reserva): ?Mesa
    {
        $disponibles = $this->verificarDisponibilidad(
            $reserva->fecha->toDateString(),
            $reserva->hora_llegada,
            $reserva->personas,
            $reserva->duracion_min,
            $reserva->sucursal_id
        );

        if ($disponibles->isEmpty()) {
            throw new InvalidArgumentException('No hay mesas disponibles con capacidad suficiente para este horario.');
        }

        $zonaSlug = $reserva->zonaPreferida?->slug;
        $disponiblesZona = $zonaSlug ? $disponibles->filter(fn ($m) => $m->zona === $zonaSlug) : collect();
        $candidatos = $disponiblesZona->isNotEmpty() ? $disponiblesZona : $disponibles;

        $mejor = $candidatos->filter(fn ($m) => $m->capacidad >= $reserva->personas)->sortBy('capacidad')->first();

        if (! $mejor && $disponiblesZona->isNotEmpty()) {
            $mejor = $disponibles->filter(fn ($m) => $m->capacidad >= $reserva->personas)->sortBy('capacidad')->first();
        }

        if (! $mejor) {
            $mejor = $disponibles->sortByDesc('capacidad')->first();
        }

        if ($mejor) {
            $this->asignarMesa($reserva, $mejor);

            return $mejor->fresh(['mesero']);
        }

        return null;
    }

    /**
     * Desasignar y liberar las mesas de una reserva.
     */
    public function desasignarMesas(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva) {
            $reservaLocked = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();
            $reservaLocked->mesas->each(function (Mesa $m) {
                $m->update(['estado' => MesaEstado::LIBRE->value]);
            });
            $reservaLocked->mesas()->detach();
            $reservaLocked->update(['mesero_id' => null, 'asignacion_automatica' => false]);

            Cache::forget('pos.terminal.mesas');
            $this->auditar('reserva.mesas_liberadas', $reservaLocked);

            return $reservaLocked->fresh(['mesas', 'mesero']);
        });
    }

    public function marcarLlego(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'llego']);
        $rotacionService = app(RotacionMeseroService::class);
        $reserva->mesas->each(function (Mesa $mesa) use ($rotacionService) {
            $mesa->update(['estado' => MesaEstado::OCUPADA->value]);
            $rotacionService->autoasignarMesa($mesa);
        });
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
        return Reserva::with(['mesas', 'cliente', 'confirmadoPor', 'mesero'])
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
                ->when($reserva->sucursal_id, fn ($q) => $q->where('sucursal_id', $reserva->sucursal_id))
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

    private function auditar(string $accion, Reserva $reserva, array $datos = []): void
    {
        app(AuditoriaService::class)->registrar(
            accion: $accion,
            entidad: 'reserva',
            entidadId: $reserva->id,
            descripcion: "{$reserva->nombre_contacto} · {$reserva->fecha->toDateString()} {$reserva->hora_llegada}",
            datos: $datos,
        );
    }
}
