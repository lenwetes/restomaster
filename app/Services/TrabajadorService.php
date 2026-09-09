<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class TrabajadorService
{
    /**
     * Crea un trabajador con rol y credenciales.
     */
    public function crear(array $datos): User
    {
        $email = trim($datos['email'] ?? '');

        if (User::where('email', $email)->exists()) {
            throw new InvalidArgumentException("Ya existe un trabajador con el email {$email}.");
        }

        $roleId = $datos['role_id'] ?? null;
        if (! Role::where('id', $roleId)->exists()) {
            throw new InvalidArgumentException('El rol seleccionado no existe.');
        }

        $name = $datos['name'] ?? $datos['nombre'] ?? '';

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'telefono' => $datos['telefono'] ?? null,
            'role_id' => $roleId,
            'activo' => $datos['activo'] ?? true,
            'password' => Hash::make($datos['password'] ?? 'secret'),
        ]);

        app(AuditoriaService::class)->registrar(
            accion: 'trabajador.creado',
            entidad: 'usuario',
            entidadId: $user->id,
            descripcion: "Se creó el trabajador {$name} con el rol {$roleId}",
            datos: ['name' => $name, 'email' => $email, 'role_id' => $roleId],
        );

        return $user;
    }

    /**
     * Actualiza datos y rol de un trabajador (nunca de forma destructiva).
     */
    public function actualizar(User $trabajador, array $datos): User
    {
        if (isset($datos['nombre']) && ! isset($datos['name'])) {
            $datos['name'] = $datos['nombre'];
        }

        if (isset($datos['email'])) {
            $email = trim($datos['email']);
            $emailDuplicado = User::where('email', $email)
                ->where('id', '!=', $trabajador->id)
                ->exists();

            if ($emailDuplicado) {
                throw new InvalidArgumentException("Ya existe otro trabajador con el email {$email}.");
            }

            $trabajador->email = $email;
        }

        foreach (['name', 'telefono', 'role_id'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $trabajador->{$campo} = $datos[$campo];
            }
        }

        if (isset($datos['password']) && $datos['password'] !== '') {
            $trabajador->password = Hash::make($datos['password']);
        }

        $trabajador->save();

        app(AuditoriaService::class)->registrar(
            accion: 'trabajador.actualizado',
            entidad: 'usuario',
            entidadId: $trabajador->id,
            descripcion: "Se actualizó el trabajador {$trabajador->name}",
            datos: $datos,
        );

        return $trabajador;
    }

    /**
     * Marca a un trabajador como inactivo (nunca se elimina físicamente).
     */
    public function desactivar(User $trabajador): User
    {
        if ($trabajador->isAdmin()) {
            throw new InvalidArgumentException('Un administrador no puede desactivarse a sí mismo.');
        }

        $trabajador->update(['activo' => false]);

        app(AuditoriaService::class)->registrar(
            accion: 'trabajador.desactivado',
            entidad: 'usuario',
            entidadId: $trabajador->id,
            descripcion: "Se desactivó el trabajador {$trabajador->name}",
        );

        return $trabajador;
    }

    /**
     * Reactiva a un trabajador inactivo.
     */
    public function reactivar(User $trabajador): User
    {
        $trabajador->update(['activo' => true]);

        app(AuditoriaService::class)->registrar(
            accion: 'trabajador.reactivado',
            entidad: 'usuario',
            entidadId: $trabajador->id,
            descripcion: "Se reactivó el trabajador {$trabajador->name}",
        );

        return $trabajador;
    }
}