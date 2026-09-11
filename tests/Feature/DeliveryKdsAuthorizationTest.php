<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeliveryKdsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $mesero;

    private User $cajero;

    private User $repartidor1;

    private User $repartidor2;

    private User $cocinero;

    private User $barman;

    private User $admin;

    private Producto $productoSushi;

    private Producto $productoBarra;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $roleRepartidor = Role::create(['nombre' => 'Repartidor', 'slug' => 'repartidor']);
        $roleCocina = Role::create(['nombre' => 'Cocina', 'slug' => 'cocina']);
        $roleBarra = Role::create(['nombre' => 'Barra', 'slug' => 'barra']);

        $sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero User',
            'email' => 'mesero@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->cajero = User::create([
            'name' => 'Cajero User',
            'email' => 'cajero@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleCajero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->repartidor1 = User::create([
            'name' => 'Moto 1',
            'email' => 'moto1@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleRepartidor->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->repartidor2 = User::create([
            'name' => 'Moto 2',
            'email' => 'moto2@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleRepartidor->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->cocinero = User::create([
            'name' => 'Chef Sushi',
            'email' => 'cocina@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleCocina->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->barman = User::create([
            'name' => 'Barman',
            'email' => 'barra@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleBarra->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $catSushi = Categoria::create(['nombre' => 'Rolls', 'slug' => 'rolls', 'activo' => true]);
        $this->productoSushi = Producto::create([
            'nombre' => 'Rainbow Roll',
            'slug' => 'rainbow-roll',
            'categoria_id' => $catSushi->id,
            'precio' => 42000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $catBebidas = Categoria::create(['nombre' => 'Bebidas', 'slug' => 'bebidas', 'activo' => true]);
        $this->productoBarra = Producto::create([
            'nombre' => 'Limonada de Coco',
            'slug' => 'limonada-coco',
            'categoria_id' => $catBebidas->id,
            'precio' => 15000,
            'area_cocina' => 'barra',
            'activo' => true,
        ]);
    }

    public function test_mesero_no_puede_asignar_repartidor_en_delivery(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'DLV-TEST-01',
            'tipo' => 'delivery',
            'estado' => 'listo',
            'subtotal' => 42000,
            'total' => 50000,
        ]);

        Volt::actingAs($this->mesero)
            ->test('delivery.index')
            ->set('pedidoSeleccionadoId', $pedido->id)
            ->set('repartidorIdSeleccionado', $this->repartidor1->id)
            ->call('asignarRepartidor')
            ->assertForbidden();
    }

    public function test_cajero_puede_asignar_repartidor_en_delivery(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'DLV-TEST-02',
            'tipo' => 'delivery',
            'estado' => 'listo',
            'subtotal' => 42000,
            'total' => 50000,
        ]);

        Volt::actingAs($this->cajero)
            ->test('delivery.index')
            ->set('pedidoSeleccionadoId', $pedido->id)
            ->set('repartidorIdSeleccionado', $this->repartidor1->id)
            ->call('asignarRepartidor')
            ->assertHasNoErrors();

        $this->assertEquals($this->repartidor1->id, $pedido->fresh()->repartidor_id);
    }

    public function test_repartidor_no_puede_liquidar_a_otro_repartidor(): void
    {
        Volt::actingAs($this->repartidor1)
            ->test('delivery.index')
            ->call('liquidarRepartidor', $this->repartidor2->id)
            ->assertForbidden();
    }

    public function test_mesero_no_puede_cocinar_ni_marcar_listo_en_kds(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'PED-KDS-01',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'subtotal' => 42000,
            'total' => 42000,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->productoSushi->id,
            'nombre_producto' => 'Rainbow Roll',
            'cantidad' => 1,
            'precio_unitario' => 42000,
            'subtotal' => 42000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        Volt::actingAs($this->mesero)
            ->test('cocina.kds')
            ->call('marcarListo', $item->id)
            ->assertForbidden();
    }

    public function test_barra_no_puede_marcar_listo_plato_de_sushi(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'PED-KDS-02',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'subtotal' => 42000,
            'total' => 42000,
        ]);

        $itemSushi = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->productoSushi->id,
            'nombre_producto' => 'Rainbow Roll',
            'cantidad' => 1,
            'precio_unitario' => 42000,
            'subtotal' => 42000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        Volt::actingAs($this->barman)
            ->test('cocina.kds')
            ->call('marcarListo', $itemSushi->id)
            ->assertForbidden();
    }

    public function test_barra_puede_marcar_listo_bebida_de_su_estacion(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'PED-KDS-03',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'subtotal' => 15000,
            'total' => 15000,
        ]);

        $itemBarra = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->productoBarra->id,
            'nombre_producto' => 'Limonada de Coco',
            'cantidad' => 1,
            'precio_unitario' => 15000,
            'subtotal' => 15000,
            'area_cocina' => 'barra',
            'estado_cocina' => 'pendiente',
        ]);

        Volt::actingAs($this->barman)
            ->test('cocina.kds')
            ->call('marcarListo', $itemBarra->id)
            ->assertHasNoErrors();

        $this->assertEquals('listo', $itemBarra->fresh()->estado_cocina);
    }

    public function test_cocina_puede_marcar_listo_plato_de_sushi(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'PED-KDS-04',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'subtotal' => 42000,
            'total' => 42000,
        ]);

        $itemSushi = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->productoSushi->id,
            'nombre_producto' => 'Rainbow Roll',
            'cantidad' => 1,
            'precio_unitario' => 42000,
            'subtotal' => 42000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        Volt::actingAs($this->cocinero)
            ->test('cocina.kds')
            ->call('marcarListo', $itemSushi->id)
            ->assertHasNoErrors();

        $this->assertEquals('listo', $itemSushi->fresh()->estado_cocina);
    }

    public function test_cocina_puede_tomar_y_marcar_listo_bebida_de_barra(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'PED-KDS-05',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'subtotal' => 15000,
            'total' => 15000,
        ]);

        $itemBarra = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->productoBarra->id,
            'nombre_producto' => 'Té Verde Matcha',
            'cantidad' => 1,
            'precio_unitario' => 15000,
            'subtotal' => 15000,
            'area_cocina' => 'barra',
            'estado_cocina' => 'pendiente',
        ]);

        Volt::actingAs($this->cocinero)
            ->test('cocina.kds')
            ->call('tomarItem', $itemBarra->id)
            ->assertHasNoErrors()
            ->call('marcarListo', $itemBarra->id)
            ->assertHasNoErrors();

        $this->assertEquals('listo', $itemBarra->fresh()->estado_cocina);
    }

    public function test_cajero_puede_crear_nuevo_pedido_manual_en_delivery(): void
    {
        Volt::actingAs($this->cajero)
            ->test('delivery.index')
            ->set('nuevoPedido.nombre_cliente', 'Carlos Manuel')
            ->set('nuevoPedido.telefono_cliente', '3001234567')
            ->set('nuevoPedido.direccion_delivery', 'Calle 10 # 43E-20')
            ->set('nuevoPedido.producto_id', $this->productoSushi->id)
            ->set('nuevoPedido.cantidad', 2)
            ->set('nuevoPedido.costo_envio', 8000)
            ->call('guardarNuevoPedido')
            ->assertHasNoErrors();

        $pedido = Pedido::where('tipo', 'delivery')->latest()->first();
        $this->assertNotNull($pedido);
        $this->assertEquals('Carlos Manuel', $pedido->nombre_cliente);
        $this->assertEquals('listo', $pedido->estado);
        $this->assertEquals(84000, $pedido->subtotal);
        $this->assertEquals(92000, $pedido->total); // 84000 + 8000
        $this->assertCount(1, $pedido->items);
        $this->assertEquals(2, $pedido->items->first()->cantidad);
    }
}
