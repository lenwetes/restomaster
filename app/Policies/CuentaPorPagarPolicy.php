<?php

namespace App\Policies;

use App\Models\CuentaPorPagar;
use App\Models\User;

class CuentaPorPagarPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function view(User $user, CuentaPorPagar $cxp): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function update(User $user, ?CuentaPorPagar $cxp = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function delete(User $user, ?CuentaPorPagar $cxp = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function registrarPago(User $user, ?CuentaPorPagar $cxp = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }
}
