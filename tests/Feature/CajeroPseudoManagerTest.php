<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CajeroPseudoManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;

    protected User $mesero1;

    protected User $mesero2;

    protected Mesa $mesa;

    protected Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SucursalSeeder::class);

        $sucursal = Sucursal::first();
        $rolCajero = Role::where('slug', 'cajero')->first();
        $rolMesero = Role::where('slug', 'mesero')->first();

        $this->cajero = User::factory()->create([
            'role_id' => $rolCajero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->mesero1 = User::factory()->create([
            'role_id' => $rolMesero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->mesero2 = User::factory()->create([
            'role_id' => $rolMesero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'numero' => 'M1',
            'zona' => 'salon',
            'capacidad' => 4,
            'sucursal_id' => $sucursal->id,
            'estado' => 'ocupada',
            'mesero_id' => $this->mesero1->id,
        ]);

        $this->insumo = Insumo::create([
            'nombre' => 'Salmón Premium Fresco',
            'codigo' => 'SALM-001',
            'categoria' => 'pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 12.5,
            'stock_minimo' => 5.0,
            'capacidad_maxima' => 20.0,
            'costo_unitario' => 48000.0,
            'proveedor_nombre' => 'Pesquera del Pacífico',
            'activo' => true,
        ]);
    }

    public function test_cajero_puede_ver_pantalla_cocina_kds(): void
    {
        $response = $this->actingAs($this->cajero)->get(route('cocina'));

        $response->assertOk();
        $response->assertSee('Pantalla de Cocina');
    }

    public function test_cajero_puede_ver_inventario_en_modo_consulta(): void
    {
        $response = $this->actingAs($this->cajero)->get(route('inventario'));

        $response->assertOk();
        $response->assertSee('Modo Consulta (Solo Lectura)');
        $response->assertSee('Salmón Premium Fresco');
    }

    public function test_cajero_abrir_modal_modificacion_dispara_notificacion_y_modal_restriccion(): void
    {
        Volt::actingAs($this->cajero)
            ->test('inventario.index')
            ->call('abrirModalMerma', $this->insumo->id)
            ->assertSet('modalMermaOpen', false)
            ->assertSet('modalRestriccionOpen', true)
            ->assertDispatched('notificacion')
            ->call('abrirModalCompra', $this->insumo->id)
            ->assertSet('modalCompraOpen', false)
            ->assertSet('modalRestriccionOpen', true)
            ->assertDispatched('notificacion')
            ->call('abrirModalAjuste', $this->insumo->id)
            ->assertSet('modalAjusteOpen', false)
            ->assertSet('modalRestriccionOpen', true)
            ->assertDispatched('notificacion')
            ->call('abrirModalNuevoInsumo')
            ->assertSet('modalNuevoInsumoOpen', false)
            ->assertSet('modalRestriccionOpen', true)
            ->assertDispatched('notificacion');
    }

    public function test_cajero_bloqueado_a_nivel_servidor_con_403_al_intentar_mutar_inventario(): void
    {
        // Intento de merma directo
        Volt::actingAs($this->cajero)
            ->test('inventario.index')
            ->set('selectedInsumoId', $this->insumo->id)
            ->set('mermaCantidad', 1.0)
            ->call('registrarMerma')
            ->assertStatus(403);

        // Intento de compra directo
        Volt::actingAs($this->cajero)
            ->test('inventario.index')
            ->set('selectedInsumoId', $this->insumo->id)
            ->set('compraCantidad', 5.0)
            ->set('compraCostoUnitario', 45000.0)
            ->call('registrarCompra')
            ->assertStatus(403);

        // Intento de ajuste de stock directo
        Volt::actingAs($this->cajero)
            ->test('inventario.index')
            ->set('selectedInsumoId', $this->insumo->id)
            ->set('ajusteNuevoStock', 10.0)
            ->call('registrarAjuste')
            ->assertStatus(403);

        // Intento de crear insumo directo
        Volt::actingAs($this->cajero)
            ->test('inventario.index')
            ->set('nuevoNombre', 'Atún Rojo')
            ->set('nuevoCodigo', 'ATUN-002')
            ->call('guardarNuevoInsumo')
            ->assertStatus(403);
    }

    public function test_cajero_puede_asignar_y_transferir_mesas_a_meseros(): void
    {
        // 1. Cajero abre modal de transferir mesa
        Volt::actingAs($this->cajero)
            ->test('mesas.index')
            ->call('abrirModalTransferir', $this->mesa->id)
            ->assertSet('modalTransferirOpen', true)
            ->set('nuevoMeseroId', $this->mesero2->id)
            ->call('ejecutarTransferenciaMesa')
            ->assertSet('modalTransferirOpen', false)
            ->assertDispatched('notificacion');

        $this->mesa->refresh();
        $this->assertEquals($this->mesero2->id, $this->mesa->mesero_id);
    }

    public function test_cajero_no_ve_reportes_dian_en_navegacion_y_ruta_retorna_403(): void
    {
        // Ruta directa reportes retorna 403
        $this->actingAs($this->cajero)->get(route('reportes'))->assertForbidden();

        // En navegación el enlace a reportes no debe existir
        Volt::actingAs($this->cajero)
            ->test('layout.navigation')
            ->assertDontSee('Reportes DIAN');
    }
}
