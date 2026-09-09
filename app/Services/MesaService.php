<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Mesa;
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
    public function cambiarEstado(Mesa $mesa, MesaEstado $nuevoEstado): Mesa
    {
        $mesa->update(['estado' => $nuevoEstado->value]);

        return $mesa->fresh();
    }
}
