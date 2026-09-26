<?php

namespace App\Services;

use App\Events\MesaActualizada;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\RotacionMesero;
use App\Models\RotacionZona;
use App\Models\TurnoMeseroZona;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RotacionMeseroService
{
    /**
     * Configurar rotación para una zona específica (F7-03).
     */
    public function configurarRotacion(
        Zona|int $zona,
        array $meserosIds,
        string $modo = 'automatico',
        ?int $sucursalId = null
    ): RotacionZona {
        $zonaId = $zona instanceof Zona ? $zona->id : $zona;
        $zonaModel = $zona instanceof Zona ? $zona : Zona::find($zonaId);
        $sucursalId = $sucursalId ?? $zonaModel?->sucursal_id ?? 1;

        $rotacionZona = RotacionZona::updateOrCreate(
            ['sucursal_id' => $sucursalId, 'zona_id' => $zonaId],
            [
                'activa' => true,
                'modo' => in_array($modo, ['automatico', 'manual'], true) ? $modo : 'automatico',
            ]
        );

        // Desactivar meseros no incluidos en la lista
        TurnoMeseroZona::where('zona_id', $zonaId)
            ->whereNotIn('mesero_id', $meserosIds)
            ->update(['activo' => false]);

        // Registrar o actualizar meseros en la cola
        foreach ($meserosIds as $indice => $meseroId) {
            TurnoMeseroZona::updateOrCreate(
                ['zona_id' => $zonaId, 'mesero_id' => $meseroId],
                [
                    'sucursal_id' => $sucursalId,
                    'orden' => $indice + 1,
                    'activo' => true,
                ]
            );

            // Mantener sincronizado con RotacionMesero histórico si existe zona_slug
            if ($zonaModel) {
                RotacionMesero::updateOrCreate(
                    [
                        'sucursal_id' => $sucursalId,
                        'zona_slug' => $zonaModel->slug,
                        'user_id' => $meseroId,
                        'turno' => 'general',
                    ],
                    [
                        'zona_id' => $zonaId,
                        'orden' => $indice + 1,
                        'activo' => true,
                    ]
                );
            }
        }

        return $rotacionZona;
    }

    /**
     * Obtener la cola de turnos de una zona (F7-03).
     */
    public function obtenerColaPorZona(int $zonaId): Collection
    {
        return TurnoMeseroZona::with(['mesero', 'zona'])
            ->where('zona_id', $zonaId)
            ->where('activo', true)
            ->orderBy('orden', 'asc')
            ->get();
    }

    /**
     * Reordenar cola de turnos por zona (F7-03).
     */
    public function reordenarCola(int $zonaId, array $ordenMeseroIds): void
    {
        DB::transaction(function () use ($zonaId, $ordenMeseroIds) {
            foreach ($ordenMeseroIds as $index => $meseroId) {
                TurnoMeseroZona::where('zona_id', $zonaId)
                    ->where('mesero_id', $meseroId)
                    ->update(['orden' => $index + 1]);
            }
        });
    }

    /**
     * Avanzar la rotación moviendo el primer mesero al final de la cola (F7-03).
     */
    public function avanzarRotacion(int $zonaId): void
    {
        $primerTurno = TurnoMeseroZona::where('zona_id', $zonaId)
            ->where('activo', true)
            ->orderBy('orden', 'asc')
            ->first();

        if (! $primerTurno) {
            return;
        }

        $maxOrden = TurnoMeseroZona::where('zona_id', $zonaId)->max('orden') ?? 0;
        $primerTurno->update([
            'orden' => $maxOrden + 1,
            'ultimo_asignado_en' => now(),
        ]);

        // Re-normalizar orden correlativo
        $todos = TurnoMeseroZona::where('zona_id', $zonaId)
            ->orderBy('orden', 'asc')
            ->get();
        foreach ($todos as $pos => $t) {
            $t->update(['orden' => $pos + 1]);
        }
    }

    /**
     * Asignar mesa de forma automática según rotación de la zona (F7-03).
     */
    public function asignarMesaAutomatico(Mesa $mesa): ?User
    {
        $zona = Zona::where('sucursal_id', $mesa->sucursal_id)
            ->where(function ($q) use ($mesa) {
                $q->where('slug', $mesa->zona)
                    ->orWhere('id', is_numeric($mesa->zona) ? (int) $mesa->zona : 0);
            })
            ->first();

        if (! $zona) {
            return $this->autoasignarMesa($mesa);
        }

        $configRotacion = RotacionZona::where('zona_id', $zona->id)->first();
        if ($configRotacion && (! $configRotacion->activa || $configRotacion->modo === 'manual')) {
            return null; // Rotación deshabilitada o en modo manual
        }

        $turno = TurnoMeseroZona::with('mesero')
            ->where('zona_id', $zona->id)
            ->where('activo', true)
            ->orderBy('orden', 'asc')
            ->first();

        if (! $turno || ! $turno->mesero || $turno->mesero->activo === false) {
            // Fallback al método histórico
            return $this->autoasignarMesa($mesa);
        }

        $mesero = $turno->mesero;
        $mesa->update(['mesero_id' => $mesero->id]);
        $turno->increment('mesas_activas');
        $this->avanzarRotacion($zona->id);

        $mesaFresh = $mesa->fresh(['mesero', 'pedidos']);
        if ($mesaFresh) {
            broadcast(new MesaActualizada($mesaFresh))->toOthers();
        }

        return $mesero;
    }

    /**
     * Asignar mesa manualmente a un mesero y actualizar contadores (F7-03).
     */
    public function asignarMesaManual(Mesa $mesa, User $mesero): void
    {
        $mesa->update(['mesero_id' => $mesero->id]);

        $zona = Zona::where('sucursal_id', $mesa->sucursal_id)
            ->where(function ($q) use ($mesa) {
                $q->where('slug', $mesa->zona)
                    ->orWhere('id', is_numeric($mesa->zona) ? (int) $mesa->zona : 0);
            })
            ->first();

        if ($zona) {
            $turno = TurnoMeseroZona::where('zona_id', $zona->id)
                ->where('mesero_id', $mesero->id)
                ->first();

            if ($turno) {
                $turno->increment('mesas_activas');
                $turno->update(['ultimo_asignado_en' => now()]);
            }
        }

        $mesaFresh = $mesa->fresh(['mesero', 'pedidos']);
        if ($mesaFresh) {
            broadcast(new MesaActualizada($mesaFresh))->toOthers();
        }
    }

    /**
     * Decrementar mesas activas del mesero al liberar la mesa (F7-03).
     */
    public function liberarMesa(Mesa $mesa, ?int $meseroId = null): void
    {
        $meseroId = $meseroId ?? $mesa->mesero_id;
        if (! $meseroId) {
            return;
        }

        $zona = Zona::where('sucursal_id', $mesa->sucursal_id)
            ->where(function ($q) use ($mesa) {
                $q->where('slug', $mesa->zona)
                    ->orWhere('id', is_numeric($mesa->zona) ? (int) $mesa->zona : 0);
            })
            ->first();

        $turnoQuery = TurnoMeseroZona::where('mesero_id', $meseroId);
        if ($zona) {
            $turnoQuery->where('zona_id', $zona->id);
        }

        $turno = $turnoQuery->first();
        if ($turno && $turno->mesas_activas > 0) {
            $turno->decrement('mesas_activas');
        }
    }

    /**
     * Asignar un mesero a una zona dentro de la rotación de una sucursal (Legacy + Compatibilidad).
     */
    public function asignarMeseroAZona(
        int $sucursalId,
        string $zonaSlug,
        int $meseroId,
        ?int $zonaId = null,
        string $turno = 'general'
    ): RotacionMesero {
        $zonaSlug = strtolower(trim($zonaSlug));

        if (! $zonaId) {
            $zonaId = Zona::where('sucursal_id', $sucursalId)
                ->where('slug', $zonaSlug)
                ->value('id');
        }

        $maxOrden = RotacionMesero::where('sucursal_id', $sucursalId)
            ->where('zona_slug', $zonaSlug)
            ->where('turno', $turno)
            ->max('orden') ?? 0;

        // Mantener sincronizado con TurnoMeseroZona si existe zonaId
        if ($zonaId) {
            TurnoMeseroZona::updateOrCreate(
                ['zona_id' => $zonaId, 'mesero_id' => $meseroId],
                [
                    'sucursal_id' => $sucursalId,
                    'orden' => $maxOrden + 1,
                    'activo' => true,
                ]
            );
        }

        return RotacionMesero::updateOrCreate(
            [
                'sucursal_id' => $sucursalId,
                'zona_slug' => $zonaSlug,
                'user_id' => $meseroId,
                'turno' => $turno,
            ],
            [
                'zona_id' => $zonaId,
                'orden' => $maxOrden + 1,
                'activo' => true,
            ]
        );
    }

    /**
     * Remover un mesero de una rotación.
     */
    public function removerMeseroDeZona(int $rotacionId): bool
    {
        $rot = RotacionMesero::find($rotacionId);
        if ($rot && $rot->zona_id) {
            TurnoMeseroZona::where('zona_id', $rot->zona_id)
                ->where('mesero_id', $rot->user_id)
                ->delete();
        }

        return (bool) RotacionMesero::where('id', $rotacionId)->delete();
    }

    /**
     * Alternar estado activo/inactivo de un mesero en la rotación del turno.
     */
    public function toggleActivo(int $rotacionId): RotacionMesero
    {
        $rotacion = RotacionMesero::findOrFail($rotacionId);
        $rotacion->update(['activo' => ! $rotacion->activo]);

        if ($rotacion->zona_id) {
            TurnoMeseroZona::where('zona_id', $rotacion->zona_id)
                ->where('mesero_id', $rotacion->user_id)
                ->update(['activo' => $rotacion->activo]);
        }

        return $rotacion->fresh();
    }

    /**
     * Reordenar la cola round-robin de meseros para una zona.
     */
    public function reordenarRotacion(array $ordenIds): void
    {
        DB::transaction(function () use ($ordenIds) {
            foreach ($ordenIds as $posicion => $id) {
                RotacionMesero::where('id', $id)->update(['orden' => $posicion + 1]);
                $rot = RotacionMesero::find($id);
                if ($rot && $rot->zona_id) {
                    TurnoMeseroZona::where('zona_id', $rot->zona_id)
                        ->where('mesero_id', $rot->user_id)
                        ->update(['orden' => $posicion + 1]);
                }
            }
        });
    }

    /**
     * Obtener el estado actual de las rotaciones agrupadas por zona para una sucursal.
     */
    public function obtenerRotacionesPorSucursal(?int $sucursalId = null, string $turno = 'general'): Collection
    {
        $sucursalId = $sucursalId ?? 1;

        return RotacionMesero::with(['mesero.role', 'zona'])
            ->where('sucursal_id', $sucursalId)
            ->where('turno', $turno)
            ->orderBy('zona_slug')
            ->orderBy('orden')
            ->get();
    }

    /**
     * Obtener el siguiente mesero en turno para una zona específica.
     */
    public function obtenerSiguienteMeseroParaZona(
        string $zonaSlug,
        ?int $sucursalId = null,
        string $turno = 'general'
    ): ?User {
        $sucursalId = $sucursalId ?? 1;
        $zonaSlug = strtolower(trim($zonaSlug));

        $rotacionesQuery = RotacionMesero::with('mesero')
            ->where('sucursal_id', $sucursalId)
            ->where('zona_slug', $zonaSlug)
            ->where('turno', $turno)
            ->activos();

        $modo = $this->obtenerModoRotacion($sucursalId);

        if ($modo === 'menor_carga') {
            $rotaciones = $rotacionesQuery->get();
            if ($rotaciones->isEmpty()) {
                return null;
            }

            $seleccionada = $rotaciones->sortBy(function ($rot) {
                return Mesa::where('mesero_id', $rot->user_id)
                    ->whereIn('estado', ['ocupada', 'cuenta_pedida', 'en_cocina'])
                    ->count();
            })->first();

            if ($seleccionada) {
                $seleccionada->update(['ultimo_asignado_en' => now()]);

                return $seleccionada->mesero;
            }
        }

        $siguienteRotacion = $rotacionesQuery
            ->orderByRaw('ultimo_asignado_en ASC NULLS FIRST')
            ->orderBy('orden', 'asc')
            ->first();

        if (! $siguienteRotacion) {
            $fallback = RotacionMesero::with('mesero')
                ->where('sucursal_id', $sucursalId)
                ->activos()
                ->orderByRaw('ultimo_asignado_en ASC NULLS FIRST')
                ->orderBy('orden', 'asc')
                ->first();

            if ($fallback) {
                $fallback->update(['ultimo_asignado_en' => now()]);

                return $fallback->mesero;
            }

            return null;
        }

        $siguienteRotacion->update(['ultimo_asignado_en' => now()]);

        return $siguienteRotacion->mesero;
    }

    /**
     * Autoasignar mesa al siguiente mesero según la zona y rotación.
     */
    public function autoasignarMesa(Mesa $mesa, string $turno = 'general'): ?User
    {
        if ($mesa->mesero_id && $mesa->mesero) {
            return $mesa->mesero;
        }

        $mesero = $this->obtenerSiguienteMeseroParaZona($mesa->zona, $mesa->sucursal_id, $turno);

        if ($mesero) {
            $mesa->update(['mesero_id' => $mesero->id]);
            $mesaFresh = $mesa->fresh(['mesero', 'pedidos']);
            if ($mesaFresh) {
                broadcast(new MesaActualizada($mesaFresh))->toOthers();
            }
        }

        return $mesero;
    }

    /**
     * Asignar mesa y mesero a una reserva según la rotación de la zona.
     */
    public function asignarMesaYMeseroAReserva(
        Reserva $reserva,
        Mesa $mesa,
        string $turno = 'general'
    ): ?User {
        if (! $reserva->mesas()->where('mesas.id', $mesa->id)->exists()) {
            $reserva->mesas()->attach($mesa->id);
        }

        $mesero = $this->autoasignarMesa($mesa, $turno);

        if ($mesero) {
            $reserva->update([
                'mesero_id' => $mesero->id,
                'asignacion_automatica' => true,
            ]);
        }

        return $mesero;
    }

    /**
     * Leer configuración del modo de rotación para la sucursal.
     */
    public function obtenerModoRotacion(?int $sucursalId = null): string
    {
        $sucursalId = $sucursalId ?? 1;
        $clave = "modo_rotacion_meseros_{$sucursalId}";
        $configService = app(ConfiguracionService::class);
        $valor = $configService->obtener('operaciones', $clave)
            ?? $configService->obtener('operaciones', 'modo_rotacion_meseros', 'round_robin');

        return in_array($valor, ['round_robin', 'menor_carga', 'manual'], true) ? $valor : 'round_robin';
    }

    /**
     * Guardar configuración del modo de rotación.
     */
    public function guardarModoRotacion(?int $sucursalId = null, string $modo = 'round_robin'): void
    {
        $sucursalId = $sucursalId ?? 1;
        $clave = "modo_rotacion_meseros_{$sucursalId}";
        app(ConfiguracionService::class)->guardar('operaciones', $clave, $modo);
    }
}
