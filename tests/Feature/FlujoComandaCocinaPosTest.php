<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FlujoComandaCocinaPosTest extends TestCase
{
    use RefreshDatabase;

    private User $mesero;

    private User $cocinero;

    private User $cajero;

    private Mesa $mesa;

    private Producto $platoParrilla;

    private Producto $bebidaBarra;

    protected function setUp(): void
    {
        parent::setUp();

        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero', 'descripcion' => 'Mesero']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero', 'descripcion' => 'Cajero']);
        $roleCocina = Role::create(['nombre' => 'Cocina', 'slug' => 'cocina', 'descripcion' => 'Cocina']);

        $sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Poblado',
            'codigo' => 'POB-01',
            'direccion' => 'Calle 10 # 43E-20',
            'activa' => true,
        ]);

        $this->mesero = User::factory()->create([
            'name' => 'Mateo Mesero',
            'email' => 'mateo@restomaster.com',
            'role_id' => $roleMesero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->cajero = User::factory()->create([
            'name' => 'Valentina Cajera',
            'email' => 'valentina@restomaster.com',
            'role_id' => $roleCajero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->cocinero = User::factory()->create([
            'name' => 'Chef Andres',
            'email' => 'chef@restomaster.com',
            'role_id' => $roleCocina->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $sucursal->id,
            'numero' => '5',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'libre',
            'activo' => true,
        ]);

        $catCaliente = Categoria::create([
            'nombre' => 'Parrilla & Carnes',
            'slug' => 'parrilla-carnes',
            'area_impresion' => 'caliente',
            'icono' => '🥩',
            'orden' => 1,
            'activo' => true,
        ]);

        $catBarra = Categoria::create([
            'nombre' => 'Bebidas & Cócteles',
            'slug' => 'bebidas-cocteles',
            'area_impresion' => 'barra',
            'icono' => '🍹',
            'orden' => 2,
            'activo' => true,
        ]);

        $this->platoParrilla = Producto::create([
            'categoria_id' => $catCaliente->id,
            'nombre' => 'Churrasco Criollo',
            'slug' => 'churrasco-criollo',
            'precio' => 45000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        $this->bebidaBarra = Producto::create([
            'categoria_id' => $catBarra->id,
            'nombre' => 'Limonada de Coco',
            'slug' => 'limonada-coco',
            'precio' => 12000,
            'area_cocina' => 'barra',
            'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja 1',
            'codigo' => 'CAJA-01',
            'activa' => true,
        ]);

        app(CajaService::class)->abrirTurno($caja, $this->cajero, 200000.00, 'Apertura turno matutino');
    }

    public function test_flujo_completo_comanda_bloqueo_cocina_y_desbloqueo_cobro(): void
    {
        // 1. Mesero abre POS y selecciona la mesa 5
        $pos = Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id);

        // Agrega 1 churrasco y 1 limonada
        $pos->call('agregarProducto', $this->platoParrilla->id)
            ->call('agregarProducto', $this->bebidaBarra->id);

        // Antes de enviar a cocina:
        $this->assertFalse($pos->instance()->comandaYaEnviadaACocina(), 'No debe figurar como enviada antes de accionar el botón.');

        // 2. Mesero pulsa "Enviar a Cocina"
        $pos->call('enviarACocina');

        // Verificar creación de pedido y estado en cocina
        $pedido = Pedido::where('mesa_id', $this->mesa->id)->latest()->first();
        $this->assertNotNull($pedido);
        $this->assertEquals('en_cocina', $pedido->estado);
        $this->assertEquals(2, $pedido->items()->count());

        // 3. Mesero vuelve a consultar la mesa en el POS
        $pos->set('mesaId', $this->mesa->id);

        // Regla POS: El botón "Enviar Cocina" queda deshabilitado (ya enviada)
        $this->assertTrue($pos->instance()->comandaYaEnviadaACocina(), 'Enviar Cocina debe estar deshabilitado porque no hay nuevos items.');

        // 4. Regla POS: El botón "Cobrar" está bloqueado mientras cocina prepara
        $this->assertTrue($pos->instance()->comandaActivaBloqueaCobro(), 'El cobro debe estar bloqueado mientras cocina prepara.');
        $this->assertFalse($pos->instance()->comandaListaParaCobrar(), 'No debe reportarse como lista para cobrar.');

        // Intentar abrir modal de cobro debe disparar advertencia y NO abrir el modal
        $pos->call('abrirModalCobro');
        $this->assertFalse($pos->get('mostrarModalCobro'), 'El modal de cobro no debe abrirse si la cocina está preparando.');

        // 5. En Cocina (KDS): el cocinero visualiza la comanda
        $kds = Volt::actingAs($this->cocinero)
            ->test('cocina.kds')
            ->set('areaSeleccionada', 'todas');

        $pedidosEnKds = $kds->viewData('pedidos');
        $this->assertTrue($pedidosEnKds->contains('id', $pedido->id), 'La comanda debe aparecer en el KDS de cocina.');

        // 6. Cocina termina la preparación ("Marcar Toda Comanda Lista")
        $kds->call('marcarTodaComandaLista', $pedido->id)
            ->assertDispatched('comanda-actualizada');

        $pedido->refresh();
        $this->assertEquals('listo', $pedido->estado, 'El pedido debe pasar a estado listo al completarse en cocina.');

        // 7. En POS: ahora el cobro está desbloqueado para el mesero o cajero
        $this->actingAs($this->mesero);
        $pos->set('mesaId', $this->mesa->id); // refrescar estado de mesa
        $this->assertFalse($pos->instance()->comandaActivaBloqueaCobro(), 'El cobro ya NO debe estar bloqueado.');
        $this->assertTrue($pos->instance()->comandaListaParaCobrar(), 'La comanda debe estar lista para cobrar.');

        // 8. Mesero / Cajero procesa el cobro exitosamente
        $pos->call('abrirModalCobro')
            ->set('metodoPago', 'efectivo')
            ->set('montoPagado', 57000)
            ->call('procesarCobro');

        $pedido->refresh();
        $this->assertEquals('pagado', $pedido->estado, 'El pedido debe quedar en estado pagado tras el cobro.');
        $this->assertEquals(0, $pedido->cambio);

        // Mesa queda liberada / por limpiar
        $this->mesa->refresh();
        $this->assertEquals('por_limpiar', $this->mesa->estado, 'La mesa debe quedar en estado por_limpiar.');
    }
}
