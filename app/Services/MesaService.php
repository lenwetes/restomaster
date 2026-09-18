<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

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
        $estadoActual = $mesa->estado instanceof MesaEstado ? $mesa->estado : MesaEstado::tryFrom((string) $mesa->estado);
        $target = $nuevoEstado instanceof MesaEstado ? $nuevoEstado : MesaEstado::tryFrom((string) $nuevoEstado);

        if (! $target) {
            throw new \InvalidArgumentException("Estado de mesa inválido: {$nuevoEstado}");
        }

        if ($estadoActual && $estadoActual !== $target && ! $estadoActual->puedeTransicionarA($target)) {
            throw new \DomainException("Transición de mesa no permitida de '{$estadoActual->value}' a '{$target->value}'.");
        }

        // Bloquear cambio a libre si la mesa tiene pedidos activos
        if ($target === MesaEstado::LIBRE) {
            $pedidosActivos = $mesa->pedidos()->activos()->exists();

            if ($pedidosActivos) {
                throw new \DomainException("No se puede liberar la Mesa #{$mesa->numero} porque tiene una comanda activa en curso.");
            }
        }

        $mesa->update(['estado' => $target->value]);
        Cache::forget('pos.terminal.mesas');

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

        Cache::forget('pos.terminal.mesas');

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

        Cache::forget('pos.terminal.mesas');

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
        $pedidosActivos = $mesa->pedidos()->activos()->count();

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

        if ($deleted) {
            Cache::forget('pos.terminal.mesas');
        }

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

    /**
     * Regla única: un mesero no opera mesas asignadas a otro mesero.
     * Cajero/gerente/admin y contextos sin actor (QR público, sistema) pasan.
     */
    public function validarDisponiblePara(Mesa $mesa, ?User $usuario = null): void
    {
        $actor = $usuario ?? auth()->user();

        if ($mesa->mesero_id && $actor && $actor->isMesero() && (int) $mesa->mesero_id !== (int) $actor->id) {
            $nombre = $mesa->mesero?->name ?? 'otro mesero';
            throw new AuthorizationException("Mesa #{$mesa->numero} atendida por {$nombre}: pídele que la libere o solicita una transferencia.");
        }
    }

    /**
     * Autoasignar una mesa al mesero autenticado o seleccionado.
     */
    public function autoasignarMesa(Mesa $mesa, User $mesero): Mesa
    {
        $this->validarDisponiblePara($mesa, $mesero);

        $mesa->update(['mesero_id' => $mesero->id]);

        // Si la mesa tiene pedidos activos, reasignarlos al nuevo mesero
        foreach ($mesa->pedidos()->activos()->get() as $pedido) {
            $pedido->update(['mesero_id' => $mesero->id]);
        }

        Cache::forget('pos.terminal.mesas');

        app(AuditoriaService::class)->registrar(
            usuario: $mesero,
            accion: 'mesas.autoasignada',
            entidad: 'mesa',
            entidadId: $mesa->id,
            descripcion: "Mesero {$mesero->name} se autoasignó la Mesa #{$mesa->numero}.",
            datos: ['mesa_id' => $mesa->id, 'mesero_id' => $mesero->id]
        );

        return $mesa->fresh(['mesero', 'pedidos']);
    }

    /**
     * Liberar la mesa para relevo de turno o descanso sin cerrar pedidos activos.
     */
    public function liberarParaRelevo(Mesa $mesa, User $mesero): Mesa
    {
        $anteriorMeseroId = $mesa->mesero_id;
        $mesa->update(['mesero_id' => null]);

        Cache::forget('pos.terminal.mesas');

        app(AuditoriaService::class)->registrar(
            usuario: $mesero,
            accion: 'mesas.liberada_relevo',
            entidad: 'mesa',
            entidadId: $mesa->id,
            descripcion: "Mesero {$mesero->name} liberó la Mesa #{$mesa->numero} para relevo de turno.",
            datos: [
                'mesa_id' => $mesa->id,
                'mesero_anterior_id' => $anteriorMeseroId,
                'estado_mesa' => $mesa->estado,
            ]
        );

        return $mesa->fresh(['mesero', 'pedidos']);
    }

    /**
     * Asignar o desasignar mesero a una mesa (por administrador o capitán).
     */
    public function asignarMesero(Mesa $mesa, ?User $mesero, ?User $autorizadoPor = null): Mesa
    {
        $meseroAnteriorId = $mesa->mesero_id;
        $mesa->update(['mesero_id' => $mesero?->id]);

        $pedidoActivo = $mesa->pedidos()->activos()->latest()->first();
        if ($pedidoActivo && $mesero) {
            $pedidoActivo->update(['mesero_id' => $mesero->id]);
        }

        Cache::forget('pos.terminal.mesas');

        if ($autorizadoPor) {
            $nombreMesero = $mesero ? $mesero->name : 'Sin asignar';
            app(AuditoriaService::class)->registrar(
                usuario: $autorizadoPor,
                accion: 'mesas.mesero_asignado',
                entidad: 'mesa',
                entidadId: $mesa->id,
                descripcion: "Mesa #{$mesa->numero} asignada a {$nombreMesero} por {$autorizadoPor->name}.",
                datos: ['mesa_id' => $mesa->id, 'mesero_anterior_id' => $meseroAnteriorId, 'nuevo_mesero_id' => $mesero?->id]
            );
        }

        return $mesa->fresh(['mesero', 'pedidos']);
    }

    /**
     * Transferir mesa libremente de un mesero a otro (con auditoría).
     */
    public function transferirMesa(Mesa $mesa, User $nuevoMesero, User $solicitante): Mesa
    {
        $anteriorMesero = $mesa->mesero?->name ?? 'Sin asignar';
        $mesa->update(['mesero_id' => $nuevoMesero->id]);

        // Transferir también la comanda activa si existe
        $pedidoActivo = $mesa->pedidos()->activos()->latest()->first();
        if ($pedidoActivo) {
            $pedidoActivo->update(['mesero_id' => $nuevoMesero->id]);
        }

        Cache::forget('pos.terminal.mesas');

        app(AuditoriaService::class)->registrar(
            usuario: $solicitante,
            accion: 'mesas.transferida',
            entidad: 'mesa',
            entidadId: $mesa->id,
            descripcion: "Mesa #{$mesa->numero} transferida de {$anteriorMesero} a {$nuevoMesero->name} por {$solicitante->name}.",
            datos: [
                'mesa_id' => $mesa->id,
                'mesero_anterior' => $anteriorMesero,
                'nuevo_mesero_id' => $nuevoMesero->id,
                'solicitante_id' => $solicitante->id,
            ]
        );

        return $mesa->fresh(['mesero', 'pedidos']);
    }

    /**
     * Liberar la asignación de mesero cuando la mesa queda limpia.
     */
    public function liberarMesa(Mesa $mesa): Mesa
    {
        $mesa->update([
            'estado' => MesaEstado::LIBRE->value,
            'mesero_id' => null,
        ]);

        Cache::forget('pos.terminal.mesas');

        return $mesa->fresh(['mesero']);
    }
}
