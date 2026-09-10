<?php

namespace App\Policies;

use App\Models\Reserva;
use App\Models\User;

class ReservaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function view(User $user, Reserva $reserva): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function update(User $user, ?Reserva $reserva = null): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function delete(User $user, ?Reserva $reserva = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function confirmar(User $user, ?Reserva $reserva = null): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function cancelar(User $user, ?Reserva $reserva = null): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }
}
