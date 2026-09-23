<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zona;

class ZonaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function view(User $user, Zona $zona): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function update(User $user, ?Zona $zona = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function mover(User $user, ?Zona $zona = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }
}
