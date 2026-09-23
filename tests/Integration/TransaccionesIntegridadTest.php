<?php

namespace Tests\Integration;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use App\Services\InventarioService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransaccionesIntegridadTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $cajero;

    private Caja $caja;

    private Producto $producto;

    private Insumo $insumoA;

    private Insumo $insumoB;

    protected function setUp(): void
    {
        parent::setUp();

        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'activa' => true,
        ]);

        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->caja = app(CajaService::class)->crearCaja([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Transaccional',
            'codigo' => 'CAJ-TRX-01',
            'activa' => true,
        ]);

        $cat = Categoria::create(['nombre' => 'Makis', 'slug' => 'makis-trx', 'activo' => true]);

        $this->producto = Producto::create([
            'categoria_id' => $cat->id,
            'nombre' => 'Ebi Tempura Roll',
            'slug' => 'ebi-tempura-roll',
            'precio' => 35000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        $this->insumoA = Insumo::create([
            'codigo' => 'INS-LANGOSTINO-01',
            'nombre' => 'Langostino Tigre',
            'categoria' => 'pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 5.0,
            'stock_minimo' => 1.0,
            'costo_unitario' => 60000,
            'activo' => true,
        ]);

        $this->insumoB = Insumo::create([
            'codigo' => 'INS-PANKO-01',
            'nombre' => 'Panko Japonés',
            'categoria' => 'arroz_granos',
            'unidad_medida' => 'kg',
            'stock_actual' => 8.0,
            'stock_minimo' => 2.0,
            'costo_unitario' => 15000,
            'activo' => true,
        ]);

        Receta::create([
            'producto_id' => $this->producto->id,
            'insumo_id' => $this->insumoA->id,
            'cantidad' => 0.150,
            'merma_esperada_pct' => 0.0,
        ]);

        Receta::create([
            'producto_id' => $this->producto->id,
            'insumo_id' => $this->insumoB->id,
            'cantidad' => 0.050,
            'merma_esperada_pct' => 0.0,
        ]);
    }

    /**
     * Test de Atomicidad: si ocurre un fallo durante el cobro, la base de datos revierte completamente.
     */
    public function test_rollback_atomico_si_falla_proceso_de_cobro(): void
    {
        $turno = app(CajaService::class)->abrirTurno(
            caja: $this->caja,
            cajero: $this->cajero,
            fondoInicial: 100000.0
        );

        $pedido = Pedido::create([
            'codigo' => 'ORD-ROLLBACK-01',
            'sucursal_id' => $this->sucursal->id,
            'tipo' => 'mesa',
            'estado' => 'listo',
            'subtotal' => 35000,
            'total' => 35000,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 35000,
            'subtotal' => 35000,
            'estado_cocina' => 'listo',
        ]);

        // Simular intento de cobro con excepción deliberada a mitad de transacción
        $excepcionOcurrida = false;
        try {
            DB::transaction(function () use ($pedido, $turno) {
                // Paso 1: vincular cobro a caja
                app(CajaService::class)->vincularCobroPedido($turno, $pedido);

                // Paso 2: marcar pagado
                $pedido->update(['estado' => 'pagado', 'pagado_en' => now()]);

                // Paso 3: simular fallo crítico de red o servicio externo
                throw new Exception('Fallo simulado de hardware/red en pasarela');
            });
        } catch (Exception $e) {
            $excepcionOcurrida = true;
        }

        $this->assertTrue($excepcionOcurrida);

        // Verificar que el estado del pedido NO cambió a pagado
        $pedido->refresh();
        $this->assertSame('listo', $pedido->estado);
        $this->assertNull($pedido->pagado_en);

        // Verificar que ningún asiento contable huérfano quedó registrado
        $this->assertDatabaseMissing('asientos_contables', [
            'referencia_id' => $pedido->id,
            'referencia_tipo' => 'pedido',
        ]);
    }

    /**
     * Test de consistencia de inventario cruzado: el descuento reduce stock y crea movimientos de kardex.
     */
    public function test_descuento_de_receta_mantiene_consistencia_transaccional(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-INV-CONS-01',
            'sucursal_id' => $this->sucursal->id,
            'tipo' => 'mesa',
            'estado' => 'en_preparacion',
            'subtotal' => 70000,
            'total' => 70000,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre,
            'cantidad' => 2, // 2 x 0.150 = 0.300 kg langostino, 2 x 0.050 = 0.100 kg panko
            'precio_unitario' => 35000,
            'subtotal' => 70000,
            'inventario_descontado' => false,
        ]);

        $servicioInventario = app(InventarioService::class);
        $resultado = $servicioInventario->descontarPorItemPedido($item);

        $this->assertTrue($resultado);

        $this->insumoA->refresh();
        $this->insumoB->refresh();

        $this->assertEquals(4.700, (float) $this->insumoA->stock_actual);
        $this->assertEquals(7.900, (float) $this->insumoB->stock_actual);

        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $this->insumoA->id,
            'tipo' => 'consumo_venta',
            'cantidad' => 0.300,
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $this->insumoB->id,
            'tipo' => 'consumo_venta',
            'cantidad' => 0.100,
        ]);
    }
}
