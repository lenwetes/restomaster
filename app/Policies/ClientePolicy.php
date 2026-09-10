<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin', 'delivery']);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin', 'delivery']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function update(User $user, ?Cliente $cliente = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function ajustarPuntos(User $user, ?Cliente $cliente = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }
}
