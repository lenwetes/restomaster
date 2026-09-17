<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Tests RED — Remedición lote L1 (R1, R2, R3).
 *
 * Hallazgos: docs/auditoria/remediacion-seguridad-rendimiento-2026-09-15.md
 * Requisito: deben FALLAR en HEAD actual (05e9f68+) y pasar tras el fix.
 */
class RemediacionInfraSeguridadTest extends TestCase
{
    use RefreshDatabase;

    public function test_r1_no_existen_secretos_hardcodeados_en_docker_compose(): void
    {
        $archivosCompose = array_merge(
            glob(base_path('compose*.yml')) ?: [],
            glob(base_path('docker-compose.*')) ?: []
        );

        $this->assertNotEmpty($archivosCompose, 'No se encontraron archivos docker-compose en la raíz.');

        $secretosConocidos = [
            'SecretResto2026!',
            'sushixpress2026',
            'sushixpress_secure_password',
            'base64:ryJ8oRftsst90c9',
        ];

        foreach ($archivosCompose as $archivo) {
            $contenido = File::get($archivo);

            foreach ($secretosConocidos as $secreto) {
                $this->assertStringNotContainsString(
                    $secreto,
                    $contenido,
                    "Secreto comprometido '{$secreto}' presente en {$archivo}. Usar \${DB_PASSWORD} sin default real."
                );
            }

            // Auto-seed habilitado por defecto (R2)
            $this->assertStringNotContainsString('AUTO_SEED:-true', $contenido, "AUTO_SEED=true por defecto en {$archivo}.");
            // APP_DEBUG en producción por defecto (R1)
            $this->assertStringNotContainsString('APP_DEBUG:-true', $contenido, "APP_DEBUG=true por defecto en {$archivo}.");
        }

        // La wiki de despliegue no debe publicar el valor real de la contraseña
        $despliegue = File::get(base_path('docs/despliegue-coolify.md'));
        foreach ($secretosConocidos as $secreto) {
            $this->assertStringNotContainsString($secreto, $despliegue, 'Secreto filtrado en docs/despliegue-coolify.md.');
        }
    }

    public function test_r2_seeder_no_cambia_contrasenas_de_usuarios_existentes(): void
    {
        // REGRESIÓN (ya corregido por Antigravity en rebranding 2026-09-15 15:20):
        // AdminUserSeeder ahora hace unset($userData['password']) si el usuario existe.
        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin', 'descripcion' => 'Admin']);
        Sucursal::create([
            'nombre' => 'Sucursal Test',
            'codigo' => 'TST-01',
            'direccion' => 'Calle 1 # 2-3',
            'activa' => true,
        ]);

        // Un usuario admin real ya existente en la BD
        $existente = User::create([
            'name' => 'Administrador Real',
            'email' => 'admin@restomaster.com',
            'password' => bcrypt('clave-segura-unica-uno-dos-tres'),
            'role_id' => $roleAdmin->id,
            'activo' => true,
        ]);

        // Re-ejecutar el seeder (como ocurre en cada boot de Docker con AUTO_SEED)
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseHas('users', [
            'id' => $existente->id,
            'email' => 'admin@restomaster.com',
        ]);

        // La contraseña del usuario existente NO debe haber sido reemplazada
        $existente->refresh();
        $this->assertTrue(
            Hash::check('clave-segura-unica-uno-dos-tres', $existente->password),
            'El seeder sobreescribió la contraseña de un usuario existente (AUTO_SEED).'
        );
    }

    public function test_r3_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        // REGRESIÓN (ya corregido): LoginForm ahora incluye 'activo' => true en Auth::attempt.
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero', 'descripcion' => 'Mesero']);

        $inactivo = User::create([
            'name' => 'Mesero Desactivado',
            'email' => 'inactivo@example.com',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'activo' => false,
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $inactivo->email)
            ->set('form.password', 'password');

        $component->call('login');

        // El usuario con activo=false NO debe quedar autenticado
        $this->assertGuest();
        $component->assertHasErrors();
    }

    public function test_r3_middleware_bloquea_usuario_inactivo_con_sesion_activa_en_ruta_sin_role(): void
    {
        // GAP REAL PENDIENTE DE R3: EnsureUserHasRole solo corre en rutas con 'role:',
        // pero /dashboard usa ['auth', 'verified'] -> un inactivo con sesión entra.
        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin', 'descripcion' => 'Admin']);

        $inactivo = User::create([
            'name' => 'Admin Desactivado',
            'email' => 'admin-inactivo@example.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'activo' => false,
        ]);

        $response = $this->actingAs($inactivo)->get(route('dashboard'));

        // Debe redirigir a login (302/403), nunca responder 200 al dashboard
        $this->assertNotEquals(200, $response->status());
        $this->assertTrue(in_array($response->status(), [302, 403]));
        $this->assertGuest();
    }
}
