<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeliveryPublicoWebTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Categoria $categoria;

    private Producto $producto1;

    private Producto $producto2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->categoria = Categoria::create([
            'nombre' => 'Rolls Especiales',
            'slug' => 'rolls-especiales',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->producto1 = Producto::create([
            'nombre' => 'Salmón Trufado Roll',
            'slug' => 'salmon-trufado-roll',
            'categoria_id' => $this->categoria->id,
            'precio' => 42000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $this->producto2 = Producto::create([
            'nombre' => 'Dragon Imperial Roll',
            'slug' => 'dragon-imperial-roll',
            'categoria_id' => $this->categoria->id,
            'precio' => 38000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_pantalla_delivery_publico_carga_correctamente(): void
    {
        $response = $this->get('/delivery/pedir');
        $response->assertStatus(200);
        $response->assertSee('RestoMaster');
        $response->assertSee('Delivery');
        $response->assertSee('Salmón Trufado Roll');
    }

    public function test_cliente_puede_crear_pedido_delivery_con_orden_numerada(): void
    {
        $component = Volt::test('delivery.pedido-publico')
            ->call('agregarAlCarrito', $this->producto1->id)
            ->call('agregarAlCarrito', $this->producto2->id)
            ->call('irADatosEntrega')
            ->set('nombreCliente', 'Valentina Restrepo')
            ->set('telefonoCliente', '3009876543')
            ->set('direccionDelivery', 'Cra 35 # 8A-12, Apto 402')
            ->set('referenciaDireccion', 'Frente al parque')
            ->set('metodoPago', 'nequi_bancolombia')
            ->set('notas', 'Sin wasabi por favor')
            ->call('enviarPedidoDelivery');

        $component->assertHasNoErrors();
        $component->assertSet('paso', 'confirmacion_exitosa');

        // Verificar que el pedido existe en la base de datos
        $pedido = Pedido::where('telefono_cliente', '3009876543')->latest()->first();
        $this->assertNotNull($pedido);
        $this->assertEquals('delivery', $pedido->tipo);
        $this->assertEquals('web_delivery', $pedido->canal_origen);
        $this->assertEquals('creado', $pedido->estado);
        $this->assertEquals('pendiente', $pedido->estado_delivery);
        $this->assertStringStartsWith('DLV-', $pedido->codigo);

        // Subtotal = 42000 + 38000 = 80000; Total = 80000 + 8000 (envío) = 88000
        $this->assertEquals(80000.0, (float) $pedido->subtotal);
        $this->assertEquals(8000.0, (float) $pedido->costo_envio);
        $this->assertEquals(88000.0, (float) $pedido->total);

        // Verificar ítems asociados
        $this->assertCount(2, $pedido->items);
        $this->assertDatabaseHas('items_pedido', [
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto1->id,
            'cantidad' => 1,
            'precio_unitario' => 42000.0,
        ]);
        $this->assertDatabaseHas('items_pedido', [
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto2->id,
            'cantidad' => 1,
            'precio_unitario' => 38000.0,
        ]);
    }

    public function test_pedido_delivery_es_visible_para_el_asesor_y_caja(): void
    {
        // Crear rol y usuario cajero
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $cajero = User::create([
            'name' => 'Cajero Turno',
            'email' => 'cajero@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        // Crear pedido delivery desde el flujo público
        Volt::test('delivery.pedido-publico')
            ->call('agregarAlCarrito', $this->producto1->id)
            ->call('irADatosEntrega')
            ->set('nombreCliente', 'Andrés Giraldo')
            ->set('telefonoCliente', '3115554433')
            ->set('direccionDelivery', 'Calle 10 # 40-15')
            ->set('metodoPago', 'efectivo')
            ->set('pagaCon', '50000')
            ->call('enviarPedidoDelivery');

        $pedido = Pedido::where('telefono_cliente', '3115554433')->first();
        $this->assertNotNull($pedido);

        // El cajero/asesor accede al módulo de delivery interno
        $this->actingAs($cajero);
        $deliveryView = Volt::test('delivery.index');
        $deliveryView->assertSee($pedido->codigo);
        $deliveryView->assertSee('Andrés Giraldo');
    }

    public function test_pedido_delivery_con_honeypot_ignora_creacion(): void
    {
        Volt::test('delivery.pedido-publico')
            ->call('agregarAlCarrito', $this->producto1->id)
            ->call('irADatosEntrega')
            ->set('empresa', 'Soy un bot spammer')
            ->set('nombreCliente', 'Bot Spam')
            ->set('telefonoCliente', '3000000000')
            ->set('direccionDelivery', 'Dirección Fake 123')
            ->set('metodoPago', 'efectivo')
            ->call('enviarPedidoDelivery');

        $this->assertDatabaseMissing('pedidos', ['telefono_cliente' => '3000000000']);
    }

    public function test_rutas_publicas_tienen_throttle_middleware(): void
    {
        $response = $this->get('/delivery/pedir');
        $response->assertStatus(200);

        $responseCarta = $this->get('/carta');
        $responseCarta->assertStatus(200);
    }

    public function test_welcome_y_reservas_publicas_cargan_correctamente(): void
    {
        $responseHome = $this->get('/');
        $responseHome->assertStatus(200);
        $responseHome->assertSee('RestoMaster');
        $responseHome->assertSee('Delivery Express');
        $responseHome->assertSee('Reserva de Mesa');

        $responseReservas = $this->get('/reservas/crear');
        $responseReservas->assertStatus(200);
        $responseReservas->assertSee('Reserva tu Mesa en RestoMaster');
        $responseReservas->assertSee('Paso 1: Fecha y Comensales');
    }
}
