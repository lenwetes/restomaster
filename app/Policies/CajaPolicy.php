<?php

namespace App\Policies;

use App\Models\Caja;
use App\Models\User;

class CajaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function view(User $user, Caja $caja): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function create(User $user): bool
    {
        // Solo gerente y admin pueden crear nuevas cajas o terminales (P0-01)
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function update(User $user, Caja $caja): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function delete(User $user, Caja $caja): bool
    {
        return in_array($user->role?->slug, ['admin']);
    }
}
