<?php

namespace Tests\Feature\Integrity;

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
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseAcidFinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Caja $caja;

    private User $cajero;

    private Insumo $insumoCarne;

    private Producto $platoSteak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal Salón',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        $roleCajero = Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero']);

        $this->cajero = User::create([
            'name' => 'Cajero Audit',
            'email' => 'cajero.audit@restomaster.com',
            'password' => bcrypt('password'),
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
            'is_active' => true,
        ]);

        $this->insumoCarne = Insumo::create([
            'nombre' => 'Bife de Chorizo Crudo',
            'codigo' => 'INS-BIFE-01',
            'categoria' => 'carnes',
            'unidad_medida' => 'kg',
            'costo_unitario' => 45000,
            'stock_actual' => 10.0,
            'stock_minimo' => 2.0,
            'activo' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Parrilla',
            'slug' => 'parrilla',
            'activo' => true,
        ]);

        $this->platoSteak = Producto::create([
            'nombre' => 'Bife de Chorizo Asado',
            'slug' => 'bife-de-chorizo-asado',
            'categoria_id' => $categoria->id,
            'precio' => 68000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        Receta::create([
            'producto_id' => $this->platoSteak->id,
            'insumo_id' => $this->insumoCarne->id,
            'cantidad' => 0.35, // 350g por plato
            'merma_esperada_pct' => 5.0,
        ]);
    }

    public function test_transaccionalidad_atomica_revierte_pedido_si_falla_un_item(): void
    {
        $totalPedidosAntes = Pedido::count();

        try {
            DB::transaction(function () {
                $pedido = Pedido::create([
                    'codigo' => 'ORD-ATOMIC-FAIL',
                    'tipo' => 'mesa',
                    'estado' => 'creado',
                    'subtotal' => 68000,
                    'total' => 68000,
                    'sucursal_id' => $this->sucursal->id,
                ]);

                ItemPedido::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $this->platoSteak->id,
                    'nombre_producto' => $this->platoSteak->nombre,
                    'cantidad' => 1,
                    'precio_unitario' => 68000,
                    'subtotal' => 68000,
                ]);

                // Simular fallo crítico antes de commitear
                throw new \RuntimeException('Fallo simulado de hardware de impresión');
            });
        } catch (\RuntimeException $e) {
            // Esperado
        }

        // El pedido debe haber sido completamente revertido
        $this->assertEquals($totalPedidosAntes, Pedido::count());
        $this->assertDatabaseMissing('pedidos', ['codigo' => 'ORD-ATOMIC-FAIL']);
    }

    public function test_descuento_atomico_de_inventario_por_receta(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-INV-OK',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'subtotal' => 68000,
            'total' => 68000,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->platoSteak->id,
            'nombre_producto' => $this->platoSteak->nombre,
            'cantidad' => 2, // 2 platos = 2 * 0.35 = 0.70kg base
            'precio_unitario' => 68000,
            'subtotal' => 136000,
            'inventario_descontado' => false,
        ]);

        /** @var InventarioService $invService */
        $invService = app(InventarioService::class);
        $invService->descontarStockPorPedido($pedido);

        $this->insumoCarne->refresh();
        $this->assertLessThan(10.0, (float) $this->insumoCarne->stock_actual);

        $item->refresh();
        $this->assertTrue((bool) $item->inventario_descontado);
    }
}
