<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Insumo;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\ReposicionRapidaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReposicionRapidaInsumoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Poblado',
            'codigo' => 'POB-01',
            'activa' => true,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
        ]);
    }

    public function test_servicio_prepara_datos_y_sugiere_cantidad_optima(): void
    {
        $insumo = Insumo::create([
            'nombre' => 'Queso Crema Philadelphia',
            'codigo' => 'INS-QC-01',
            'unidad_medida' => 'kg',
            'stock_actual' => 2.0,
            'stock_minimo' => 8.0,
            'capacidad_maxima' => 12.0,
            'costo_unitario' => 32000,
            'activo' => true,
        ]);

        $service = app(ReposicionRapidaService::class);
        $datos = $service->prepararDatosInsumo($insumo->id);

        $this->assertEquals(2.0, $datos['stock_actual']);
        $this->assertEquals(8.0, $datos['stock_minimo']);
        $this->assertEquals(10.0, $datos['cantidad_sugerida']); // 12 - 2 = 10
        $this->assertEquals(32000, $datos['costo_unitario_estimado']);
    }

    public function test_crear_proveedor_rapido_asocia_correctamente_al_insumo(): void
    {
        $this->actingAs($this->admin);

        $insumo = Insumo::create([
            'nombre' => 'Aguacate Hass de Exportación',
            'codigo' => 'INS-AG-01',
            'unidad_medida' => 'kg',
            'stock_actual' => 1.5,
            'stock_minimo' => 10.0,
            'costo_unitario' => 9500,
            'activo' => true,
        ]);

        $service = app(ReposicionRapidaService::class);
        $proveedor = $service->crearProveedorRapido($insumo->id, [
            'nombre' => 'Distribuidora Frutas del Valle',
            'telefono' => '3009876543',
            'email' => 'ventas@frutasdelvalle.com',
            'nit' => '901234567-1',
            'contacto' => 'Don Fernando',
        ]);

        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'nombre' => 'Distribuidora Frutas del Valle',
        ]);

        $insumo->refresh();
        $this->assertEquals($proveedor->id, $insumo->proveedor_id);
        $this->assertEquals('Distribuidora Frutas del Valle', $insumo->proveedor_nombre);
    }

    public function test_generar_enlaces_pedido_contiene_marca_restomaster_y_datos_insumo(): void
    {
        $proveedor = Proveedor::create([
            'nombre' => 'Carnes San Martín',
            'telefono' => '3104567890',
            'email' => 'pedidos@sanmartin.co',
            'contacto' => 'Rodrigo',
            'activo' => true,
        ]);

        $insumo = Insumo::create([
            'nombre' => 'Lomo Fino Angus',
            'codigo' => 'INS-LM-01',
            'unidad_medida' => 'kg',
            'stock_actual' => 1.0,
            'stock_minimo' => 6.0,
            'costo_unitario' => 45000,
            'proveedor_id' => $proveedor->id,
            'activo' => true,
        ]);

        $service = app(ReposicionRapidaService::class);
        $enlaces = $service->generarEnlacesPedido($insumo, $proveedor, 5.0);

        $this->assertStringContainsString('RestoMaster', $enlaces['texto']);
        $this->assertStringContainsString('5 kg', $enlaces['texto']);
        $this->assertStringContainsString('Lomo Fino Angus', $enlaces['texto']);
        $this->assertStringStartsWith('https://wa.me/573104567890', $enlaces['url_whatsapp']);
        $this->assertStringStartsWith('mailto:pedidos@sanmartin.co', $enlaces['url_email']);
    }

    public function test_registrar_orden_pedido_guarda_compra_en_estado_solicitada(): void
    {
        $this->actingAs($this->admin);

        $proveedor = Proveedor::create([
            'nombre' => 'Lácteos de Antioquia',
            'activo' => true,
        ]);

        $insumo = Insumo::create([
            'nombre' => 'Crema de Leche Fresca',
            'codigo' => 'INS-CL-01',
            'unidad_medida' => 'kg',
            'stock_actual' => 0.5,
            'stock_minimo' => 5.0,
            'costo_unitario' => 18000,
            'proveedor_id' => $proveedor->id,
            'activo' => true,
        ]);

        $service = app(ReposicionRapidaService::class);
        $orden = $service->registrarOrdenPedidoPendiente($insumo, $proveedor->id, 4.0, 18000, $this->admin);

        $this->assertDatabaseHas('compras', [
            'id' => $orden->id,
            'proveedor_id' => $proveedor->id,
            'estado' => 'solicitada',
            'subtotal' => 72000,
        ]);

        $this->assertDatabaseHas('compra_lineas', [
            'compra_id' => $orden->id,
            'insumo_id' => $insumo->id,
            'cantidad' => 4.0,
        ]);
    }

    public function test_ingreso_directo_aumenta_stock_y_registra_movimiento_kardex(): void
    {
        $this->actingAs($this->admin);

        $insumo = Insumo::create([
            'nombre' => 'Arroz Especial Koshihikari',
            'codigo' => 'INS-AR-01',
            'unidad_medida' => 'kg',
            'stock_actual' => 2.0,
            'stock_minimo' => 10.0,
            'costo_unitario' => 12000,
            'activo' => true,
        ]);

        $service = app(ReposicionRapidaService::class);
        $resultado = $service->ingresarStockDirecto(
            insumoId: $insumo->id,
            cantidad: 8.0,
            costoUnitario: 14000,
            fuentePago: 'externo',
            numeroDocumento: 'REC-TEST-999',
            usuario: $this->admin
        );

        $insumo->refresh();
        $this->assertEquals(10.0, (float) $insumo->stock_actual);

        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $insumo->id,
            'tipo' => 'compra',
            'cantidad' => 8.0,
            'referencia_documento' => 'REC-TEST-999',
        ]);
    }

    public function test_ingreso_directo_con_caja_menor_registra_egreso_en_turno_abierto(): void
    {
        $this->actingAs($this->admin);

        $caja = Caja::create([
            'codigo' => 'CAJ-01',
            'nombre' => 'Caja Principal',
            'sucursal_id' => $this->sucursal->id,
        ]);

        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->admin->id,
            'apertura_en' => now(),
            'monto_inicial' => 200000,
        ]);

        $insumo = Insumo::create([
            'nombre' => 'Cilantro Fresco',
            'codigo' => 'INS-CF-01',
            'unidad_medida' => 'kg',
            'stock_actual' => 0.0,
            'stock_minimo' => 2.0,
            'costo_unitario' => 5000,
            'activo' => true,
        ]);

        $service = app(ReposicionRapidaService::class);
        $resultado = $service->ingresarStockDirecto(
            insumoId: $insumo->id,
            cantidad: 2.0,
            costoUnitario: 6000,
            fuentePago: 'caja_menor',
            numeroDocumento: 'VALE-001',
            usuario: $this->admin
        );

        $this->assertTrue($resultado['egreso_caja_registrado']);

        $this->assertDatabaseHas('movimientos_caja', [
            'turno_caja_id' => $turno->id,
            'tipo' => 'egreso',
            'monto' => 12000,
            'numero_comprobante' => 'VALE-001',
        ]);

        $turno->refresh();
        $this->assertEquals(12000, (float) $turno->total_egresos);
    }

    public function test_componente_livewire_ejecutivo_abre_modal_y_ejecuta_ingreso_directo(): void
    {
        $this->actingAs($this->admin);

        $insumo = Insumo::create([
            'nombre' => 'Sal Marina Fina',
            'codigo' => 'INS-SM-01',
            'unidad_medida' => 'kg',
            'stock_actual' => 1.0,
            'stock_minimo' => 5.0,
            'costo_unitario' => 3000,
            'activo' => true,
        ]);

        Volt::test('dashboard.ejecutivo')
            ->call('abrirModalReponer', $insumo->id)
            ->assertSet('modalReponer', true)
            ->assertSet('reponerInsumoId', $insumo->id)
            ->set('reponerCantidad', 4.0)
            ->set('reponerCostoUnitario', 3500)
            ->call('confirmarIngresoDirectoStock')
            ->assertSet('modalReponer', false);

        $insumo->refresh();
        $this->assertEquals(5.0, (float) $insumo->stock_actual);
    }
}
