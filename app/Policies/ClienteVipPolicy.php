<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClienteVipPolicy
{
    /**
     * Determina si el usuario puede ver la consola y listados del Club VIP.
     */
    public function viewAny(User $user): bool
    {
        return $this->esAdminOGerente($user);
    }

    /**
     * Determina si el usuario puede invitar a un cliente al Club VIP.
     */
    public function invitar(User $user, ?Cliente $cliente = null): bool
    {
        return $this->esAdminOGerente($user);
    }

    /**
     * Determina si el usuario puede aprobar la membresía VIP de un cliente.
     */
    public function aprobar(User $user, ?Cliente $cliente = null): bool
    {
        return $this->esAdminOGerente($user);
    }

    /**
     * Determina si el usuario puede rechazar la membresía VIP de un cliente.
     */
    public function rechazar(User $user, ?Cliente $cliente = null): bool
    {
        return $this->esAdminOGerente($user);
    }

    /**
     * Determina si el usuario puede suspender la membresía VIP de un cliente.
     */
    public function suspender(User $user, ?Cliente $cliente = null): bool
    {
        return $this->esAdminOGerente($user);
    }

    /**
     * Determina si el usuario puede reactivar a un cliente VIP suspendido.
     */
    public function reactivar(User $user, ?Cliente $cliente = null): bool
    {
        return $this->esAdminOGerente($user);
    }

    /**
     * Determina si el usuario puede enviar difusiones a los clientes VIP.
     */
    public function difundir(User $user): bool
    {
        return $this->esAdminOGerente($user);
    }

    protected function esAdminOGerente(User $user): bool
    {
        $rol = strtolower($user->role?->slug ?? '');

        return in_array($rol, ['admin', 'administrador', 'gerente'], true);
    }
}
