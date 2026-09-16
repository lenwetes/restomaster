<?php

namespace App\Policies;

use App\Models\Insumo;
use App\Models\User;

class InsumoPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function view(User $user, Insumo $insumo): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function update(User $user, Insumo $insumo): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function delete(User $user, Insumo $insumo): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function registrarCompra(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function registrarMerma(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function ajusteFisico(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }
}
