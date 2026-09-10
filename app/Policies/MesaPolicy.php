<?php

namespace App\Policies;

use App\Models\Mesa;
use App\Models\User;

class MesaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function view(User $user, Mesa $mesa): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function create(User $user): bool
    {
        // Solo gerente y admin pueden crear mesas (P0-01)
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function update(User $user, ?Mesa $mesa = null): bool
    {
        // Solo gerente y admin pueden editar configuración de mesas (P0-01)
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function delete(User $user, ?Mesa $mesa = null): bool
    {
        // Solo gerente y admin pueden eliminar mesas (P0-01)
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function cambiarEstado(User $user, ?Mesa $mesa = null): bool
    {
        // Mesero, cajero, gerente y admin pueden cambiar estado operacional
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }
}
