<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\CuentaPorPagar;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HardeningDatabaseIntegridadTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    private User $userAdmin;

    private Proveedor $proveedor;

    private Categoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create([
            'nombre' => 'Administrador',
            'slug' => 'admin',
            'descripcion' => 'Admin test',
        ]);

        $this->sucursalA = Sucursal::create([
            'nombre' => 'Sucursal Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $this->sucursalB = Sucursal::create([
            'nombre' => 'Sucursal Laureles',
            'codigo' => 'LAU-02',
            'direccion' => 'Av Nutibara # 72-10',
            'telefono' => '3009876543',
            'activa' => true,
        ]);

        $this->userAdmin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursalA->id,
        ]);

        $this->proveedor = Proveedor::create([
            'nombre' => 'Distribuidora Carnes del Norte',
            'nit' => '900123456-1',
            'telefono' => '3104567890',
            'email' => 'ventas@carnesdelnorte.com',
            'activo' => true,
        ]);

        $this->categoria = Categoria::create([
            'nombre' => 'Carnes y Cortes',
            'slug' => 'carnes-y-cortes',
            'icono' => '🥩',
            'orden' => 1,
            'activo' => true,
        ]);
    }

    /**
     * Test 1: Restricción de unicidad compuesta en mesas (sucursal_id, numero)
     */
    public function test_mesas_impide_numeros_duplicados_en_la_misma_sucursal(): void
    {
        Mesa::create([
            'sucursal_id' => $this->sucursalA->id,
            'numero' => 'Mesa-10',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
        ]);

        $this->expectException(QueryException::class);

        Mesa::create([
            'sucursal_id' => $this->sucursalA->id,
            'numero' => 'Mesa-10',
            'capacidad' => 6,
            'zona' => 'terraza',
            'estado' => 'libre',
        ]);
    }

    /**
     * Test 2: Mesas en distintas sucursales pueden compartir el mismo número
     */
    public function test_mesas_permite_mismo_numero_en_sucursales_distintas(): void
    {
        $mesaA = Mesa::create([
            'sucursal_id' => $this->sucursalA->id,
            'numero' => 'Mesa-01',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
        ]);

        $mesaB = Mesa::create([
            'sucursal_id' => $this->sucursalB->id,
            'numero' => 'Mesa-01',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
        ]);

        $this->assertNotNull($mesaA->id);
        $this->assertNotNull($mesaB->id);
        $this->assertEquals($mesaA->numero, $mesaB->numero);
        $this->assertNotEquals($mesaA->sucursal_id, $mesaB->sucursal_id);
    }

    /**
     * Test 3: Cajas con soporte multi-sucursal (mismo código en sedes distintas)
     */
    public function test_cajas_permite_mismo_codigo_en_distintas_sucursales(): void
    {
        $cajaA = Caja::create([
            'sucursal_id' => $this->sucursalA->id,
            'nombre' => 'Caja Salón Provenza',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        $cajaB = Caja::create([
            'sucursal_id' => $this->sucursalB->id,
            'nombre' => 'Caja Salón Laureles',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        $this->assertNotNull($cajaA->id);
        $this->assertNotNull($cajaB->id);
        $this->assertEquals('CAJ-01', $cajaA->codigo);
        $this->assertEquals('CAJ-01', $cajaB->codigo);
    }

    /**
     * Test 4: Cajas impide mismo código en la misma sucursal
     */
    public function test_cajas_impide_mismo_codigo_en_la_misma_sucursal(): void
    {
        Caja::create([
            'sucursal_id' => $this->sucursalA->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJ-PRINCIPAL',
            'activa' => true,
        ]);

        $this->expectException(QueryException::class);

        Caja::create([
            'sucursal_id' => $this->sucursalA->id,
            'nombre' => 'Segunda Caja',
            'codigo' => 'CAJ-PRINCIPAL',
            'activa' => true,
        ]);
    }

    /**
     * Test 5: Productos impide duplicación de slugs
     */
    public function test_productos_impide_slugs_duplicados(): void
    {
        Producto::create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'Bife de Chorizo Angus',
            'slug' => 'bife-chorizo-angus',
            'precio' => 58000,
            'costo' => 24000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        $this->expectException(QueryException::class);

        Producto::create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'Bife de Chorizo Premium',
            'slug' => 'bife-chorizo-angus',
            'precio' => 62000,
            'costo' => 26000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);
    }

    /**
     * Test 6: Compras restrictOnDelete impide borrado si tiene líneas asociadas
     */
    public function test_compras_no_se_pueden_eliminar_si_tienen_lineas_asociadas_restrict_on_delete(): void
    {
        $compra = Compra::create([
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'FAC-INV-9901',
            'fecha' => '2026-09-18',
            'subtotal' => 250000.00,
            'forma_pago' => 'credito',
            'estado' => 'registrada',
            'user_id' => $this->userAdmin->id,
        ]);

        $insumo = Insumo::create([
            'nombre' => 'Lomo Fino Angus',
            'codigo' => 'INS-LOMO-99',
            'categoria' => 'carnes',
            'unidad_medida' => 'kg',
            'stock_actual' => 15.000,
            'stock_minimo' => 5.000,
            'costo_unitario' => 45000.00,
            'activo' => true,
        ]);

        CompraLinea::create([
            'compra_id' => $compra->id,
            'insumo_id' => $insumo->id,
            'cantidad' => 5.000,
            'costo_unitario' => 50000.00,
            'subtotal' => 250000.00,
        ]);

        $this->expectException(QueryException::class);

        $compra->delete();
    }

    /**
     * Test 7: Compras restrictOnDelete impide borrado si tiene CuentaPorPagar asociada
     */
    public function test_compras_no_se_pueden_eliminar_si_tienen_cuentas_por_pagar_asociadas_restrict_on_delete(): void
    {
        $compra = Compra::create([
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'FAC-CXP-8801',
            'fecha' => '2026-09-18',
            'subtotal' => 400000.00,
            'forma_pago' => 'credito',
            'estado' => 'registrada',
            'user_id' => $this->userAdmin->id,
        ]);

        CuentaPorPagar::create([
            'proveedor_nombre' => $this->proveedor->nombre,
            'proveedor_nit' => $this->proveedor->nit,
            'compra_id' => $compra->id,
            'concepto' => 'Factura compra de carnes',
            'monto_total' => 400000.00,
            'saldo_pendiente' => 400000.00,
            'fecha_emision' => '2026-09-18',
            'estado' => 'pendiente',
            'user_id' => $this->userAdmin->id,
        ]);

        $this->expectException(QueryException::class);

        $compra->delete();
    }

    /**
     * Test 8: Desasociación segura de insumo_id en CxP sin destruir la deuda
     */
    public function test_cuentas_por_pagar_insumo_id_permite_desasociar_sin_borrar_cuenta(): void
    {
        $insumo = Insumo::create([
            'nombre' => 'Queso Parmesano',
            'codigo' => 'INS-PARM-01',
            'categoria' => 'lacteos',
            'unidad_medida' => 'kg',
            'stock_actual' => 8.000,
            'stock_minimo' => 2.000,
            'costo_unitario' => 38000.00,
            'activo' => true,
        ]);

        $cxp = CuentaPorPagar::create([
            'proveedor_nombre' => 'Lácteos del Valle',
            'insumo_id' => $insumo->id,
            'concepto' => 'Compra queso parmesano',
            'monto_total' => 150000.00,
            'saldo_pendiente' => 150000.00,
            'fecha_emision' => '2026-09-18',
            'estado' => 'pendiente',
            'user_id' => $this->userAdmin->id,
        ]);

        // Simula desasociación en lugar de borrado físico destructivo
        $cxp->update(['insumo_id' => null]);

        $this->assertDatabaseHas('cuentas_por_pagar', [
            'id' => $cxp->id,
            'insumo_id' => null,
            'monto_total' => 150000.00,
        ]);
    }

    /**
     * Test 9: Existencia de índices B-Tree compuestos y en FKs críticas
     */
    public function test_indices_criticos_compuestos_y_fk_existen_en_esquema(): void
    {
        $this->assertTrue(Schema::hasIndex('mesas', ['sucursal_id', 'estado']));
        $this->assertTrue(Schema::hasIndex('mesas', ['sucursal_id', 'zona']));
        $this->assertTrue(Schema::hasIndex('pedidos', ['sucursal_id', 'estado']));
        $this->assertTrue(Schema::hasIndex('compra_lineas', ['compra_id']));
        $this->assertTrue(Schema::hasIndex('compra_lineas', ['insumo_id']));
        $this->assertTrue(Schema::hasIndex('cuentas_por_pagar', ['compra_id']));
        $this->assertTrue(Schema::hasIndex('cuentas_por_pagar', ['insumo_id']));
    }

    /**
     * Test 10: PostgreSQL CHECK constraints rechazan montos/cantidades negativas
     */
    public function test_check_constraints_en_postgresql_rechazan_valores_negativos(): void
    {
        $conn = null;

        if (DB::getDriverName() === 'pgsql') {
            $conn = DB::connection();
        } else {
            try {
                config(['database.connections.pgsql.database' => 'restomaster']);
                DB::purge('pgsql');
                $conn = DB::connection('pgsql');
                $conn->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('Conexión PostgreSQL no disponible en este entorno: '.$e->getMessage());
            }
        }

        $conn->beginTransaction();

        try {
            // 1. Pedidos con total negativo
            $conn->statement('SAVEPOINT sp_pedido');
            $pedidoInvalido = false;
            try {
                $conn->statement("INSERT INTO pedidos (codigo, tipo, estado, subtotal, total, created_at, updated_at) VALUES ('ORD-NEG-TEST-01', 'mesa', 'creado', 100, -50, now(), now())");
            } catch (QueryException $e) {
                $conn->statement('ROLLBACK TO SAVEPOINT sp_pedido');
                $pedidoInvalido = str_contains($e->getMessage(), 'chk_pedidos_totales_no_negativos') || str_contains($e->getMessage(), '23514');
            }
            $this->assertTrue($pedidoInvalido, 'PostgreSQL debe rechazar pedidos con total negativo');

            // 2. Items pedido con cantidad cero o negativa
            $conn->statement('SAVEPOINT sp_item');
            $itemInvalido = false;
            try {
                // Obtener un pedido y producto real para no fallar por FK
                $pedidoRealId = $conn->selectOne('SELECT id FROM pedidos LIMIT 1')?->id ?? 1;
                $productoRealId = $conn->selectOne('SELECT id FROM productos LIMIT 1')?->id ?? 1;
                $conn->statement("INSERT INTO items_pedido (pedido_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal, created_at, updated_at) VALUES ({$pedidoRealId}, {$productoRealId}, 'Plato Test', 0, 10, 0, now(), now())");
            } catch (QueryException $e) {
                $conn->statement('ROLLBACK TO SAVEPOINT sp_item');
                $itemInvalido = str_contains($e->getMessage(), 'chk_items_pedido_cantidad_positiva') || str_contains($e->getMessage(), '23514');
            }
            $this->assertTrue($itemInvalido, 'PostgreSQL debe rechazar items de pedido con cantidad <= 0');

            // 3. Movimientos de caja con monto negativo
            $conn->statement('SAVEPOINT sp_movcaja');
            $movCajaInvalido = false;
            try {
                $turnoRealId = $conn->selectOne('SELECT id FROM turnos_caja LIMIT 1')?->id ?? 1;
                $userRealId = $conn->selectOne('SELECT id FROM users LIMIT 1')?->id ?? 1;
                $conn->statement("INSERT INTO movimientos_caja (turno_caja_id, user_id, tipo, concepto, monto, created_at, updated_at) VALUES ({$turnoRealId}, {$userRealId}, 'ingreso', 'Test', -100, now(), now())");
            } catch (QueryException $e) {
                $conn->statement('ROLLBACK TO SAVEPOINT sp_movcaja');
                $movCajaInvalido = str_contains($e->getMessage(), 'chk_movimientos_caja_monto_positivo') || str_contains($e->getMessage(), '23514');
            }
            $this->assertTrue($movCajaInvalido, 'PostgreSQL debe rechazar movimientos de caja con monto negativo');

            // 4. Recetas con merma esperada superior al 100%
            $conn->statement('SAVEPOINT sp_receta');
            $recetaInvalida = false;
            try {
                $prodId = $conn->selectOne('SELECT id FROM productos LIMIT 1')?->id ?? 1;
                $insId = $conn->selectOne('SELECT id FROM insumos LIMIT 1')?->id ?? 1;
                $conn->statement("INSERT INTO recetas (producto_id, insumo_id, cantidad, merma_esperada_pct, created_at, updated_at) VALUES ({$prodId}, {$insId}, 1.0, 150.0, now(), now())");
            } catch (QueryException $e) {
                $conn->statement('ROLLBACK TO SAVEPOINT sp_receta');
                $recetaInvalida = str_contains($e->getMessage(), 'chk_recetas_cantidad_merma') || str_contains($e->getMessage(), '23514');
            }
            $this->assertTrue($recetaInvalida, 'PostgreSQL debe rechazar recetas con merma > 100%');

            // 5. Insumos con costo unitario negativo
            $conn->statement('SAVEPOINT sp_insumo');
            $insumoInvalido = false;
            try {
                $conn->statement("INSERT INTO insumos (nombre, codigo, categoria, unidad_medida, costo_unitario, created_at, updated_at) VALUES ('Insumo Negativo Test', 'INS-NEG-TEST-99', 'carnes', 'kg', -500, now(), now())");
            } catch (QueryException $e) {
                $conn->statement('ROLLBACK TO SAVEPOINT sp_insumo');
                $insumoInvalido = str_contains($e->getMessage(), 'chk_insumos_costo_stock') || str_contains($e->getMessage(), '23514');
            }
            $this->assertTrue($insumoInvalido, 'PostgreSQL debe rechazar insumos con costo unitario negativo');

            // 6. Compras con subtotal negativo
            $conn->statement('SAVEPOINT sp_compra');
            $compraInvalida = false;
            try {
                $provId = $conn->selectOne('SELECT id FROM proveedores LIMIT 1')?->id ?? 1;
                $userId = $conn->selectOne('SELECT id FROM users LIMIT 1')?->id ?? 1;
                $conn->statement("INSERT INTO compras (proveedor_id, numero_factura, fecha, subtotal, forma_pago, estado, user_id, created_at, updated_at) VALUES ({$provId}, 'FAC-NEG-TEST-01', '2026-09-18', -100, 'credito', 'registrada', {$userId}, now(), now())");
            } catch (QueryException $e) {
                $conn->statement('ROLLBACK TO SAVEPOINT sp_compra');
                $compraInvalida = str_contains($e->getMessage(), 'chk_compras_subtotal') || str_contains($e->getMessage(), '23514');
            }
            $this->assertTrue($compraInvalida, 'PostgreSQL debe rechazar compras con subtotal negativo');

        } finally {
            $conn->rollBack();
        }
    }
}
