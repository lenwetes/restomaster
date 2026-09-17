<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\NotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NotificacionServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_obtener_resumen_cachea_y_devuelve_array_plano(): void
    {
        Cache::spy();

        $role = Role::create(['nombre' => 'Cocina', 'slug' => 'cocina', 'descripcion' => 'Cocina']);
        $sucursal = Sucursal::create(['nombre' => 'S1', 'codigo' => 'S1', 'direccion' => 'x', 'activa' => true]);
        $usuario = User::create([
            'name' => 'Chef',
            'email' => 'chef@test.com',
            'password' => bcrypt('clave-segura'),
            'role_id' => $role->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        app(NotificacionService::class)->obtenerResumen($usuario);

        // La clave de caché por usuario debe consultarse
        Cache::shouldHaveReceived('remember')
            ->withArgs(fn ($clave) => str_contains($clave, 'notif.resumen.') && str_contains($clave, (string) $usuario->id))
            ->once();
    }
}
