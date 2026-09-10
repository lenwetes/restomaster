<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SeguridadDineroAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $pedidoService;

    private Sucursal $sucursal;

    private User $mesero;

    private Mesa $mesa;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pedidoService = app(PedidoService::class);

        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero Prueba',
            'email' => 'mesero@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M01',
            'capacidad' => 4,
            'estado' => 'libre',
        ]);

        $cat = Categoria::create(['nombre' => 'Rolls', 'slug' => 'rolls', 'activo' => true]);
        $this->producto = Producto::create([
            'nombre' => 'Salmón Trufado Roll',
            'slug' => 'salmon-trufado-roll',
            'categoria_id' => $cat->id,
            'precio' => 45000,
            'activo' => true,
        ]);
    }

    public function test_c1_precio_unitario_siempre_se_obtiene_de_la_base_de_datos(): void
    {
        // El cliente intenta enviar un payload malicioso manipulando el precio a $1.00 COP
        $itemsPayload = [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'precio_unitario' => 1.00, // MANIPULADO
            ],
        ];

        $pedido = $this->pedidoService->crearPedido([
            'tipo' => 'mesa',
            'mesa_id' => $this->mesa->id,
        ], $itemsPayload, $this->mesero);

        // El servidor debió ignorar el precio enviado y tomar $45.000 de la base de datos
        // 2 x $45.000 = $90.000
        $this->assertEquals(90000.0, (float) $pedido->subtotal);
        $this->assertEquals(90000.0, (float) $pedido->total);

        $item = $pedido->items->first();
        $this->assertEquals(45000.0, (float) $item->precio_unitario);
        $this->assertEquals(90000.0, (float) $item->subtotal);
    }

    public function test_c2_y_m2_formula_total_unificada_con_envio_descuentos_y_puntos(): void
    {
        $itemsPayload = [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
            ],
        ];

        // Subtotal = 45.000, Envío = 8.000, Descuento = 5.000, Puntos = 3.000
        // Total = 45000 + 8000 - 5000 - 3000 = 45000
        $pedido = $this->pedidoService->crearPedido([
            'tipo' => 'delivery',
            'costo_envio' => 8000,
            'descuento' => 5000,
            'descuento_puntos' => 3000,
        ], $itemsPayload, $this->mesero);

        $this->assertEquals(45000.0, (float) $pedido->subtotal);
        $this->assertEquals(8000.0, (float) $pedido->costo_envio);
        $this->assertEquals(5000.0, (float) $pedido->descuento);
        $this->assertEquals(3000.0, (float) $pedido->descuento_puntos);
        $this->assertEquals(45000.0, (float) $pedido->total);
    }

    public function test_h5_idempotencia_en_cobro_impide_doble_pago(): void
    {
        $itemsPayload = [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
            ],
        ];

        $pedido = $this->pedidoService->crearPedido([
            'tipo' => 'mesa',
            'mesa_id' => $this->mesa->id,
        ], $itemsPayload, $this->mesero);

        // Primer cobro exitoso
        $cobrado = $this->pedidoService->cobrarPedido($pedido, 'efectivo', 50000);
        $this->assertEquals('pagado', $cobrado->estado);

        // Segundo intento de cobro (ej. doble click o race condition) debe arrojar excepción 400
        $this->expectException(HttpException::class);
        $this->pedidoService->cobrarPedido($cobrado, 'efectivo', 50000);
    }
}
