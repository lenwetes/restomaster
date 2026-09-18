<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PermisoService
{
    protected array $alias = [
        'viewAny' => 'ver', 'view' => 'ver', 'create' => 'crear',
        'update' => 'actualizar', 'delete' => 'eliminar',
    ];

    public function resolverKey(string $ability, mixed $target): ?string
    {
        if (is_object($target)) {
            $clase = class_basename($target);
        } elseif (is_string($target) && class_exists($target)) {
            $clase = class_basename($target);
        } else {
            return null;
        }

        $prefijo = config('permisos.mapa')[$clase] ?? null;
        if (! $prefijo) {
            return null;
        }

        $habilidad = $this->alias[$ability] ?? Str::snake($ability);
        $key = $prefijo.'.'.$habilidad;

        return in_array($key, array_keys(config('permisos.catalogo', [])), true) ? $key : null;
    }

    public function plantilla(string $slug): array
    {
        $plantillas = config('permisos.plantillas', []);

        if (($plantillas[$slug] ?? null) === '*') {
            return array_keys(config('permisos.catalogo', []));
        }

        return $plantillas[$slug] ?? [];
    }

    public function aplicarPlantilla(User $usuario, string $slug): void
    {
        $this->guardarChecks($usuario, array_fill_keys($this->plantilla($slug), 'otorgar'));
    }

    public function guardarChecks(User $usuario, array $checks): array
    {
        $catalogo = array_keys(config('permisos.catalogo', []));
        $antes = DB::table('permission_user')->where('user_id', $usuario->id)->pluck('tipo', 'permission')->all();
        $otorgados = [];
        $quitados = [];

        foreach ($checks as $key => $estado) {
            if (! in_array($key, $catalogo, true)) {
                throw new InvalidArgumentException("Permiso desconocido: {$key}.");
            }
            if (! in_array($estado, ['otorgar', 'quitar', 'heredar'], true)) {
                throw new InvalidArgumentException("Estado inválido para {$key}: {$estado}.");
            }

            $tenia = $antes[$key] ?? null;

            if ($estado === 'heredar') {
                if ($tenia !== null) {
                    DB::table('permission_user')->where('user_id', $usuario->id)->where('permission', $key)->delete();
                    $quitados[] = $key;
                }

                continue;
            }

            $tipo = $estado === 'otorgar' ? 'grant' : 'deny';
            DB::table('permission_user')->updateOrInsert(
                ['user_id' => $usuario->id, 'permission' => $key],
                ['tipo' => $tipo, 'updated_at' => now(), 'created_at' => now()]
            );

            if ($tenia !== $tipo) {
                if ($estado === 'otorgar') {
                    $otorgados[] = $key;
                } else {
                    $quitados[] = $key;
                }
            }
        }

        $usuario->olvidarPermisosMemo();

        return ['otorgados' => $otorgados, 'quitados' => $quitados];
    }

    public function criticas(): array
    {
        return ['pedidos.cobrar', 'turnos.abrir', 'pedidos.eliminar', 'caja.eliminar'];
    }
}
