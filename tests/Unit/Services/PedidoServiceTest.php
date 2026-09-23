<?php

namespace Tests\Unit\Services;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoServiceTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $service;

    private Sucursal $sucursal;

    private User $mesero;

    private User $cajero;

    private Mesa $mesa;

    private Categoria $categoria;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PedidoService::class);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Central',
            'codigo' => 'SUC-01',
            'direccion' => 'Calle 100 # 19-40',
            'telefono' => '3005556677',
            'activa' => true,
        ]);

        $roleMesero = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero']);
        $roleCajero = Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero']);

        $this->mesero = User::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'role_id' => $roleMesero->id,
        ]);

        $this->cajero = User::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'role_id' => $roleCajero->id,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-05',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
        ]);

        $this->categoria = Categoria::create([
            'nombre' => 'Sushi Rolls',
            'slug' => 'sushi-rolls-pedidos',
            'activo' => true,
        ]);

        $this->producto = Producto::create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'Roll Philadelphia 10 Bocados',
            'slug' => 'roll-philadelphia-10-bocados',
            'precio' => 32000.0,
            'area_cocina' => 'fria',
            'activo' => true,
        ]);
    }

    public function test_crear_pedido_calcula_subtotal_e_items(): void
    {
        $pedido = $this->service->crearPedido(
            datos: [
                'sucursal_id' => $this->sucursal->id,
                'mesa_id' => $this->mesa->id,
                'tipo' => 'mesa',
                'estado' => 'creado',
            ],
            items: [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 2,
                    'notas' => 'Sin sésamo',
                ],
            ],
            usuario: $this->mesero
        );

        $this->assertInstanceOf(Pedido::class, $pedido);
        $this->assertEquals(64000.0, (float) $pedido->subtotal);
        $this->assertEquals(64000.0, (float) $pedido->total);
        $this->assertCount(1, $pedido->items);
        $this->assertSame('Sin sésamo', $pedido->items->first()->notas);
    }

    public function test_agregar_item_a_pedido_recalcula_totales(): void
    {
        $pedido = $this->service->crearPedido(
            datos: [
                'sucursal_id' => $this->sucursal->id,
                'mesa_id' => $this->mesa->id,
                'tipo' => 'mesa',
                'estado' => 'creado',
            ],
            items: [
                ['producto_id' => $this->producto->id, 'cantidad' => 1],
            ],
            usuario: $this->mesero
        );

        $productoBebida = Producto::create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'Cerveza Asahi',
            'slug' => 'cerveza-asahi',
            'precio' => 15000.0,
            'area_cocina' => 'barra',
            'activo' => true,
        ]);

        $itemAdicional = $this->service->agregarItem($pedido, $productoBebida, 2);

        $this->assertInstanceOf(ItemPedido::class, $itemAdicional);
        $this->assertEquals(30000.0, (float) $itemAdicional->subtotal);

        $pedidoActualizado = $pedido->fresh();
        // 32.000 + 30.000 = 62.000
        $this->assertEquals(62000.0, (float) $pedidoActualizado->subtotal);
        $this->assertEquals(62000.0, (float) $pedidoActualizado->total);
    }

    public function test_cobrar_pedido_con_turno_de_caja_activo(): void
    {
        $caja = app(CajaService::class)->crearCaja([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja POS 1',
            'codigo' => 'POS-01',
            'activa' => true,
        ]);

        app(CajaService::class)->abrirTurno(
            caja: $caja,
            cajero: $this->cajero,
            fondoInicial: 100000.0
        );

        $pedido = $this->service->crearPedido(
            datos: [
                'sucursal_id' => $this->sucursal->id,
                'mesa_id' => $this->mesa->id,
                'tipo' => 'mesa',
                'estado' => 'listo',
            ],
            items: [
                ['producto_id' => $this->producto->id, 'cantidad' => 1],
            ],
            usuario: $this->mesero
        );

        // Asegurar que los items no queden en pendiente para poder cobrar
        $pedido->items()->update(['estado_cocina' => 'listo']);

        $pedidoPagado = $this->service->cobrarPedido(
            pedido: $pedido,
            metodoPago: 'efectivo',
            montoPagado: 50000.0,
            propina: 3200.0
        );

        $this->assertSame('pagado', $pedidoPagado->fresh()->estado);
        $this->assertEquals(3200.0, (float) $pedidoPagado->fresh()->propina);
        $this->assertNotNull($pedidoPagado->fresh()->pagado_en);
    }
}
