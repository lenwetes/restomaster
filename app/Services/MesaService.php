<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class MesaService
{
    /**
     * Get all tables for a given branch or all tables.
     */
    public function getMesasPorSucursal(?int $sucursalId = null): Collection
    {
        $query = Mesa::query()->with('sucursal');

        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }

        return $query->orderBy('numero')->get();
    }

    /**
     * Change table status.
     */
    public function cambiarEstado(Mesa $mesa, MesaEstado|string $nuevoEstado): Mesa
    {
        $estadoValor = $nuevoEstado instanceof MesaEstado ? $nuevoEstado->value : $nuevoEstado;
        $mesa->update(['estado' => $estadoValor]);

        return $mesa->fresh();
    }

    /**
     * Crear una nueva mesa en el restaurante.
     */
    public function crearMesa(array $datos, ?User $usuario = null): Mesa
    {
        $sucursalId = $datos['sucursal_id'] ?? Sucursal::value('id') ?? 1;

        $mesa = Mesa::create([
            'sucursal_id' => $sucursalId,
            'numero' => trim((string) $datos['numero']),
            'capacidad' => max(1, (int) ($datos['capacidad'] ?? 4)),
            'zona' => strtolower($datos['zona'] ?? 'salon'),
            'estado' => MesaEstado::LIBRE->value,
        ]);

        if ($usuario) {
            app(AuditoriaService::class)->registrar(
                usuario: $usuario,
                accion: 'mesas.creada',
                entidad: 'mesa',
                entidadId: $mesa->id,
                descripcion: "Mesa #{$mesa->numero} creada en zona {$mesa->zona} con capacidad {$mesa->capacidad} personas.",
                datos: $mesa->toArray()
            );
        }

        return $mesa;
    }

    /**
     * Actualizar los datos de una mesa existente.
     */
    public function actualizarMesa(Mesa $mesa, array $datos, ?User $usuario = null): Mesa
    {
        $mesa->update([
            'numero' => isset($datos['numero']) ? trim((string) $datos['numero']) : $mesa->numero,
            'capacidad' => isset($datos['capacidad']) ? max(1, (int) $datos['capacidad']) : $mesa->capacidad,
            'zona' => isset($datos['zona']) ? strtolower($datos['zona']) : $mesa->zona,
            'sucursal_id' => $datos['sucursal_id'] ?? $mesa->sucursal_id,
        ]);

        if ($usuario) {
            app(AuditoriaService::class)->registrar(
                usuario: $usuario,
                accion: 'mesas.actualizada',
                entidad: 'mesa',
                entidadId: $mesa->id,
                descripcion: "Mesa #{$mesa->numero} actualizada (zona {$mesa->zona}, capacidad {$mesa->capacidad}).",
                datos: $mesa->toArray()
            );
        }

        return $mesa->fresh();
    }

    /**
     * Eliminar una mesa si no tiene pedidos pendientes ni en curso.
     */
    public function eliminarMesa(Mesa $mesa, ?User $usuario = null): bool
    {
        $pedidosActivos = $mesa->pedidos()
            ->whereIn('estado', ['creado', 'en_cocina', 'listo', 'entregado'])
            ->count();

        if ($pedidosActivos > 0) {
            throw new \InvalidArgumentException("No se puede eliminar la Mesa #{$mesa->numero} porque tiene pedidos activos en curso.");
        }

        $reservasAsociadas = $mesa->reservas()->count();
        if ($reservasAsociadas > 0) {
            throw new \InvalidArgumentException("No se puede eliminar la Mesa #{$mesa->numero} porque tiene {$reservasAsociadas} reserva(s) asociadas en el historial.");
        }

        $numero = $mesa->numero;
        $zona = $mesa->zona;
        $id = $mesa->id;

        $deleted = (bool) $mesa->delete();

        if ($deleted && $usuario) {
            app(AuditoriaService::class)->registrar(
                usuario: $usuario,
                accion: 'mesas.eliminada',
                entidad: 'mesa',
                entidadId: $id,
                descripcion: "Mesa #{$numero} eliminada del sistema.",
                datos: ['numero' => $numero, 'zona' => $zona]
            );
        }

        return $deleted;
    }
}
