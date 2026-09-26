<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CajaReporteGestionTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    protected User $cajero;

    protected Caja $cajaPrincipal;

    protected Caja $cajaBarra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Central',
            'codigo' => 'SEDE-01',
            'direccion' => 'Calle 80 # 11-20',
            'activo' => true,
        ]);

        $adminRole = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $cajeroRole = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Admin Caja',
            'email' => 'admin.caja@test.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $this->cajero = User::create([
            'role_id' => $cajeroRole->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Cajero Pedro',
            'email' => 'pedro@test.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $this->cajaPrincipal = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Salón 1',
            'codigo' => 'CAJA-01',
            'tipo' => 'principal',
            'descripcion' => 'Caja principal de recepción y salón',
            'activa' => true,
        ]);

        $this->cajaBarra = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Barra Cocteles',
            'codigo' => 'CAJA-02',
            'tipo' => 'barra',
            'descripcion' => 'Caja ubicada en barra de bebidas',
            'activa' => true,
        ]);
    }

    public function test_caja_model_persists_tipo_and_descripcion(): void
    {
        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Domicilios',
            'codigo' => 'CAJA-DEL',
            'tipo' => 'delivery',
            'descripcion' => 'Terminal dedicada a pedidos para llevar',
            'activa' => true,
        ]);

        $this->assertDatabaseHas('cajas', [
            'id' => $caja->id,
            'tipo' => 'delivery',
            'descripcion' => 'Terminal dedicada a pedidos para llevar',
        ]);
    }

    public function test_caja_reporte_service_resumen_y_gastos(): void
    {
        $turno = TurnoCaja::create([
            'caja_id' => $this->cajaPrincipal->id,
            'user_id' => $this->cajero->id,
            'monto_inicial' => 100000,
            'apertura_en' => now()->subHours(4),
            'cierre_en' => now()->subHour(),
            'total_ventas_efectivo' => 250000,
            'total_ventas_tarjeta' => 150000,
            'total_ventas_transferencia' => 50000,
            'total_ingresos' => 450000,
            'total_egresos' => 30000,
            'total_retiros' => 0,
            'monto_esperado_efectivo' => 320000,
            'monto_real_efectivo' => 320000,
            'diferencia' => 0,
            'estado' => 'cerrado',
        ]);

        MovimientoCaja::create([
            'turno_caja_id' => $turno->id,
            'user_id' => $this->cajero->id,
            'tipo' => 'egreso',
            'monto' => 30000,
            'concepto' => 'Compra de bolsas y servilletas',
            'metodo_pago' => 'efectivo',
            'categoria' => 'operativo',
            'autorizado_por' => 'Admin Supervisor',
        ]);

        $service = app(CajaReporteService::class);
        $resumen = $service->resumenPorCaja($this->cajaPrincipal->id);

        $this->assertEquals(450000, $resumen['total_ventas']);
        $this->assertEquals(250000, $resumen['ventas_efectivo']);
        $this->assertEquals(150000, $resumen['ventas_tarjeta']);
        $this->assertEquals(50000, $resumen['ventas_transferencia']);
        $this->assertEquals(30000, $resumen['total_gastos']);
        $this->assertEquals(420000, $resumen['neto']);
        $this->assertEquals(1, $resumen['turnos_count']);

        $gastos = $service->gastosPorCaja($this->cajaPrincipal->id);
        $this->assertEquals(30000, $gastos['total_gastos']);
        $this->assertCount(1, $gastos['movimientos']);
    }

    public function test_caja_reporte_service_comparar_cajas(): void
    {
        TurnoCaja::create([
            'caja_id' => $this->cajaPrincipal->id,
            'user_id' => $this->cajero->id,
            'monto_inicial' => 100000,
            'apertura_en' => now()->subHours(2),
            'total_ventas_efectivo' => 200000,
            'total_ventas_tarjeta' => 100000,
            'total_ventas_transferencia' => 0,
            'total_ingresos' => 300000,
            'total_egresos' => 10000,
            'total_retiros' => 0,
            'monto_esperado_efectivo' => 290000,
            'estado' => 'abierto',
        ]);

        TurnoCaja::create([
            'caja_id' => $this->cajaBarra->id,
            'user_id' => $this->cajero->id,
            'monto_inicial' => 50000,
            'apertura_en' => now()->subHours(2),
            'total_ventas_efectivo' => 80000,
            'total_ventas_tarjeta' => 40000,
            'total_ventas_transferencia' => 0,
            'total_ingresos' => 120000,
            'total_egresos' => 0,
            'total_retiros' => 0,
            'monto_esperado_efectivo' => 130000,
            'estado' => 'abierto',
        ]);

        $service = app(CajaReporteService::class);
        $comparativa = $service->compararCajas($this->sucursal->id);

        $this->assertEquals(2, $comparativa['total_cajas']);
        $this->assertEquals(420000, $comparativa['gran_total_ventas']);
        $this->assertEquals(10000, $comparativa['gran_total_gastos']);
        $this->assertEquals(410000, $comparativa['gran_total_neto']);
    }

    public function test_componente_caja_control_cambia_de_vistas(): void
    {
        $this->actingAs($this->admin);

        Volt::test('caja.control')
            ->assertSee('Control de Caja y Operaciones del Turno')
            ->assertSee('Turno Operativo')
            ->assertSee('Gestión por Caja')
            ->assertSee('Comparativo')
            ->set('vistaCaja', 'por_caja')
            ->assertSee('Caja Seleccionada')
            ->assertSee('Historial de Turnos')
            ->set('vistaCaja', 'comparativo')
            ->assertSee('Facturación Total Sede')
            ->assertSee('Comparativa Financiera por Terminal de Cobro');
    }
}
