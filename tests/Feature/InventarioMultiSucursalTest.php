<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\InsumoSucursal;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Sucursal;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioMultiSucursalTest extends TestCase
{
    use RefreshDatabase;

    public function test_descuento_de_inventario_aislado_entre_sucursales(): void
    {
        $sucursalNorte = Sucursal::create([
            'nombre' => 'Sucursal Norte',
            'codigo' => 'SUC-NORTE',
            'activa' => true,
        ]);

        $sucursalSur = Sucursal::create([
            'nombre' => 'Sucursal Sur',
            'codigo' => 'SUC-SUR',
            'activa' => true,
        ]);

        $insumo = Insumo::create([
            'codigo' => 'INS-SALMON-01',
            'nombre' => 'Salmón Fresco Premium',
            'categoria' => 'pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 20.0,
            'stock_minimo' => 2.0,
            'costo_unitario' => 50000,
            'activo' => true,
        ]);

        // Asignar 10 kg en Sucursal Norte y 10 kg en Sucursal Sur
        $invNorte = InsumoSucursal::create([
            'sucursal_id' => $sucursalNorte->id,
            'insumo_id' => $insumo->id,
            'stock_actual' => 10.0,
            'stock_minimo' => 2.0,
            'costo_unitario' => 50000,
            'activo' => true,
        ]);

        $invSur = InsumoSucursal::create([
            'sucursal_id' => $sucursalSur->id,
            'insumo_id' => $insumo->id,
            'stock_actual' => 10.0,
            'stock_minimo' => 2.0,
            'costo_unitario' => 50000,
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Sashimi', 'slug' => 'sashimi', 'activo' => true]);

        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Sashimi Salmón 5 cortes',
            'slug' => 'sashimi-salmon-5',
            'precio' => 32000,
            'activo' => true,
        ]);

        Receta::create([
            'producto_id' => $producto->id,
            'insumo_id' => $insumo->id,
            'cantidad' => 0.200, // 200g por plato
            'merma_esperada_pct' => 0.0,
        ]);

        // Crear y procesar comanda en Sucursal Norte por 2 platos (400g)
        $pedidoNorte = Pedido::create([
            'codigo' => 'ORD-NORTE-01',
            'sucursal_id' => $sucursalNorte->id,
            'tipo' => 'mostrador',
            'estado' => 'en_preparacion',
            'subtotal' => 64000,
            'total' => 64000,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedidoNorte->id,
            'producto_id' => $producto->id,
            'nombre_producto' => $producto->nombre,
            'cantidad' => 2,
            'precio_unitario' => 32000,
            'subtotal' => 64000,
            'inventario_descontado' => false,
        ]);

        $inventarioService = app(InventarioService::class);
        $resultado = $inventarioService->descontarPorItemPedido($item);

        $this->assertTrue($resultado);

        // Verificar aislamiento de inventario
        $invNorte->refresh();
        $invSur->refresh();

        // Sucursal Norte debe tener 10.0 - 0.400 = 9.600 kg
        $this->assertEquals(9.600, (float) $invNorte->stock_actual);

        // Sucursal Sur DEBE PERMANECER INTACTA con 10.000 kg
        $this->assertEquals(10.000, (float) $invSur->stock_actual);

        // Verificar que el movimiento de Kardex registró la sucursal Norte
        $this->assertDatabaseHas('movimientos_inventario', [
            'sucursal_id' => $sucursalNorte->id,
            'insumo_id' => $insumo->id,
            'tipo' => 'consumo_venta',
            'cantidad' => 0.400,
        ]);
    }
}
