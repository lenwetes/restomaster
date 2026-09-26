<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('mesero.{userId}', function (User $user, int|string $userId): bool {
    return (int) $user->id === (int) $userId || $user->isAdmin();
});

Broadcast::channel('cocina.{sucursalId}', function (User $user, int|string $sucursalId): bool {
    if ($user->isAdmin()) {
        return true;
    }

    return (int) $user->sucursal_id === (int) $sucursalId;
});

Broadcast::channel('sucursal.{sucursalId}', function (User $user, int|string $sucursalId): bool {
    if ($user->isAdmin()) {
        return true;
    }

    return (int) $user->sucursal_id === (int) $sucursalId;
});

Broadcast::channel('caja.{sucursalId}', function (User $user, int|string $sucursalId): bool {
    if ($user->isAdmin()) {
        return true;
    }

    return (int) $user->sucursal_id === (int) $sucursalId;
});
