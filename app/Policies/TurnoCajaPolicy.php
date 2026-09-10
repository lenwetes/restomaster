<?php

namespace App\Policies;

use App\Models\TurnoCaja;
use App\Models\User;

class TurnoCajaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function view(User $user, TurnoCaja $turno): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function abrir(User $user): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function cerrar(User $user, ?TurnoCaja $turno = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function arqueo(User $user, ?TurnoCaja $turno = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function guardarMovimiento(User $user, ?TurnoCaja $turno = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }
}
