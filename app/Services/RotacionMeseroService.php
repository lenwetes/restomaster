<?php

namespace App\Services;

use App\Events\MesaActualizada;
use App\Models\Mesa;
use App\Models\Pedido;
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
        $sucursalId ??= $zonaModel?->sucursal_id ?? 1;

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

        $modo = $this->obtenerModoRotacion($mesa->sucursal_id);
        if ($modo === 'manual') {
            return null;
        }

        $configRotacion = RotacionZona::where('zona_id', $zona->id)->first();
        if ($configRotacion && (! $configRotacion->activa || $configRotacion->modo === 'manual')) {
            return null;
        }

        return $this->autoasignarMesa($mesa);
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
        $meseroId ??= $mesa->mesero_id;
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

        // Regla de Negocio: Un mesero solo puede estar asignado a UNA SOLA ZONA activa por turno.
        // Si ya está asignado a otra zona en esta sucursal, removerlo de la zona previa.
        $rotacionesPrevias = RotacionMesero::where('sucursal_id', $sucursalId)
            ->where('user_id', $meseroId)
            ->where('turno', $turno)
            ->where('zona_slug', '!=', $zonaSlug)
            ->get();

        foreach ($rotacionesPrevias as $rotPrev) {
            if ($rotPrev->zona_id) {
                TurnoMeseroZona::where('zona_id', $rotPrev->zona_id)
                    ->where('mesero_id', $meseroId)
                    ->delete();
            }
            $rotPrev->delete();
        }

        if ($zonaId) {
            TurnoMeseroZona::where('sucursal_id', $sucursalId)
                ->where('mesero_id', $meseroId)
                ->where('zona_id', '!=', $zonaId)
                ->delete();
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
     * Distribuir equitativamente todos los meseros activos entre las zonas de la sucursal
     * garantizando que cada mesero quede en exactamente una zona sin duplicados.
     */
    public function autodistribuirMeserosActivos(?int $sucursalId = null): int
    {
        $sucursalId ??= 1;
        $zonas = Zona::where('sucursal_id', $sucursalId)->where('activa', true)->orderBy('orden')->get();
        if ($zonas->isEmpty()) {
            $zonas = Zona::where('activa', true)->orderBy('orden')->get();
        }

        if ($zonas->isEmpty()) {
            return 0;
        }

        $meseros = User::whereHas('role', fn ($q) => $q->where('slug', 'mesero'))
            ->where('activo', true)
            ->when($sucursalId, fn ($q) => $q->where(fn ($sq) => $sq->where('sucursal_id', $sucursalId)->orWhereNull('sucursal_id')))
            ->orderBy('id')
            ->get();

        if ($meseros->isEmpty()) {
            return 0;
        }

        // Limpiar asignaciones previas en la sucursal para evitar meseros duplicados entre zonas
        RotacionMesero::where('sucursal_id', $sucursalId)->where('turno', 'general')->delete();
        TurnoMeseroZona::where('sucursal_id', $sucursalId)->delete();

        $numZonas = $zonas->count();
        $distribuidos = 0;

        foreach ($meseros as $index => $mesero) {
            $zona = $zonas[$index % $numZonas];
            $this->asignarMeseroAZona($sucursalId, $zona->slug, $mesero->id, $zona->id);
            $distribuidos++;
        }

        return $distribuidos;
    }

    /**
     * Eliminar asignaciones duplicadas de meseros entre diferentes zonas (regla: 1 mesero = 1 zona).
     */
    public function deduplicarRotaciones(?int $sucursalId = null, string $turno = 'general'): int
    {
        $sucursalId ??= 1;
        $rotaciones = RotacionMesero::where('sucursal_id', $sucursalId)
            ->where('turno', $turno)
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $vistos = [];
        $eliminados = 0;

        foreach ($rotaciones as $rot) {
            if (isset($vistos[$rot->user_id])) {
                if ($rot->zona_id) {
                    TurnoMeseroZona::where('zona_id', $rot->zona_id)
                        ->where('mesero_id', $rot->user_id)
                        ->delete();
                }
                $rot->delete();
                $eliminados++;
            } else {
                $vistos[$rot->user_id] = $rot->zona_slug;
            }
        }

        return $eliminados;
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
        $sucursalId ??= 1;

        // Auto-reparación: Garantizar que no existan duplicados residuales antes de listar
        $this->deduplicarRotaciones($sucursalId, $turno);

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
        $sucursalId ??= 1;
        $zonaSlug = strtolower(trim($zonaSlug));

        $modo = $this->obtenerModoRotacion($sucursalId);
        if ($modo === 'manual') {
            return null;
        }

        $rotacionesQuery = RotacionMesero::with('mesero')
            ->where('sucursal_id', $sucursalId)
            ->where('zona_slug', $zonaSlug)
            ->where('turno', $turno)
            ->activos();

        if ($modo === 'menor_carga') {
            $rotaciones = $rotacionesQuery->get();
            if ($rotaciones->isEmpty()) {
                // Fallback a rotaciones activas de la sucursal
                $rotaciones = RotacionMesero::with('mesero')
                    ->where('sucursal_id', $sucursalId)
                    ->where('turno', $turno)
                    ->activos()
                    ->get();
            }

            if ($rotaciones->isEmpty()) {
                return null;
            }

            $seleccionada = $rotaciones->sortBy(function ($rot) {
                $mesasActivas = Mesa::where('mesero_id', $rot->user_id)
                    ->whereIn('estado', ['ocupada', 'cuenta_pedida', 'en_cocina'])
                    ->count();
                $pedidosActivos = Pedido::where('mesero_id', $rot->user_id)
                    ->activos()
                    ->count();

                return $mesasActivas + $pedidosActivos;
            })->first();

            if ($seleccionada) {
                $seleccionada->update(['ultimo_asignado_en' => now()]);
                if ($seleccionada->zona_id) {
                    TurnoMeseroZona::where('zona_id', $seleccionada->zona_id)
                        ->where('mesero_id', $seleccionada->user_id)
                        ->increment('mesas_activas', 1, ['ultimo_asignado_en' => now()]);
                }

                return $seleccionada->mesero;
            }
        }

        $siguienteRotacion = $rotacionesQuery
            ->orderByRaw('ultimo_asignado_en ASC NULLS FIRST')
            ->orderBy('orden', 'asc')
            ->first();

        if (! $siguienteRotacion) {
            // Fallback a TurnoMeseroZona por zona o sucursal
            $turnoZona = TurnoMeseroZona::with('mesero')
                ->where('activo', true)
                ->where(function ($q) use ($zonaSlug, $sucursalId) {
                    $q->whereHas('zona', fn ($zq) => $zq->where('slug', $zonaSlug)->where('sucursal_id', $sucursalId))
                        ->orWhereHas('zona', fn ($zq) => $zq->where('slug', $zonaSlug))
                        ->orWhere('sucursal_id', $sucursalId);
                })
                ->orderByRaw('ultimo_asignado_en ASC NULLS FIRST')
                ->orderBy('orden', 'asc')
                ->first();

            if ($turnoZona && $turnoZona->mesero) {
                $turnoZona->increment('mesas_activas', 1, ['ultimo_asignado_en' => now()]);

                return $turnoZona->mesero;
            }

            $fallback = RotacionMesero::with('mesero')
                ->where('sucursal_id', $sucursalId)
                ->activos()
                ->orderByRaw('ultimo_asignado_en ASC NULLS FIRST')
                ->orderBy('orden', 'asc')
                ->first();

            if ($fallback) {
                $fallback->update(['ultimo_asignado_en' => now()]);
                if ($fallback->zona_id) {
                    TurnoMeseroZona::where('zona_id', $fallback->zona_id)
                        ->where('mesero_id', $fallback->user_id)
                        ->increment('mesas_activas', 1, ['ultimo_asignado_en' => now()]);
                }

                return $fallback->mesero;
            }

            return null;
        }

        $siguienteRotacion->update(['ultimo_asignado_en' => now()]);
        if ($siguienteRotacion->zona_id) {
            TurnoMeseroZona::where('zona_id', $siguienteRotacion->zona_id)
                ->where('mesero_id', $siguienteRotacion->user_id)
                ->increment('mesas_activas', 1, ['ultimo_asignado_en' => now()]);
        }

        return $siguienteRotacion->mesero;
    }

    /**
     * Autoasignar mesa al siguiente mesero según la zona y rotación.
     */
    public function autoasignarMesa(Mesa $mesa, string $turno = 'general', bool $forzar = false): ?User
    {
        if (! $forzar && $mesa->mesero_id && $mesa->mesero) {
            return $mesa->mesero;
        }

        $modo = $this->obtenerModoRotacion($mesa->sucursal_id);
        if ($modo === 'manual') {
            return null;
        }

        $mesero = $this->obtenerSiguienteMeseroParaZona($mesa->zona, $mesa->sucursal_id, $turno);

        if ($mesero) {
            $mesa->update(['mesero_id' => $mesero->id]);

            // Si la mesa se fuerza/releva o tiene pedidos sin mesero, transferirlos al nuevo mesero
            if ($forzar) {
                $mesa->pedidos()->activos()->update(['mesero_id' => $mesero->id]);
            } else {
                $mesa->pedidos()->activos()->whereNull('mesero_id')->update(['mesero_id' => $mesero->id]);
            }

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
        $sucursalId ??= 1;
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
        $sucursalId ??= 1;
        $clave = "modo_rotacion_meseros_{$sucursalId}";
        app(ConfiguracionService::class)->guardar('operaciones', $clave, $modo);

        // Mantener sincronizado RotacionZona
        RotacionZona::where('sucursal_id', $sucursalId)->update([
            'modo' => $modo === 'manual' ? 'manual' : 'automatico',
            'activa' => $modo !== 'manual',
        ]);
    }
}
