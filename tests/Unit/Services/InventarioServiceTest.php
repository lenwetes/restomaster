<?php

namespace Tests\Unit\Services;

use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventarioService $service;

    private Sucursal $sucursal;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventarioService::class);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Norte',
            'codigo' => 'SED-01',
            'direccion' => 'Carrera 15 # 85-30',
            'telefono' => '3109876543',
            'activa' => true,
        ]);

        $this->user = User::factory()->create([
            'sucursal_id' => $this->sucursal->id,
        ]);
    }

    public function test_descontar_insumo_por_receta_con_merma(): void
    {
        $insumo = Insumo::create([
            'codigo' => 'INS-SALMON-01',
            'nombre' => 'Salmón Fresco Premium',
            'categoria' => 'pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 10.0,
            'stock_minimo' => 2.0,
            'costo_unitario' => 45000.0,
            'activo' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Sashimis',
            'slug' => 'sashimis',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Sashimi de Salmón 8 Cortes',
            'slug' => 'sashimi-de-salmon-8-cortes',
            'precio' => 38000.0,
            'area_cocina' => 'fria',
            'activo' => true,
        ]);

        // Receta: 0.200 kg con 10% de merma esperada = 0.220 kg por unidad
        Receta::create([
            'producto_id' => $producto->id,
            'insumo_id' => $insumo->id,
            'cantidad' => 0.200,
            'merma_esperada_pct' => 10.0,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'ORD-INV-SASH-01',
            'sucursal_id' => $this->sucursal->id,
            'usuario_id' => $this->user->id,
            'tipo' => 'mesa',
            'estado' => 'en_preparacion',
            'subtotal' => 76000.0,
            'total' => 76000.0,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => $producto->nombre,
            'precio_unitario' => 38000.0,
            'cantidad' => 2, // 2 x 0.220 = 0.440 kg a descontar
            'subtotal' => 76000.0,
            'inventario_descontado' => false,
        ]);

        $resultado = $this->service->descontarPorItemPedido($item);

        $this->assertTrue($resultado);
        $this->assertTrue((bool) $item->fresh()->inventario_descontado);

        // 10.0 - 0.440 = 9.560
        $this->assertEquals(9.560, (float) $insumo->fresh()->stock_actual);

        // Reintento debe ser idempotente
        $segundoIntento = $this->service->descontarPorItemPedido($item->fresh());
        $this->assertFalse($segundoIntento);
        $this->assertEquals(9.560, (float) $insumo->fresh()->stock_actual);
    }

    public function test_registrar_merma_y_ajuste_fisico(): void
    {
        $insumo = Insumo::create([
            'codigo' => 'INS-ARROZ-01',
            'nombre' => 'Arroz de Sushi Kokuho',
            'categoria' => 'arroz_granos',
            'unidad_medida' => 'kg',
            'stock_actual' => 10.0,
            'stock_minimo' => 5.0,
            'costo_unitario' => 10000.0,
            'activo' => true,
        ]);

        // Registrar merma de 2.0 kg
        $movMerma = $this->service->registrarMerma(
            insumoId: $insumo->id,
            cantidad: 2.0,
            motivo: 'Derrame accidental en cocina',
            userId: $this->user->id
        );

        $this->assertInstanceOf(MovimientoInventario::class, $movMerma);
        $this->assertEquals(8.0, (float) $insumo->fresh()->stock_actual);
        $this->assertSame('merma', $movMerma->tipo);

        // Ajuste físico a 12.0 kg
        $movAjuste = $this->service->registrarAjuste(
            insumoId: $insumo->id,
            nuevoStock: 12.0,
            motivo: 'Conteo físico fin de mes',
            userId: $this->user->id
        );

        $this->assertEquals(12.0, (float) $insumo->fresh()->stock_actual);
        $this->assertSame('ajuste_positivo', $movAjuste->tipo);
    }

    public function test_insumos_en_alerta_detecta_stock_critico(): void
    {
        Insumo::create([
            'codigo' => 'INS-AGUACATE-01',
            'nombre' => 'Aguacate Hass',
            'categoria' => 'vegetales',
            'unidad_medida' => 'kg',
            'stock_actual' => 1.5,
            'stock_minimo' => 5.0,
            'costo_unitario' => 8000.0,
            'activo' => true,
        ]);

        Insumo::create([
            'codigo' => 'INS-SOYA-01',
            'nombre' => 'Salsa de Soya Kikkoman',
            'categoria' => 'salsas_condimentos',
            'unidad_medida' => 'l',
            'stock_actual' => 20.0,
            'stock_minimo' => 4.0,
            'costo_unitario' => 25000.0,
            'activo' => true,
        ]);

        $kpis = $this->service->obtenerKpis();
        $this->assertSame(1, $kpis['criticos_count']);
        $this->assertSame('Aguacate Hass', $kpis['insumos_criticos']->first()->nombre);
    }
}
