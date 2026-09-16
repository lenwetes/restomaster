<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\CuentaPorPagar;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Sucursal;
use App\Services\CuentasPorPagarService;
use App\Services\FidelizacionService;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaLote4ConcurrenciaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);
    }

    public function test_r10_descuento_inventario_es_estrictamente_idempotente_y_atomico(): void
    {
        $insumo = Insumo::create([
            'nombre' => 'Carne Angus',
            'codigo' => 'INS-ANGUS',
            'categoria' => 'proteinas',
            'unidad_medida' => 'kg',
            'stock_actual' => 10.000,
            'stock_minimo' => 2.000,
            'costo_unitario' => 50000,
            'activo' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Parrilla',
            'slug' => 'parrilla',
            'icono' => '🥩',
            'orden' => 1,
            'activo' => true,
        ]);

        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Bife Angus 350g',
            'slug' => 'bife-angus-350g',
            'precio' => 75000,
            'area_cocina' => 'cocina',
            'activo' => true,
        ]);

        Receta::create([
            'producto_id' => $producto->id,
            'insumo_id' => $insumo->id,
            'cantidad' => 0.350,
            'merma_esperada_pct' => 0,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'ORD-R10-TEST',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'subtotal' => 75000,
            'total' => 75000,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => $producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 75000,
            'subtotal' => 75000,
            'area_cocina' => 'cocina',
            'estado_cocina' => 'en_preparacion',
            'inventario_descontado' => false,
        ]);

        $inventarioService = app(InventarioService::class);

        // Invocación 1
        $res1 = $inventarioService->descontarPorItemPedido($item);
        $this->assertTrue($res1);

        // Invocación 2 inmediata (simulación de proceso concurrente)
        $item->refresh();
        $res2 = $inventarioService->descontarPorItemPedido($item);
        $this->assertFalse($res2);

        // El stock debe haberse descontado exactamente 1 vez (10 - 0.350 = 9.650)
        $insumo->refresh();
        $this->assertEquals(9.650, (float) $insumo->stock_actual);

        // Solo 1 movimiento en el kardex
        $kardexCount = MovimientoInventario::where('insumo_id', $insumo->id)
            ->where('pedido_id', $pedido->id)
            ->count();
        $this->assertEquals(1, $kardexCount);
    }

    public function test_r11_abono_cxp_impide_sobrepago(): void
    {
        $cuenta = CuentaPorPagar::create([
            'proveedor_nombre' => 'Distribuidora Carnes SAS',
            'concepto' => 'Compra cortes Angus',
            'monto_total' => 100000,
            'saldo_pendiente' => 100000,
            'estado' => 'pendiente',
            'fecha_emision' => now()->toDateString(),
        ]);

        $cxpService = app(CuentasPorPagarService::class);

        // Abono mayor al saldo pendiente debe lanzar excepción
        $this->expectException(\InvalidArgumentException::class);
        $cxpService->registrarPago($cuenta, 150000, null, 'transferencia');
    }

    public function test_r12_canje_puntos_impide_saldo_insuficiente(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Cliente Puntos',
            'telefono' => '3001234567',
            'email' => 'cliente.puntos@test.com',
            'puntos_fidelidad' => 50,
            'tier' => 'bronce',
            'activo' => true,
        ]);

        $fidelizacionService = app(FidelizacionService::class);

        // Intentar canjear 100 puntos cuando solo tiene 50 debe fallar
        $this->expectException(\InvalidArgumentException::class);
        $fidelizacionService->canjearPuntos($cliente, 100);
    }
}
