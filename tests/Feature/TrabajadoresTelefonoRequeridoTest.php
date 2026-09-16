<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TrabajadoresTelefonoRequeridoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $rolCajero;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['nombre' => 'Administrador', 'descripcion' => 'Admin']
        );
        $this->rolCajero = Role::firstOrCreate(
            ['slug' => 'cajero'],
            ['nombre' => 'Cajero', 'descripcion' => 'Caja']
        );

        $this->sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'SUC-01'],
            ['nombre' => 'Sucursal Centro', 'direccion' => 'Cra 43 # 50-10', 'activa' => true]
        );

        $this->admin = User::create([
            'name' => 'Admin Sistema',
            'email' => 'admin.trabajadores@sushixpress.com',
            'telefono' => '3000000000',
            'role_id' => $rolAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);
    }

    public function test_crear_trabajador_falla_si_falta_telefono(): void
    {
        $this->actingAs($this->admin);

        Volt::test('trabajadores.index')
            ->call('abrirModalNuevo')
            ->set('nuevo.nombre', 'Carlos Andrés Perez')
            ->set('nuevo.email', 'carlos.perez@sushixpress.com')
            ->set('nuevo.telefono', '') // Vacío intencionalmente
            ->set('nuevo.password', 'secret123')
            ->set('nuevo.role_id', $this->rolCajero->id)
            ->set('nuevo.sucursal_id', $this->sucursal->id)
            ->call('guardarNuevo')
            ->assertHasErrors(['nuevo.telefono' => 'required']);

        $this->assertDatabaseMissing('users', [
            'email' => 'carlos.perez@sushixpress.com',
        ]);
    }

    public function test_crear_trabajador_exitoso_con_telefono_y_datos_completos(): void
    {
        $this->actingAs($this->admin);

        Volt::test('trabajadores.index')
            ->call('abrirModalNuevo')
            ->set('nuevo.nombre', 'Laura Gómez Soto')
            ->set('nuevo.email', 'laura.gomez@sushixpress.com')
            ->set('nuevo.telefono', '3157890123')
            ->set('nuevo.password', 'secret123')
            ->set('nuevo.role_id', $this->rolCajero->id)
            ->set('nuevo.sucursal_id', $this->sucursal->id)
            ->call('guardarNuevo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'Laura Gómez Soto',
            'email' => 'laura.gomez@sushixpress.com',
            'telefono' => '3157890123',
        ]);
    }

    public function test_crear_trabajador_falla_con_email_duplicado(): void
    {
        $this->actingAs($this->admin);

        Volt::test('trabajadores.index')
            ->call('abrirModalNuevo')
            ->set('nuevo.nombre', 'Otro Admin')
            ->set('nuevo.email', 'admin.trabajadores@sushixpress.com') // Ya existe
            ->set('nuevo.telefono', '3209998877')
            ->set('nuevo.password', 'secret123')
            ->set('nuevo.role_id', $this->rolCajero->id)
            ->call('guardarNuevo')
            ->assertHasErrors(['nuevo.email']);
    }
}
