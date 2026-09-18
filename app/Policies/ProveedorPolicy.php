<?php

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;

class ProveedorPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }

    public function view(User $user, Proveedor $proveedor): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }

    public function update(User $user, Proveedor $proveedor): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }

    public function delete(User $user, ?Proveedor $proveedor = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin'], true);
    }
}
