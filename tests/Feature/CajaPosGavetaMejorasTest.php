<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TrabajoImpresion;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\ImpresionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CajaPosGavetaMejorasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cajero;

    private User $mesero;

    private Sucursal $sucursal;

    private Caja $caja;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin', 'descripcion' => 'Admin']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero', 'descripcion' => 'Cajero']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero', 'descripcion' => 'Mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Centro',
            'codigo' => 'CEN-01',
            'direccion' => 'Calle Central 100',
            'activa' => true,
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
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

        $this->mesero = User::create([
            'name' => 'Mesero Test',
            'email' => 'mesero@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Sushi', 'slug' => 'sushi', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
        $this->producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Roll Salmón Test',
            'slug' => 'roll-salmon-test',
            'precio' => 30000.00,
            'activo' => true,
        ]);
    }

    public function test_caja_permite_registrar_ingreso_de_efectivo_y_actualiza_monto_esperado(): void
    {
        $this->actingAs($this->cajero);

        $turno = app(CajaService::class)->abrirTurno($this->caja, $this->cajero, 100000.00, 'Apertura inicial');

        Volt::test('caja.control')
            ->set('turnoId', $turno->id)
            ->call('abrirModalMovimiento', 'ingreso')
            ->assertSet('mostrarModalMovimiento', true)
            ->assertSet('tipoMovimiento', 'ingreso')
            ->set('montoMovimiento', 45000.00)
            ->set('conceptoMovimiento', 'Inyección de sencillo para cambio')
            ->set('comprobanteMovimiento', 'REC-009')
            ->call('registrarMovimiento')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalMovimiento', false);

        $this->assertDatabaseHas('movimientos_caja', [
            'turno_caja_id' => $turno->id,
            'tipo' => 'ingreso',
            'monto' => 45000.00,
            'concepto' => 'Inyección de sencillo para cambio',
        ]);

        $turno->refresh();
        $this->assertEquals(45000.00, (float) $turno->total_ingresos);
        // Base $100.000 + Ingreso $45.000 = $145.000 esperado
        $this->assertEquals(145000.00, (float) $turno->monto_esperado_efectivo);
    }

    public function test_impresion_incluye_secuencia_escpos_apertura_gaveta_en_efectivo_y_reporte_z(): void
    {
        $impresionService = app(ImpresionService::class);
        $drawerKickSecuencia = $impresionService->comandoAbrirGaveta();

        // 1. Ticket de venta pagado en efectivo -> debe incluir apertura de gaveta
        $pedidoEfectivo = Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'usuario_id' => $this->cajero->id,
            'codigo' => 'TEST-001',
            'tipo' => 'mostrador',
            'estado' => 'pagado',
            'subtotal' => 30000.00,
            'total' => 30000.00,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        $trabajoEfectivo = $impresionService->despacharTicketVenta($pedidoEfectivo, $this->cajero);
        $this->assertStringContainsString($drawerKickSecuencia, $trabajoEfectivo->contenido_raw);

        // 2. Ticket de venta pagado en tarjeta -> NO debe incluir apertura de gaveta
        $pedidoTarjeta = Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'usuario_id' => $this->cajero->id,
            'codigo' => 'TEST-002',
            'tipo' => 'mostrador',
            'estado' => 'pagado',
            'subtotal' => 30000.00,
            'total' => 30000.00,
            'metodo_pago' => 'tarjeta',
            'pagado_en' => now(),
        ]);

        $trabajoTarjeta = $impresionService->despacharTicketVenta($pedidoTarjeta, $this->cajero);
        $this->assertStringNotContainsString($drawerKickSecuencia, $trabajoTarjeta->contenido_raw);

        // 3. Reporte Z de arqueo -> SIEMPRE debe incluir apertura de gaveta
        $turno = app(CajaService::class)->abrirTurno($this->caja, $this->cajero, 100000.00);
        $trabajoZ = $impresionService->despacharReporteZ($turno, $this->cajero);
        $this->assertStringContainsString($drawerKickSecuencia, $trabajoZ->contenido_raw);
    }

    public function test_caja_permite_apertura_manual_de_gaveta_escpos(): void
    {
        $this->actingAs($this->cajero);

        Volt::test('caja.control')
            ->call('abrirGavetaManual')
            ->assertDispatched('notificacion');

        $trabajo = TrabajoImpresion::where('tipo', 'apertura_gaveta')->latest()->first();
        $this->assertNotNull($trabajo);
        $this->assertStringContainsString(app(ImpresionService::class)->comandoAbrirGaveta(), $trabajo->contenido_raw);
    }

    public function test_pos_bloquea_preventivamente_cobro_sin_caja_abierta_y_muestra_modal_apertura(): void
    {
        $this->actingAs($this->cajero);

        // Asegurar que no hay turnos abiertos
        TurnoCaja::query()->update(['estado' => 'cerrado']);

        Volt::test('pos.terminal')
            ->set('carrito', [
                $this->producto->id => [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'precio' => $this->producto->precio,
                    'cantidad' => 1,
                    'subtotal' => $this->producto->precio,
                    'notas' => '',
                ],
            ])
            ->call('abrirModalCobro')
            ->assertSet('mostrarModalAperturaPos', true)
            ->assertSet('mostrarModalCobro', false);
    }

    public function test_pos_permite_abrir_turno_con_base_y_desbloquea_cobro(): void
    {
        $this->actingAs($this->cajero);

        TurnoCaja::query()->update(['estado' => 'cerrado']);

        Volt::test('pos.terminal')
            ->set('carrito', [
                $this->producto->id => [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'precio' => $this->producto->precio,
                    'cantidad' => 1,
                    'subtotal' => $this->producto->precio,
                    'notas' => '',
                ],
            ])
            ->call('abrirModalCobro')
            ->assertSet('mostrarModalAperturaPos', true)
            ->set('cajaAperturaId', $this->caja->id)
            ->set('baseAperturaPos', 125000.00)
            ->set('notasAperturaPos', 'Apertura directa desde mostrador')
            ->call('abrirTurnoDesdePos')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalAperturaPos', false)
            ->assertSet('mostrarModalCobro', true);

        $turnoCreado = TurnoCaja::where('caja_id', $this->caja->id)->where('estado', 'abierto')->first();
        $this->assertNotNull($turnoCreado);
        $this->assertEquals(125000.00, (float) $turnoCreado->monto_inicial);
    }

    public function test_mesero_sin_permiso_de_abrir_caja_recibe_advertencia_preventiva_en_pos(): void
    {
        $this->actingAs($this->mesero);

        TurnoCaja::query()->update(['estado' => 'cerrado']);

        Volt::test('pos.terminal')
            ->set('carrito', [
                $this->producto->id => [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'precio' => $this->producto->precio,
                    'cantidad' => 1,
                    'subtotal' => $this->producto->precio,
                    'notas' => '',
                ],
            ])
            ->call('abrirModalCobro')
            ->assertSet('mostrarModalAperturaPos', false)
            ->assertSet('mostrarModalCobro', false)
            ->assertDispatched('notificacion');
    }

    public function test_admin_puede_editar_terminal_de_caja(): void
    {
        $this->actingAs($this->admin);

        Volt::test('caja.control')
            ->call('abrirModalGestionTerminales')
            ->assertSet('modalGestionTerminalesOpen', true)
            ->call('iniciarEdicionCaja', $this->caja->id)
            ->assertSet('cajaEditandoId', $this->caja->id)
            ->set('formEditarCaja.nombre', 'Caja Sushi Bar Principal')
            ->set('formEditarCaja.codigo', 'CAJ-BAR-01')
            ->call('guardarEdicionCaja')
            ->assertHasNoErrors()
            ->assertSet('cajaEditandoId', null);

        $this->assertDatabaseHas('cajas', [
            'id' => $this->caja->id,
            'nombre' => 'Caja Sushi Bar Principal',
            'codigo' => 'CAJ-BAR-01',
        ]);
    }

    public function test_admin_puede_alternar_estado_activo_inactivo_de_terminal(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue($this->caja->activa);

        Volt::test('caja.control')
            ->call('alternarEstadoCaja', $this->caja->id);

        $this->assertFalse($this->caja->fresh()->activa);

        Volt::test('caja.control')
            ->call('alternarEstadoCaja', $this->caja->id);

        $this->assertTrue($this->caja->fresh()->activa);
    }

    public function test_admin_puede_eliminar_caja_sin_historial_de_turnos(): void
    {
        $this->actingAs($this->admin);

        $cajaVacia = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Sin Uso',
            'codigo' => 'CAJ-VACIA',
            'activa' => true,
        ]);

        Volt::test('caja.control')
            ->call('eliminarCaja', $cajaVacia->id)
            ->assertDispatched('notificacion');

        $this->assertDatabaseMissing('cajas', ['id' => $cajaVacia->id]);
    }

    public function test_no_permite_eliminar_caja_con_historial_de_turnos(): void
    {
        $this->actingAs($this->admin);

        // Crear turno asociado a la caja
        app(CajaService::class)->abrirTurno($this->caja, $this->cajero, 100000.00);

        Volt::test('caja.control')
            ->call('eliminarCaja', $this->caja->id)
            ->assertDispatched('notificacion');

        // La caja NO debe ser eliminada
        $this->assertDatabaseHas('cajas', ['id' => $this->caja->id]);
    }

    public function test_cajero_no_puede_editar_terminales(): void
    {
        $this->actingAs($this->cajero);

        $cajaVacia = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Para Test',
            'codigo' => 'CAJ-TEST',
            'activa' => true,
        ]);

        Volt::test('caja.control')
            ->call('iniciarEdicionCaja', $cajaVacia->id)
            ->set('formEditarCaja.nombre', 'Nombre Ilegal')
            ->set('formEditarCaja.codigo', 'CAJ-ILEGAL')
            ->call('guardarEdicionCaja')
            ->assertForbidden();
    }
}
