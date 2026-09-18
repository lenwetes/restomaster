<?php

namespace App\Policies;

use App\Models\Compra;
use App\Models\User;

class CompraPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }

    public function view(User $user, Compra $compra): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }

    public function anular(User $user, ?Compra $compra = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }
}
