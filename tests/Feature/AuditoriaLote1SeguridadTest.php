<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditoriaLote1SeguridadTest extends TestCase
{
    use RefreshDatabase;

    private Role $roleAdmin;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Central',
            'codigo' => 'RST-01',
            'direccion' => 'Calle 10 # 40-20',
            'activa' => true,
        ]);
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $usuarioInactivo = User::create([
            'name' => 'Ex Empleado',
            'email' => 'inactivo@restomaster.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => false,
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', 'inactivo@restomaster.com')
            ->set('form.password', 'password123')
            ->call('login')
            ->assertHasErrors(['form.email']);

        $this->assertGuest();
    }

    public function test_middleware_bloquea_usuario_inactivo_autenticado(): void
    {
        $usuarioInactivo = User::create([
            'name' => 'Empleado Desactivado',
            'email' => 'bloqueado@restomaster.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => false,
        ]);

        $response = $this->actingAs($usuarioInactivo)->get('/dashboard');

        // Debe redirigir al login o responder 403
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    public function test_admin_user_seeder_no_sobrescribe_password_de_usuario_existente(): void
    {
        // Creamos al admin con una contraseña personalizada
        $admin = User::create([
            'name' => 'Admin Existente',
            'email' => 'admin@restomaster.com',
            'password' => Hash::make('mi_password_seguro_personalizado_123'),
            'role_id' => $this->roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        // Ejecutamos el seeder
        $this->seed(AdminUserSeeder::class);

        $admin->refresh();

        // La contraseña NO debe haberse reseteado a la contraseña demo
        $this->assertTrue(Hash::check('mi_password_seguro_personalizado_123', $admin->password));
    }
}
