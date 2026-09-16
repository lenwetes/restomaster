<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CuentaPorPagar;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\MenuService;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditoriaNuevosFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $gerente;

    private User $mesero;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador', 'descripcion' => 'Admin']);
        $rolGerente = Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente', 'descripcion' => 'Gerente']);
        $rolMesero = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero', 'descripcion' => 'Mesero']);

        $this->sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'SUC-AUDIT'],
            ['nombre' => 'Sucursal Auditoría', 'direccion' => 'Calle Test # 1-2', 'activa' => true]
        );

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin.audit@restomaster.com',
            'telefono' => '3001110001',
            'role_id' => $rolAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test',
            'email' => 'gerente.audit@restomaster.com',
            'telefono' => '3001110002',
            'role_id' => $rolGerente->id,
            'sucursal_id' => $this->sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero Test',
            'email' => 'mesero.audit@restomaster.com',
            'telefono' => '3001110003',
            'role_id' => $rolMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);
    }

    public function test_cxp_crea_cuenta_con_nit_vencimiento_y_factura(): void
    {
        $this->actingAs($this->admin);

        Volt::test('cxp.index')
            ->set('crearForm.proveedor_nombre', 'Distribuidora Carnes SAS')
            ->set('crearForm.proveedor_nit', '900123456-7')
            ->set('crearForm.numero_factura', 'FACT-9988')
            ->set('crearForm.concepto', 'Compra de lomo fino')
            ->set('crearForm.monto_total', 150000.50)
            ->set('crearForm.fecha_emision', '2026-09-16')
            ->set('crearForm.fecha_vencimiento', '2026-10-16')
            ->call('crearCuenta')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cuentas_por_pagar', [
            'proveedor_nombre' => 'Distribuidora Carnes SAS',
            'proveedor_nit' => '900123456-7',
            'numero_factura' => 'FACT-9988',
            'concepto' => 'Compra de lomo fino',
        ]);

        $cuenta = CuentaPorPagar::where('numero_factura', 'FACT-9988')->firstOrFail();
        $this->assertEquals('2026-10-16', $cuenta->fecha_vencimiento?->toDateString());
    }

    public function test_cxp_registra_abono_con_comprobante_y_notas(): void
    {
        $this->actingAs($this->admin);

        $cuenta = CuentaPorPagar::create([
            'proveedor_nombre' => 'Pescados del Pacífico',
            'proveedor_nit' => '800555444-1',
            'numero_factura' => 'INV-001',
            'concepto' => 'Salmón fresco',
            'monto_total' => 200000,
            'saldo_pendiente' => 200000,
            'fecha_emision' => '2026-09-16',
            'estado' => 'pendiente',
            'user_id' => $this->admin->id,
        ]);

        Volt::test('cxp.index')
            ->call('abrirPago', $cuenta->id)
            ->set('pagoForm.monto', 50000)
            ->set('pagoForm.metodo_pago', 'transferencia')
            ->set('pagoForm.comprobante', 'TRX-774411')
            ->set('pagoForm.notas', 'Pago 1er abono')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos_cxps', [
            'cuenta_por_pagar_id' => $cuenta->id,
            'monto' => 50000,
            'metodo_pago' => 'transferencia',
        ]);

        $this->assertDatabaseHas('cuentas_por_pagar', [
            'id' => $cuenta->id,
            'saldo_pendiente' => 150000,
        ]);
    }

    public function test_is_delivery_y_flota_reconocen_roles_delivery_y_repartidor(): void
    {
        $rolDelivery = Role::firstOrCreate(['slug' => 'delivery'], ['nombre' => 'Delivery', 'descripcion' => 'Delivery']);
        $rolRepartidor = Role::firstOrCreate(['slug' => 'repartidor'], ['nombre' => 'Repartidor', 'descripcion' => 'Repartidor']);

        $userDelivery = User::create([
            'name' => 'Motorizado Delivery',
            'email' => 'moto.delivery@restomaster.com',
            'telefono' => '3110001111',
            'role_id' => $rolDelivery->id,
            'sucursal_id' => $this->sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);

        $userRepartidor = User::create([
            'name' => 'Motorizado Repartidor',
            'email' => 'moto.repartidor@restomaster.com',
            'telefono' => '3110002222',
            'role_id' => $rolRepartidor->id,
            'sucursal_id' => $this->sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);

        $this->assertTrue($userDelivery->isDelivery());
        $this->assertTrue($userRepartidor->isDelivery());

        $flota = app(DeliveryService::class)->obtenerFlotaMotorizados();

        $this->assertTrue($flota->contains('id', $userDelivery->id));
        $this->assertTrue($flota->contains('id', $userRepartidor->id));
    }

    public function test_clientes_guardar_direccion_requiere_autorizacion(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Cliente Auditoría',
            'telefono' => '3007778899',
            'activo' => true,
        ]);

        // Usuario mesero no autorizado para editar cliente
        $this->actingAs($this->mesero);
        Volt::test('clientes.index')
            ->set('clienteSeleccionadoId', $cliente->id)
            ->set('nuevaDireccion.direccion', 'Carrera 43A # 1-50')
            ->call('guardarDireccion')
            ->assertForbidden();

        // Admin sí autorizado
        $this->actingAs($this->admin);
        Volt::test('clientes.index')
            ->set('clienteSeleccionadoId', $cliente->id)
            ->set('nuevaDireccion.direccion', 'Carrera 43A # 1-50')
            ->call('guardarDireccion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('direcciones_cliente', [
            'cliente_id' => $cliente->id,
            'direccion' => 'Carrera 43A # 1-50',
        ]);
    }

    public function test_trabajadores_mutadores_exigen_rol_admin(): void
    {
        $trabajador = User::create([
            'name' => 'Trabajador Prueba',
            'email' => 'prueba.trabajador@restomaster.com',
            'telefono' => '3159998877',
            'role_id' => $this->mesero->role_id,
            'sucursal_id' => $this->sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);

        // Mesero intenta toggleActivo
        $this->actingAs($this->mesero);
        Volt::test('trabajadores.index')
            ->call('toggleActivo', $trabajador->id)
            ->assertForbidden();

        // Mesero intenta guardarNuevo
        Volt::test('trabajadores.index')
            ->set('nuevo.nombre', 'Nuevo Hacker')
            ->set('nuevo.email', 'hacker@restomaster.com')
            ->set('nuevo.telefono', '3150000000')
            ->set('nuevo.password', '123456')
            ->set('nuevo.role_id', $this->admin->role_id)
            ->call('guardarNuevo')
            ->assertForbidden();
    }

    public function test_pos_muestra_boton_nuevo_producto_a_gerente(): void
    {
        $this->actingAs($this->gerente);

        Volt::test('pos.terminal')
            ->assertSee('btnPosCrearProductoAdmin')
            ->assertSee('+ Nuevo Producto');
    }

    public function test_menu_service_invalida_pos_terminal_categorias(): void
    {
        Cache::put('pos.terminal.categorias', ['dummy' => true], 60);
        $this->assertTrue(Cache::has('pos.terminal.categorias'));

        app(MenuService::class)->invalidarCacheMenu();

        $this->assertFalse(Cache::has('pos.terminal.categorias'));
    }

    public function test_reserva_service_invalida_pos_terminal_mesas(): void
    {
        $mesa = Mesa::create([
            'numero' => 99,
            'capacidad' => 4,
            'estado' => 'libre',
            'sucursal_id' => $this->sucursal->id,
        ]);

        $reserva = Reserva::create([
            'nombre_contacto' => 'Reserva Cache Test',
            'telefono_contacto' => '3009998877',
            'personas' => 2,
            'fecha' => '2026-09-16',
            'hora_llegada' => '20:00',
            'duracion_min' => 120,
            'estado' => 'confirmada',
        ]);
        $reserva->mesas()->attach($mesa->id);

        Cache::put('pos.terminal.mesas', ['dummy' => true], 60);
        $this->assertTrue(Cache::has('pos.terminal.mesas'));

        app(ReservaService::class)->marcarLlego($reserva);

        $this->assertFalse(Cache::has('pos.terminal.mesas'));
    }
}
