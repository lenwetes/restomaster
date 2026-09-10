<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MesaCrudTest extends TestCase
{
    use RefreshDatabase;

    private MesaService $mesaService;

    private Sucursal $sucursal;

    private User $admin;

    private User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Boss',
            'email' => 'admin@sushixpress.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleAdmin->id,
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero Juan',
            'email' => 'juan@sushixpress.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleMesero->id,
            'activo' => true,
        ]);

        $this->mesaService = app(MesaService::class);
    }

    public function test_service_puede_crear_actualizar_y_eliminar_mesa(): void
    {
        $mesa = $this->mesaService->crearMesa([
            'numero' => '15',
            'zona' => 'terraza',
            'capacidad' => 6,
            'sucursal_id' => $this->sucursal->id,
        ], $this->admin);

        $this->assertDatabaseHas('mesas', [
            'id' => $mesa->id,
            'numero' => '15',
            'zona' => 'terraza',
            'capacidad' => 6,
            'estado' => MesaEstado::LIBRE->value,
        ]);

        // Actualizar
        $this->mesaService->actualizarMesa($mesa, [
            'capacidad' => 8,
            'zona' => 'vip',
        ], $this->admin);

        $this->assertDatabaseHas('mesas', [
            'id' => $mesa->id,
            'capacidad' => 8,
            'zona' => 'vip',
        ]);

        // Eliminar
        $resultado = $this->mesaService->eliminarMesa($mesa, $this->admin);
        $this->assertTrue($resultado);
        $this->assertDatabaseMissing('mesas', ['id' => $mesa->id]);
    }

    public function test_no_permite_eliminar_mesa_con_pedidos_activos(): void
    {
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '99',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'ocupada',
        ]);

        Pedido::create([
            'codigo' => 'ORD-MESA-ACTIVA',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'mesa_id' => $mesa->id,
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->mesaService->eliminarMesa($mesa, $this->admin);
    }

    public function test_livewire_volt_crear_y_editar_mesa(): void
    {
        $this->actingAs($this->admin);

        Volt::test('mesas.index')
            ->call('abrirModalNuevaMesa')
            ->assertSet('modalMesaOpen', true)
            ->set('formMesa.numero', '25')
            ->set('formMesa.zona', 'barra')
            ->set('formMesa.capacidad', 2)
            ->set('formMesa.sucursal_id', $this->sucursal->id)
            ->call('guardarMesa')
            ->assertHasNoErrors()
            ->assertSet('modalMesaOpen', false);

        $this->assertDatabaseHas('mesas', [
            'numero' => '25',
            'zona' => 'barra',
            'capacidad' => 2,
        ]);

        $mesa = Mesa::where('numero', '25')->first();

        // Editar
        Volt::test('mesas.index')
            ->call('abrirModalEditarMesa', $mesa->id)
            ->assertSet('modalMesaOpen', true)
            ->set('formMesa.capacidad', 4)
            ->call('guardarMesa')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mesas', [
            'id' => $mesa->id,
            'capacidad' => 4,
        ]);

        // Eliminar
        Volt::test('mesas.index')
            ->call('eliminarMesa', $mesa->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('mesas', ['id' => $mesa->id]);
    }
}
