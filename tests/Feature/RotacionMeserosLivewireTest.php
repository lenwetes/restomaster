<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Role;
use App\Models\RotacionMesero;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RotacionMeserosLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    protected User $mesero;

    protected Zona $zonaSalon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sushixpress Centro']);

        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);
        $roleMesero = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero']);

        $this->admin = User::create([
            'name' => 'Admin General',
            'email' => 'admin@sushixpress.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mateo Mesero',
            'email' => 'mateo@sushixpress.com',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->zonaSalon = Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Salón',
            'slug' => 'salon',
            'color' => 'terracota',
            'icono' => 'mesa',
            'activa' => true,
            'orden' => 1,
        ]);
    }

    public function test_abrir_modal_rotacion_y_cambiar_algoritmo(): void
    {
        $this->actingAs($this->admin);

        Volt::test('mesas.index')
            ->call('abrirModalRotacion')
            ->assertSet('modalRotacionOpen', true)
            ->call('cambiarModoRotacion', 'menor_carga')
            ->assertSet('modoRotacion', 'menor_carga');
    }

    public function test_asignar_y_pausar_mesero_en_rotacion_desde_ui(): void
    {
        $this->actingAs($this->admin);

        $component = Volt::test('mesas.index')
            ->set('rotacionNuevoMeseroId', $this->mesero->id)
            ->set('rotacionNuevaZona', 'salon')
            ->call('agregarMeseroARotacion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('rotaciones_meseros', [
            'sucursal_id' => $this->sucursal->id,
            'zona_slug' => 'salon',
            'user_id' => $this->mesero->id,
            'activo' => true,
        ]);

        $rotacion = RotacionMesero::first();

        // Pausar mesero
        $component->call('toggleActivoRotacion', $rotacion->id);
        $this->assertFalse($rotacion->fresh()->activo);

        // Remover mesero
        $component->call('removerMeseroDeRotacion', $rotacion->id);
        $this->assertDatabaseMissing('rotaciones_meseros', ['id' => $rotacion->id]);
    }

    public function test_autoasignar_mesas_libres_desde_ui(): void
    {
        $this->actingAs($this->admin);

        // Asignar mesero a rotación
        RotacionMesero::create([
            'sucursal_id' => $this->sucursal->id,
            'zona_slug' => 'salon',
            'user_id' => $this->mesero->id,
            'orden' => 1,
            'activo' => true,
        ]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-10',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::LIBRE->value,
            'mesero_id' => null,
        ]);

        Volt::test('mesas.index')
            ->call('autoasignarMesasLibres');

        $this->assertEquals($this->mesero->id, $mesa->fresh()->mesero_id);
    }

    public function test_autoasignar_mesas_libres_rebalancea_cuando_todas_tienen_mesero(): void
    {
        $this->actingAs($this->admin);

        $otroMesero = User::create([
            'name' => 'Sara Mesera',
            'email' => 'sara@sushixpress.com',
            'password' => bcrypt('password'),
            'role_id' => $this->mesero->role_id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        RotacionMesero::create([
            'sucursal_id' => $this->sucursal->id,
            'zona_slug' => 'salon',
            'user_id' => $this->mesero->id,
            'orden' => 1,
            'activo' => true,
        ]);

        RotacionMesero::create([
            'sucursal_id' => $this->sucursal->id,
            'zona_slug' => 'salon',
            'user_id' => $otroMesero->id,
            'orden' => 2,
            'activo' => true,
        ]);

        // Mesa libre que ya tenía asignado el mesero 1
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-11',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::LIBRE->value,
            'mesero_id' => $this->mesero->id,
        ]);

        Volt::test('mesas.index')
            ->call('autoasignarMesasLibres')
            ->assertSet('tipoFlash', 'success');

        $this->assertNotNull($mesa->fresh()->mesero_id);
    }

    public function test_autoasignar_bloqueado_en_modo_manual(): void
    {
        $this->actingAs($this->admin);

        RotacionMesero::create([
            'sucursal_id' => $this->sucursal->id,
            'zona_slug' => 'salon',
            'user_id' => $this->mesero->id,
            'orden' => 1,
            'activo' => true,
        ]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-12',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::LIBRE->value,
            'mesero_id' => null,
        ]);

        // Cambiar a manual
        Volt::test('mesas.index')
            ->call('cambiarModoRotacion', 'manual')
            ->call('autoasignarMesasLibres')
            ->assertSet('tipoFlash', 'warning');

        // La mesa no debe haberse autoasignado
        $this->assertNull($mesa->fresh()->mesero_id);
    }

    public function test_asignar_por_rotacion_especifica_desde_ui(): void
    {
        $this->actingAs($this->admin);

        RotacionMesero::create([
            'sucursal_id' => $this->sucursal->id,
            'zona_slug' => 'salon',
            'user_id' => $this->mesero->id,
            'orden' => 1,
            'activo' => true,
        ]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-13',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::LIBRE->value,
            'mesero_id' => null,
        ]);

        Volt::test('mesas.index')
            ->call('cambiarModoRotacion', 'round_robin')
            ->call('asignarPorRotacion', $mesa->id)
            ->assertSet('tipoFlash', 'success');

        $this->assertEquals($this->mesero->id, $mesa->fresh()->mesero_id);
    }

    public function test_autoasignar_mesas_libres_autodistribuye_cuando_rotaciones_estan_vacias(): void
    {
        $this->actingAs($this->admin);

        // Aseguramos que no hay rotaciones creadas
        RotacionMesero::truncate();

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-20',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::LIBRE->value,
            'mesero_id' => null,
        ]);

        Volt::test('mesas.index')
            ->call('autoasignarMesasLibres')
            ->assertSet('tipoFlash', 'success');

        // Al autoasignar, debió autodistribuir los meseros activos y asignar la mesa
        $this->assertGreaterThan(0, RotacionMesero::count());
        $this->assertNotNull($mesa->fresh()->mesero_id);
    }

    public function test_autodistribuir_meseros_en_zonas_desde_ui(): void
    {
        $this->actingAs($this->admin);

        RotacionMesero::truncate();

        Volt::test('mesas.index')
            ->call('autodistribuirMeserosEnZonas')
            ->assertDispatched('notificacion');

        $this->assertGreaterThan(0, RotacionMesero::count());
    }

    public function test_mesero_no_puede_cobrar_mesa_asignada_a_otro_mesero(): void
    {
        $otroMesero = User::create([
            'name' => 'Otro Mesero',
            'email' => 'otro@sushixpress.com',
            'password' => bcrypt('password'),
            'role_id' => $this->mesero->role_id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-30',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::OCUPADA->value,
            'mesero_id' => $otroMesero->id,
        ]);

        $pedido = \App\Models\Pedido::create([
            'codigo' => 'PED-TEST-RESTR',
            'tipo' => 'mesa',
            'estado' => 'listo',
            'mesa_id' => $mesa->id,
            'mesero_id' => $otroMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'total' => 50000,
            'subtotal' => 50000,
        ]);

        // Actuando como el mesero (no el asignado a la mesa)
        $this->actingAs($this->mesero);

        // En terminal POS, debe detectar que es mesa de otro mesero
        Volt::test('pos.terminal', ['mesaId' => $mesa->id, 'tipo' => 'mesa'])
            ->call('abrirModalCobro')
            ->assertDispatched('notificacion');

        // En PedidoService directo, debe arrojar 403
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\PedidoService::class)->cobrarPedido(
            $pedido,
            'efectivo',
            50000
        );
    }
}
