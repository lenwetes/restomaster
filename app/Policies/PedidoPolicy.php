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

    public function gestionarDelivery(User $user, ?Pedido $pedido = null): bool
    {
        return in_array($user->role?->slug, ['cajero', 'delivery', 'gerente', 'admin', 'repartidor']);
    }

    public function liquidarRepartidor(User $user, ?int $repartidorId = null): bool
    {
        if (in_array($user->role?->slug, ['cajero', 'gerente', 'admin'])) {
            return true;
        }

        if ($user->role?->slug === 'repartidor' && $repartidorId !== null && $user->id === $repartidorId) {
            return true;
        }

        return false;
    }

    public function cocinar(User $user, ?string $areaCocina = null): bool
    {
        if (in_array($user->role?->slug, ['admin', 'gerente'])) {
            return true;
        }

        if ($user->role?->slug === 'cocina') {
            return $areaCocina === null || in_array($areaCocina, ['sushi', 'cocina', 'caliente', 'calientes', 'fria', 'cocina_fria', 'postres', 'barra', 'bebidas']);
        }

        if ($user->role?->slug === 'barra') {
            return $areaCocina === null || in_array($areaCocina, ['barra', 'bebidas']);
        }

        return false;
    }
}
