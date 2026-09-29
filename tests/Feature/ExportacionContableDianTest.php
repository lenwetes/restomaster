<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ItemPedido;
use App\Models\MovimientoCaja;
use App\Models\NotaCredito;
use App\Models\Pedido;
use App\Models\PedidoDevolucion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\ExportadorContableService;
use App\Services\PedidoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportacionContableDianTest extends TestCase
{
    use RefreshDatabase;

    protected User $gerente;

    protected User $mesero;

    protected Sucursal $sucursal;

    protected Caja $caja;

    protected TurnoCaja $turno;

    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $rolGerente = Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente']);
        $rolMesero = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Central',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $this->gerente = User::factory()->create([
            'role_id' => $rolGerente->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Gerente Contable',
            'email' => 'gerente_contable@restomaster.test',
        ]);

        $this->mesero = User::factory()->create([
            'role_id' => $rolMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Mesero POS',
            'email' => 'mesero_pos@restomaster.test',
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJA-01',
            'activa' => true,
        ]);

        $this->turno = app(CajaService::class)->abrirTurno($this->caja, $this->gerente, 100000.0, 'Turno Test');

        $categoria = Categoria::create(['nombre' => 'Sushi Rolls', 'slug' => 'sushi-rolls', 'activo' => true]);
        $this->producto = Producto::create([
            'nombre' => 'Philadelphia Roll',
            'slug' => 'philadelphia-roll',
            'categoria_id' => $categoria->id,
            'precio' => 32400,
            'activo' => true,
        ]);
    }

    public function test_exportador_siigo_genera_partida_doble_balanceada_con_cuentas_puc(): void
    {
        $hoy = now()->toDateString();

        $cliente = Cliente::create([
            'nombre' => 'Empresa Andina SAS',
            'documento' => '900.123.456-1',
            'telefono' => '3001112233',
        ]);

        $pedido = Pedido::create([
            'codigo' => 'PED-SIIGO-01',
            'tipo' => 'pos',
            'usuario_id' => $this->mesero->id,
            'turno_caja_id' => $this->turno->id,
            'cliente_id' => $cliente->id,
            'estado' => 'pagado',
            'subtotal' => 60000,
            'total' => 64800,
            'propina' => 5000,
            'monto_pagado' => 69800,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => 'Philadelphia Roll',
            'cantidad' => 2,
            'precio_unitario' => 32400,
            'subtotal' => 64800,
            'cantidad_devuelta' => 0,
        ]);

        // Registrar una devolución parcial (Fase 8.3: con Nota de Crédito obligatoria)
        $pedidoService = app(PedidoService::class);
        $ncSiigo = NotaCredito::emitir($pedido, 'error_cargo', $this->gerente, 32400.0);
        $pedidoService->devolverItemPedido(
            item: $item,
            cantidad: 1,
            motivo: 'Bebida extra registrada por error',
            autorizadoPor: 'Gerente Contable',
            usuario: $this->gerente,
            metodoReembolso: 'efectivo',
            notaCreditoId: $ncSiigo->id
        );

        $service = app(ExportadorContableService::class);
        $data = $service->exportarSiigo($hoy, $hoy);

        $this->assertNotEmpty($data['filas']);
        $this->assertContains('Tipo Comprobante', $data['encabezados']);
        $this->assertContains('Cuenta Contable', $data['encabezados']);
        $this->assertContains('Debito', $data['encabezados']);
        $this->assertContains('Credito', $data['encabezados']);

        $totalDebitos = 0.0;
        $totalCreditos = 0.0;
        $cuentasPresentes = [];

        foreach ($data['filas'] as $fila) {
            $cuentasPresentes[] = $fila[3]; // Cuenta Contable
            $totalDebitos += (float) $fila[8]; // Débito
            $totalCreditos += (float) $fila[9]; // Crédito
        }

        // Partida Doble Perfecta: Débitos == Créditos
        $this->assertEqualsWithDelta($totalDebitos, $totalCreditos, 0.05, 'El comprobante Siigo debe estar exactamente balanceado.');

        // Verificación de Cuentas PUC Colombia estándar
        $this->assertContains('11050501', $cuentasPresentes, 'Debe registrar movimiento a Caja General');
        $this->assertContains('41350101', $cuentasPresentes, 'Debe registrar ingresos operacionales 4135');
        $this->assertContains('24950101', $cuentasPresentes, 'Debe registrar Impuesto Nacional al Consumo 8% (2495)');
        $this->assertContains('41750501', $cuentasPresentes, 'Debe registrar Devolución en ventas 4175');
    }

    public function test_exportador_alegra_genera_items_y_metodos_de_pago(): void
    {
        $hoy = now()->toDateString();

        $pedido = Pedido::create([
            'codigo' => 'PED-ALEGRA-01',
            'tipo' => 'pos',
            'usuario_id' => $this->mesero->id,
            'turno_caja_id' => $this->turno->id,
            'estado' => 'pagado',
            'subtotal' => 32400,
            'total' => 32400,
            'monto_pagado' => 32400,
            'metodo_pago' => 'tarjeta',
            'pagado_en' => now(),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => 'Philadelphia Roll',
            'cantidad' => 1,
            'precio_unitario' => 32400,
            'subtotal' => 32400,
            'cantidad_devuelta' => 0,
        ]);

        $service = app(ExportadorContableService::class);
        $data = $service->exportarAlegra($hoy, $hoy);

        $this->assertCount(1, $data['filas']);
        $fila = $data['filas'][0];
        $this->assertEquals('PED-ALEGRA-01', $fila[3]); // Numero Factura
        $this->assertEquals('Philadelphia Roll', $fila[4]); // Item
        $this->assertEquals(1, $fila[5]); // Cantidad
        $this->assertEquals('Tarjeta', $fila[10]); // Metodo Pago
    }

    public function test_exportador_world_office_y_helisa_generan_archivos_planos(): void
    {
        $hoy = now()->toDateString();

        $pedido = Pedido::create([
            'codigo' => 'PED-PLANO-01',
            'tipo' => 'pos',
            'usuario_id' => $this->mesero->id,
            'turno_caja_id' => $this->turno->id,
            'estado' => 'pagado',
            'subtotal' => 30000,
            'total' => 32400,
            'monto_pagado' => 32400,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => 'Philadelphia Roll',
            'cantidad' => 1,
            'precio_unitario' => 32400,
            'subtotal' => 32400,
        ]);

        $service = app(ExportadorContableService::class);

        // World Office (delimitado por punto y coma)
        $woTxt = $service->exportarWorldOffice($hoy, $hoy);
        $this->assertStringContainsString('TipoDoc;Documento;Fecha;Cuenta;Tercero', $woTxt);
        $this->assertStringContainsString('PED-PLANO-01', $woTxt);
        $this->assertStringContainsString('11050501', $woTxt);

        // Helisa (delimitado por pipe |)
        $helisaTxt = $service->exportarHelisa($hoy, $hoy);
        $this->assertStringContainsString('COMPROBANTE|CONSECUTIVO|FECHA|CUENTA|NIT_TERCERO', $helisaTxt);
        $this->assertStringContainsString('11050501', $helisaTxt);
    }

    public function test_generar_libro_fiscal_dian_calcula_comprobantes_e_ingresos_y_gastos(): void
    {
        $hoy = now()->toDateString();

        // Pedido 1
        Pedido::create([
            'codigo' => 'POS-001',
            'tipo' => 'pos',
            'usuario_id' => $this->mesero->id,
            'turno_caja_id' => $this->turno->id,
            'estado' => 'pagado',
            'subtotal' => 50000,
            'total' => 54000,
            'monto_pagado' => 54000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        // Pedido 2
        $p2 = Pedido::create([
            'codigo' => 'POS-002',
            'tipo' => 'pos',
            'usuario_id' => $this->mesero->id,
            'turno_caja_id' => $this->turno->id,
            'estado' => 'pagado',
            'subtotal' => 20000,
            'total' => 21600,
            'monto_pagado' => 21600,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        $it2 = ItemPedido::create([
            'pedido_id' => $p2->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => 'Roll Test',
            'cantidad' => 1,
            'precio_unitario' => 21600,
            'subtotal' => 21600,
        ]);

        // Gasto en efectivo del día
        MovimientoCaja::create([
            'turno_caja_id' => $this->turno->id,
            'user_id' => $this->gerente->id,
            'tipo' => 'egreso',
            'concepto' => 'Compra de bolsas y hielo',
            'monto' => 15000,
            'metodo_pago' => 'efectivo',
            'autorizado_por' => 'Gerente',
        ]);

        // Devolución (Fase 8.3: con Nota de Crédito obligatoria)
        $ncLibro = NotaCredito::emitir($p2, 'error_cargo', $this->gerente, 21600.0);
        app(PedidoService::class)->devolverItemPedido(
            item: $it2,
            cantidad: 1,
            motivo: 'Error de cobro',
            autorizadoPor: 'Gerente',
            usuario: $this->gerente,
            metodoReembolso: 'efectivo',
            notaCreditoId: $ncLibro->id
        );

        $service = app(ExportadorContableService::class);
        $libro = $service->generarLibroFiscalDian($hoy, $hoy);

        $this->assertEquals('RestoMaster S.A.S.', $libro['empresa']['razon_social']);
        $this->assertEquals(2, $libro['totales']['total_operaciones']);
        $this->assertEquals(75600, $libro['totales']['ingresos_brutos']);
        $this->assertEquals(21600, $libro['totales']['total_devoluciones']);
        $this->assertEquals(54000, $libro['totales']['ingresos_netos']);
        $this->assertEquals(15000, $libro['totales']['gastos_diarios_caja']);
        $this->assertEquals(39000, $libro['totales']['saldo_neto_fiscal']);

        // Comprobante Inicial y Final del día
        $dia = $libro['dias'][0];
        $this->assertEquals('POS-001', $dia['comprobante_inicial']);
        $this->assertEquals('POS-002', $dia['comprobante_final']);
    }

    public function test_endpoints_http_descargan_correctamente_los_reportes_contables(): void
    {
        $hoy = now()->toDateString();

        // 1. Siigo
        $respSiigo = $this->actingAs($this->gerente)->get(route('reportes.contable.siigo', ['desde' => $hoy, 'hasta' => $hoy]));
        $respSiigo->assertStatus(200);
        $respSiigo->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // 2. Alegra
        $respAlegra = $this->actingAs($this->gerente)->get(route('reportes.contable.alegra', ['desde' => $hoy, 'hasta' => $hoy]));
        $respAlegra->assertStatus(200);
        $respAlegra->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // 3. World Office
        $respWo = $this->actingAs($this->gerente)->get(route('reportes.contable.world-office', ['desde' => $hoy, 'hasta' => $hoy]));
        $respWo->assertStatus(200);
        $respWo->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        // 4. Helisa
        $respHelisa = $this->actingAs($this->gerente)->get(route('reportes.contable.helisa', ['desde' => $hoy, 'hasta' => $hoy]));
        $respHelisa->assertStatus(200);
        $respHelisa->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        // 5. Libro Fiscal CSV
        $respLibroCsv = $this->actingAs($this->gerente)->get(route('reportes.contable.libro-fiscal', ['desde' => $hoy, 'hasta' => $hoy, 'formato' => 'csv']));
        $respLibroCsv->assertStatus(200);
        $respLibroCsv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // 6. Libro Fiscal PDF
        $respLibroPdf = $this->actingAs($this->gerente)->get(route('reportes.contable.libro-fiscal', ['desde' => $hoy, 'hasta' => $hoy, 'formato' => 'pdf']));
        $respLibroPdf->assertStatus(200);
        $respLibroPdf->assertHeader('Content-Type', 'application/pdf');

        // 7. Acceso denegado a rol no autorizado (mesero)
        $resp403 = $this->actingAs($this->mesero)->get(route('reportes.contable.siigo', ['desde' => $hoy, 'hasta' => $hoy]));
        $resp403->assertStatus(403);
    }
}
