<?php

namespace Tests\Integration;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AntiNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        $this->sucursal = Sucursal::create([
            'nombre' => 'SushiXpress N+1 Audit',
            'codigo' => 'NP1-01',
            'activa' => true,
        ]);

        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $categoria = Categoria::create(['nombre' => 'Rolls', 'slug' => 'rolls', 'activo' => true]);

        // Crear 10 mesas y 10 pedidos con items
        for ($i = 1; $i <= 10; $i++) {
            $mesa = Mesa::create([
                'sucursal_id' => $this->sucursal->id,
                'numero' => "Mesa-{$i}",
                'capacidad' => 4,
                'zona' => 'salon',
                'estado' => 'ocupada',
                'activo' => true,
            ]);

            $producto = Producto::create([
                'categoria_id' => $categoria->id,
                'nombre' => "Roll Especial #{$i}",
                'slug' => "roll-especial-{$i}",
                'precio' => 30000 + ($i * 1000),
                'activo' => true,
            ]);

            $pedido = Pedido::create([
                'codigo' => "ORD-NP1-{$i}",
                'sucursal_id' => $this->sucursal->id,
                'mesa_id' => $mesa->id,
                'tipo' => 'mesa',
                'estado' => 'en_cocina',
                'subtotal' => $producto->precio,
                'total' => $producto->precio,
                'usuario_id' => $this->mesero->id,
            ]);

            ItemPedido::create([
                'pedido_id' => $pedido->id,
                'producto_id' => $producto->id,
                'nombre_producto' => $producto->nombre,
                'cantidad' => 2,
                'precio_unitario' => $producto->precio,
                'subtotal' => $producto->precio * 2,
            ]);
        }
    }

    /**
     * Test de presupuesto estricto de consultas (Anti N+1) en carga de comandas activas.
     */
    public function test_carga_de_comandas_activas_no_supera_presupuesto_de_consultas(): void
    {
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // Carga con Eager Loading estándar del KDS / POS
        $pedidos = Pedido::where('sucursal_id', $this->sucursal->id)
            ->with(['mesa', 'items.producto', 'usuario'])
            ->get();

        $this->assertCount(10, $pedidos);

        // Iterar todos los pedidos, mesas e items simulando renderizado de pantalla
        foreach ($pedidos as $pedido) {
            $nombreMesa = $pedido->mesa?->numero;
            $nombreMesero = $pedido->usuario?->name;
            $itemsCount = $pedido->items->count();

            foreach ($pedido->items as $item) {
                $nombreProducto = $item->producto?->nombre;
                $this->assertNotEmpty($nombreProducto);
            }

            $this->assertNotEmpty($nombreMesa);
            $this->assertNotEmpty($nombreMesero);
            $this->assertGreaterThan(0, $itemsCount);
        }

        // El presupuesto debe ser estrictamente O(1) con respecto al número de pedidos (<= 5 queries)
        $this->assertLessThanOrEqual(5, $queryCount, "Se detectó posible N+1: se ejecutaron {$queryCount} consultas para 10 pedidos.");
    }
}
