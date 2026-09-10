<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase2CajaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cajero;

    private Sucursal $sucursal;

    private Caja $caja;

    private CajaService $cajaService;

    private PedidoService $pedidoService;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin', 'descripcion' => 'Admin']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero', 'descripcion' => 'Cajero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Matriz Central',
            'codigo' => 'MAT-01',
            'direccion' => 'Av. Gastronómica 123',
            'telefono' => '55-1234-5678',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Administrador Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->cajero = User::create([
            'name' => 'Cajero Test',
            'email' => 'cajero@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Test #01',
            'codigo' => 'CAJ-T01',
            'activa' => true,
        ]);

        $this->cajaService = app(CajaService::class);
        $this->pedidoService = app(PedidoService::class);
    }

    public function test_cajero_puede_abrir_turno_con_fondo_inicial(): void
    {
        $turno = $this->cajaService->abrirTurno($this->caja, $this->cajero, 150000.00, 'Apertura inicial');

        $this->assertDatabaseHas('turnos_caja', [
            'id' => $turno->id,
            'caja_id' => $this->caja->id,
            'user_id' => $this->cajero->id,
            'estado' => 'abierto',
            'monto_inicial' => 150000.00,
            'monto_esperado_efectivo' => 150000.00,
        ]);

        $this->assertDatabaseHas('asientos_contables', [
            'tipo' => 'ingreso',
            'cuenta' => 'caja_general',
            'monto' => 150000.00,
            'referencia_tipo' => 'apertura_turno',
            'referencia_id' => $turno->id,
        ]);
    }

    public function test_no_se_puede_abrir_dos_turnos_simultaneos_en_la_misma_caja(): void
    {
        $this->cajaService->abrirTurno($this->caja, $this->cajero, 100000.00);

        $this->expectException(\InvalidArgumentException::class);
        $this->cajaService->abrirTurno($this->caja, $this->admin, 50000.00);
    }

    public function test_registro_egreso_disminuye_efectivo_esperado_y_crea_asiento_contable(): void
    {
        $turno = $this->cajaService->abrirTurno($this->caja, $this->cajero, 200000.00);

        $movimiento = $this->cajaService->registrarMovimiento(
            $turno,
            'egreso',
            35000.00,
            'Compra insumos de emergencia',
            'efectivo',
            'FAC-100',
            'Administrador',
            $this->cajero
        );

        $turno->refresh();

        $this->assertEquals(35000.00, (float) $turno->total_egresos);
        $this->assertEquals(165000.00, (float) $turno->monto_esperado_efectivo);

        $this->assertDatabaseHas('movimientos_caja', [
            'id' => $movimiento->id,
            'tipo' => 'egreso',
            'monto' => 35000.00,
            'numero_comprobante' => 'FAC-100',
        ]);

        $this->assertDatabaseHas('asientos_contables', [
            'tipo' => 'gasto',
            'cuenta' => 'gastos_operativos',
            'monto' => 35000.00,
            'referencia_tipo' => 'movimiento_caja',
            'referencia_id' => $movimiento->id,
        ]);
    }

    public function test_registro_retiro_a_banco_disminuye_efectivo_esperado(): void
    {
        $turno = $this->cajaService->abrirTurno($this->caja, $this->cajero, 500000.00);

        $this->cajaService->registrarMovimiento(
            $turno,
            'retiro',
            200000.00,
            'Remesa parcial a banco BBVA',
            'efectivo',
            'REM-01',
            'Gerente',
            $this->admin
        );

        $turno->refresh();

        $this->assertEquals(200000.00, (float) $turno->total_retiros);
        $this->assertEquals(300000.00, (float) $turno->monto_esperado_efectivo);

        $this->assertDatabaseHas('asientos_contables', [
            'tipo' => 'gasto',
            'cuenta' => 'retiros_banco',
            'monto' => 200000.00,
        ]);
    }

    public function test_cobro_pedido_en_efectivo_aumenta_ventas_y_efectivo_esperado(): void
    {
        $turno = $this->cajaService->abrirTurno($this->caja, $this->cajero, 100000.00);

        $cat = Categoria::create(['nombre' => 'Sushi', 'slug' => 'sushi', 'icono' => '🍣', 'activo' => true]);
        $prod = Producto::create([
            'categoria_id' => $cat->id,
            'nombre' => 'Dragon Roll',
            'slug' => 'dragon-roll',
            'precio' => 45000.00,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $pedido = $this->pedidoService->crearPedido(
            ['tipo' => 'mostrador'],
            [['producto_id' => $prod->id, 'cantidad' => 2, 'precio_unitario' => 45000.00]],
            $this->cajero
        );

        $this->assertEquals(90000.00, (float) $pedido->total);

        // Cobrar pedido en efectivo
        $this->pedidoService->cobrarPedido($pedido, 'efectivo', 100000.00);

        $turno->refresh();

        $this->assertEquals(90000.00, (float) $turno->total_ventas_efectivo);
        $this->assertEquals(190000.00, (float) $turno->monto_esperado_efectivo);
        $this->assertEquals($turno->id, $pedido->fresh()->turno_caja_id);

        $this->assertDatabaseHas('asientos_contables', [
            'tipo' => 'ingreso',
            'cuenta' => 'ventas_restaurante',
            'monto' => 90000.00,
            'referencia_tipo' => 'pedido',
            'referencia_id' => $pedido->id,
        ]);
    }

    public function test_arqueo_ciego_calcula_sobrante_y_cierra_turno_con_reporte_z(): void
    {
        $turno = $this->cajaService->abrirTurno($this->caja, $this->cajero, 100000.00);

        // El esperado es 100,000. El cajero cuenta 105,000 (sobrante de +5,000)
        $turno = $this->cajaService->cerrarTurno($turno, 105000.00, $this->admin, 'Cierre sin incidencias');

        $this->assertEquals('cerrado', $turno->estado);
        $this->assertEquals(105000.00, (float) $turno->monto_real_efectivo);
        $this->assertEquals(5000.00, (float) $turno->diferencia);
        $this->assertNotNull($turno->cierre_en);

        $this->assertDatabaseHas('asientos_contables', [
            'tipo' => 'ingreso',
            'cuenta' => 'sobrante_caja',
            'monto' => 5000.00,
            'referencia_tipo' => 'arqueo_caja',
            'referencia_id' => $turno->id,
        ]);

        $reporteZ = $this->cajaService->generarReporteZ($turno);
        $this->assertEquals('SOBRANTE', $reporteZ['estado_cuadre']);
        $this->assertEquals(5000.00, $reporteZ['diferencia']);
    }

    public function test_arqueo_ciego_calcula_faltante_correctamente(): void
    {
        $turno = $this->cajaService->abrirTurno($this->caja, $this->cajero, 100000.00);

        // El esperado es 100,000. El cajero cuenta 98,000 (faltante de -2,000)
        $turno = $this->cajaService->cerrarTurno($turno, 98000.00, $this->admin, 'Faltante de 2000');

        $this->assertEquals(-2000.00, (float) $turno->diferencia);

        $this->assertDatabaseHas('asientos_contables', [
            'tipo' => 'gasto',
            'cuenta' => 'faltante_caja',
            'monto' => 2000.00,
            'referencia_tipo' => 'arqueo_caja',
        ]);

        $reporteZ = $this->cajaService->generarReporteZ($turno);
        $this->assertEquals('FALTANTE', $reporteZ['estado_cuadre']);
    }

    public function test_pantalla_caja_es_accesible_autenticado(): void
    {
        $this->actingAs($this->cajero)
            ->get(route('caja'))
            ->assertStatus(200)
            ->assertSee('Control de Caja y Operaciones del Turno')
            ->assertSee('CAJ-01');
    }
}
