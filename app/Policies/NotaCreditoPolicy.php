<?php

namespace App\Policies;

use App\Models\User;

class NotaCreditoPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin'], true);
    }

    public function view(User $user): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['cajero', 'admin'], true);
    }
}
