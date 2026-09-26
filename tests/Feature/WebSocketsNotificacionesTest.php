<?php

namespace Tests\Feature;

use App\Events\ComandaEnviada;
use App\Events\ItemListoParaServir;
use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebSocketsNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected Role $adminRole;

    protected Role $meseroRole;

    protected Role $cocinaRole;

    protected User $admin;

    protected User $mesero;

    protected User $otroMesero;

    protected Categoria $categoria;

    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Principal',
            'codigo' => 'SEDE-01',
            'direccion' => 'Calle 10 # 40-20',
            'activo' => true,
        ]);

        $this->adminRole = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $this->meseroRole = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        $this->cocinaRole = Role::create(['nombre' => 'Cocinero', 'slug' => 'cocina']);

        $this->admin = User::create([
            'role_id' => $this->adminRole->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'role_id' => $this->meseroRole->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Mesero Juan',
            'email' => 'juan@test.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $this->otroMesero = User::create([
            'role_id' => $this->meseroRole->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Mesero Carlos',
            'email' => 'carlos@test.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $this->categoria = Categoria::create([
            'nombre' => 'Rolls',
            'slug' => 'rolls',
            'activo' => true,
        ]);

        $this->producto = Producto::create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'Salmon Roll',
            'slug' => 'salmon-roll',
            'descripcion' => 'Delicioso roll de salmón fresco',
            'precio' => 25000,
            'costo' => 8000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_comanda_enviada_dispatches_broadcast_event(): void
    {
        Event::fake([ComandaEnviada::class]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M1',
            'capacidad' => 4,
            'estado' => 'libre',
            'zona' => 'salon',
        ]);

        $pedido = Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'usuario_id' => $this->mesero->id,
            'codigo' => 'ORD-TEST-01',
            'estado' => 'creado',
            'tipo' => 'mesa',
            'subtotal' => 50000,
            'impuestos' => 0,
            'descuento' => 0,
            'total' => 50000,
        ]);

        app(PedidoService::class)->enviarACocina($pedido);

        Event::assertDispatched(ComandaEnviada::class, function ($event) use ($pedido) {
            return $event->pedidoId === $pedido->id
                && $event->broadcastOn()[0]->name === "private-cocina.{$this->sucursal->id}";
        });
    }

    public function test_item_listo_persists_notificacion_and_dispatches_event_to_mesero(): void
    {
        Event::fake([ItemListoParaServir::class]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M2',
            'capacidad' => 2,
            'estado' => 'ocupada',
            'zona' => 'barra',
        ]);

        $pedido = Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'usuario_id' => $this->mesero->id,
            'mesero_id' => $this->mesero->id,
            'codigo' => 'ORD-TEST-02',
            'estado' => 'en_cocina',
            'tipo' => 'mesa',
            'subtotal' => 25000,
            'impuestos' => 0,
            'descuento' => 0,
            'total' => 25000,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => 'Salmon Roll',
            'precio_unitario' => 25000,
            'cantidad' => 2,
            'subtotal' => 50000,
            'estado_cocina' => 'en_preparacion',
        ]);

        app(PedidoService::class)->marcarItemListo($item);

        // Verifica que se guardó en notificaciones_usuario
        $this->assertDatabaseHas('notificaciones_usuario', [
            'user_id' => $this->mesero->id,
            'tipo' => 'plato_listo',
            'leida' => false,
        ]);

        Event::assertDispatched(ItemListoParaServir::class, function ($event) {
            return $event->meseroId === $this->mesero->id
                && $event->broadcastOn()[0]->name === "private-mesero.{$this->mesero->id}";
        });
    }

    public function test_canales_autorizacion_broadcast(): void
    {
        $channels = Broadcast::getChannels();

        $cocinaCallback = $channels['cocina.{sucursalId}'];
        $meseroCallback = $channels['mesero.{userId}'];

        // Canal de cocina: mesero de la misma sucursal
        $this->assertTrue($cocinaCallback($this->mesero, $this->sucursal->id));

        $otraSucursal = Sucursal::create([
            'nombre' => 'Sede Norte',
            'codigo' => 'SEDE-02',
            'direccion' => 'Calle 100 # 15-20',
            'activo' => true,
        ]);

        $usuarioOtraSucursal = User::create([
            'role_id' => $this->meseroRole->id,
            'sucursal_id' => $otraSucursal->id,
            'name' => 'Mesero Otra Sede',
            'email' => 'otra@test.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);
        $this->assertFalse($cocinaCallback($usuarioOtraSucursal, $this->sucursal->id));

        // Canal de cocina: admin siempre tiene acceso
        $this->assertTrue($cocinaCallback($this->admin, $this->sucursal->id));

        // Canal privado mesero: el propio mesero tiene acceso
        $this->assertTrue($meseroCallback($this->mesero, $this->mesero->id));

        // Canal privado mesero: otro mesero NO tiene acceso
        $this->assertFalse($meseroCallback($this->otroMesero, $this->mesero->id));

        // Canal privado mesero: admin sí tiene acceso de supervisión
        $this->assertTrue($meseroCallback($this->admin, $this->mesero->id));
    }
}
