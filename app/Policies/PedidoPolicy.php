<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;

class PedidoPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin', 'cocina', 'barra', 'delivery']);
    }

    public function view(User $user, Pedido $pedido): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin', 'cocina', 'barra', 'delivery']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function update(User $user, ?Pedido $pedido = null): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function delete(User $user, ?Pedido $pedido = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function cobrar(User $user, ?Pedido $pedido = null): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function enviarCocina(User $user, ?Pedido $pedido = null): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function aplicarDescuento(User $user, ?Pedido $pedido = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }

    public function canjearPuntos(User $user, ?Pedido $pedido = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }
}
