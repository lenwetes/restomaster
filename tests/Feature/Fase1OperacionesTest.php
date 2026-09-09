<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase1OperacionesTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;
    protected Sucursal $sucursal;
    protected Mesa $mesa;
    protected Categoria $categoria;
    protected Producto $producto1;
    protected Producto $producto2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Test',
            'direccion' => 'Calle 123',
            'telefono' => '12345678',
            'nit_ruc' => 'TEST-123',
            'activo' => true,
        ]);

        $rolAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);

        $this->usuario = User::factory()->create(['role_id' => $rolAdmin->id]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-01',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $this->categoria = Categoria::create([
            'nombre' => 'Rolls Clásicos',
            'slug' => 'rolls-clasicos',
            'icono' => '🍣',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->producto1 = Producto::create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'California Roll',
            'slug' => 'california-roll',
            'precio' => 10.00,
            'costo' => 3.00,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $this->producto2 = Producto::create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'Gyozas',
            'slug' => 'gyozas',
            'precio' => 6.00,
            'costo' => 2.00,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);
    }

    public function test_pantalla_mesas_puede_ser_renderizada(): void
    {
        $response = $this->actingAs($this->usuario)->get(route('mesas'));
        $response->assertOk();
        $response->assertSeeVolt('mesas.index');
        $response->assertSee('M-01');
    }

    public function test_pantalla_pos_puede_ser_renderizada(): void
    {
        $response = $this->actingAs($this->usuario)->get(route('pos'));
        $response->assertOk();
        $response->assertSeeVolt('pos.terminal');
        $response->assertSee('California Roll');
    }

    public function test_pantalla_cocina_kds_puede_ser_renderizada(): void
    {
        $response = $this->actingAs($this->usuario)->get(route('cocina'));
        $response->assertOk();
        $response->assertSeeVolt('cocina.kds');
    }

    public function test_flujo_completo_operativo_mesa_orden_cocina_cobro(): void
    {
        $pedidoService = app(PedidoService::class);

        // 1. Verificar mesa en estado libre
        $this->assertEquals(MesaEstado::LIBRE->value, $this->mesa->estado);

        // 2. Crear pedido asociado a la mesa con 2 items
        $items = [
            [
                'producto_id' => $this->producto1->id,
                'cantidad' => 2,
                'precio_unitario' => 10.00,
                'notas' => 'Sin sésamo',
            ],
            [
                'producto_id' => $this->producto2->id,
                'cantidad' => 1,
                'precio_unitario' => 6.00,
                'notas' => 'Bien doradas',
            ],
        ];

        $pedido = $pedidoService->crearPedido([
            'tipo' => 'mesa',
            'mesa_id' => $this->mesa->id,
        ], $items, $this->usuario);

        $this->assertNotNull($pedido->codigo);
        $this->assertEquals(26.00, (float)$pedido->total);

        // La mesa debe haber pasado automáticamente a 'ocupada'
        $this->mesa->refresh();
        $this->assertEquals(MesaEstado::OCUPADA->value, $this->mesa->estado);

        // 3. Enviar a cocina
        $pedido = $pedidoService->enviarACocina($pedido);
        $this->assertEquals('en_cocina', $pedido->estado);
        $this->assertEquals(2, $pedido->items()->where('estado_cocina', 'en_preparacion')->count());

        // 4. Cocina marca los items como listos
        foreach ($pedido->items as $item) {
            $pedidoService->marcarItemListo($item);
        }

        $pedido->refresh();
        $this->assertEquals('listo', $pedido->estado);

        // 5. Mesero entrega los items a la mesa
        foreach ($pedido->items as $item) {
            $pedidoService->marcarItemEntregado($item);
        }

        $pedido->refresh();
        $this->assertEquals('entregado', $pedido->estado);

        // 6. Cobro del pedido en caja con efectivo ($30 para una cuenta de $26)
        $pedidoPagado = $pedidoService->cobrarPedido($pedido, 'efectivo', 30.00);

        $this->assertEquals('pagado', $pedidoPagado->estado);
        $this->assertEquals(4.00, (float)$pedidoPagado->cambio);
        $this->assertNotNull($pedidoPagado->pagado_en);

        // La mesa debe quedar en 'por_limpiar'
        $this->mesa->refresh();
        $this->assertEquals(MesaEstado::POR_LIMPIAR->value, $this->mesa->estado);

        // 7. Personal de limpieza limpia y libera la mesa
        $this->mesa->update(['estado' => MesaEstado::LIBRE->value]);
        $this->assertEquals(MesaEstado::LIBRE->value, $this->mesa->estado);
    }
}
