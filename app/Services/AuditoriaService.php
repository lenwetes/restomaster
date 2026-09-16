<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AuditoriaService
{
    /**
     * Registra un evento de auditoría. El usuario se toma del parámetro o del contexto autenticado.
     */
    public function registrar(
        ?User $usuario = null,
        string $accion = '',
        string $entidad = '',
        ?int $entidadId = null,
        ?string $descripcion = null,
        array $datos = [],
    ): Auditoria {
        return Auditoria::create([
            'user_id' => $usuario?->id ?? auth()->id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'descripcion' => $descripcion,
            'datos' => $datos ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    /**
     * Devuelve la traza de auditoría de una entidad concreta (más recientes primero).
     */
    public function porEntidad(string $entidad, int $entidadId, int $limite = 200): Collection
    {
        return Auditoria::with('usuario')
            ->where('entidad', $entidad)
            ->where('entidad_id', $entidadId)
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /**
     * Devuelve auditorías filtradas por acción.
     */
    public function porAccion(string $accion, int $limite = 200): Collection
    {
        return Auditoria::with('usuario')
            ->where('accion', $accion)
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }
}
