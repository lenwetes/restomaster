<?php

namespace Tests\Feature;

use App\Jobs\ImprimirTicketVentaJob;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Impresora;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TrabajoImpresion;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\ImpresionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase6RobustezImpresionTest extends TestCase
{
    use RefreshDatabase;

    private ImpresionService $impresionService;

    private Sucursal $sucursal;

    private User $admin;

    private User $mesero;

    private Mesa $mesa;

    private Impresora $impresoraSushi;

    private Impresora $impresoraCalientes;

    private Impresora $impresoraCaja;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Chef',
            'email' => 'admin@sushixpress.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleAdmin->id,
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Carlos Mesero',
            'email' => 'mesero@sushixpress.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleMesero->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 4,
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => true,
        ]);

        // Crear impresoras térmicas virtuales para pruebas deterministas
        $this->impresoraSushi = Impresora::create([
            'nombre' => 'Térmica Barra Sushi (Simulador)',
            'tipo_conexion' => 'virtual_simulador',
            'area' => 'sushi',
            'ancho_columnas' => 48,
            'copias' => 1,
            'activa' => true,
        ]);

        $this->impresoraCalientes = Impresora::create([
            'nombre' => 'Térmica Calientes & Wok (Simulador)',
            'tipo_conexion' => 'virtual_simulador',
            'area' => 'calientes',
            'ancho_columnas' => 48,
            'copias' => 1,
            'activa' => true,
        ]);

        $this->impresoraCaja = Impresora::create([
            'nombre' => 'Térmica Caja Principal (Simulador)',
            'tipo_conexion' => 'virtual_simulador',
            'area' => 'caja_principal',
            'ancho_columnas' => 48,
            'copias' => 1,
            'activa' => true,
        ]);

        $this->impresionService = app(ImpresionService::class);
    }

    public function test_impresoras_crud_y_scopes(): void
    {
        $this->assertCount(3, Impresora::activas()->get());

        $sushiPrinters = Impresora::porArea('sushi')->get();
        $this->assertTrue($sushiPrinters->contains('id', $this->impresoraSushi->id));
        $this->assertFalse($sushiPrinters->contains('id', $this->impresoraCalientes->id));

        $inactiva = Impresora::create([
            'nombre' => 'Impresora Averiada',
            'tipo_conexion' => 'red_ip',
            'ip_address' => '192.168.1.99',
            'area' => 'sushi',
            'activa' => false,
        ]);

        $this->assertFalse(Impresora::activas()->get()->contains('id', $inactiva->id));

        // Probar socket en virtual devuelve true
        $socketRes = $this->impresoraSushi->probarConexionSocket();
        $this->assertTrue($socketRes['ok']);
    }

    public function test_formateo_comanda_y_despacho_por_estaciones(): void
    {
        $catSushi = Categoria::create(['nombre' => 'Rolls', 'slug' => 'rolls', 'activo' => true]);
        $catWok = Categoria::create(['nombre' => 'Wok', 'slug' => 'wok', 'activo' => true]);

        $p1 = Producto::create([
            'categoria_id' => $catSushi->id,
            'nombre' => 'Acevichado Roll',
            'slug' => 'acevichado-roll',
            'precio' => 38000,
            'costo' => 14000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $p2 = Producto::create([
            'categoria_id' => $catWok->id,
            'nombre' => 'Yakisoba de Pollo',
            'slug' => 'yakisoba-pollo',
            'precio' => 32000,
            'costo' => 11000,
            'area_cocina' => 'calientes',
            'activo' => true,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'ORD-TEST-001',
            'tipo' => 'mesa',
            'estado' => 'creado',
            'mesa_id' => $this->mesa->id,
            'usuario_id' => $this->mesero->id,
            'subtotal' => 70000,
            'total' => 70000,
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $p1->id,
            'nombre_producto' => $p1->nombre,
            'cantidad' => 2,
            'precio_unitario' => 38000,
            'subtotal' => 76000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
            'notas' => 'Sin wasabi por favor',
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $p2->id,
            'nombre_producto' => $p2->nombre,
            'cantidad' => 1,
            'precio_unitario' => 32000,
            'subtotal' => 32000,
            'area_cocina' => 'calientes',
            'estado_cocina' => 'pendiente',
        ]);

        $trabajos = $this->impresionService->despacharComandaCocina($pedido, $this->mesero);

        // Se deben crear 2 comandas separadas para cada estación de cocina
        $this->assertCount(2, $trabajos);

        $trabajoSushi = collect($trabajos)->firstWhere('area', 'sushi');
        $trabajoCalientes = collect($trabajos)->firstWhere('area', 'calientes');

        $this->assertNotNull($trabajoSushi);
        $this->assertNotNull($trabajoCalientes);

        $this->assertStringContainsString('COMANDA ÁREA: SUSHI', $trabajoSushi->contenido_texto);
        $this->assertStringContainsString('ACEVICHADO ROLL', $trabajoSushi->contenido_texto);
        $this->assertStringContainsString('SIN WASABI POR FAVOR', $trabajoSushi->contenido_texto);
        $this->assertStringNotContainsString('YAKISOBA DE POLLO', $trabajoSushi->contenido_texto);

        $this->assertStringContainsString('COMANDA ÁREA: CALIENTES', $trabajoCalientes->contenido_texto);
        $this->assertStringContainsString('YAKISOBA DE POLLO', $trabajoCalientes->contenido_texto);
        $this->assertStringNotContainsString('ACEVICHADO ROLL', $trabajoCalientes->contenido_texto);

        // Contenido raw debe contener comando de corte ESC/POS (GS V: 0x1D 0x56)
        $this->assertStringContainsString("\x1D\x56", $trabajoSushi->contenido_raw);
    }

    public function test_formateo_ticket_venta_48_columnas_con_puntos_y_resolucion(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Valentina Restrepo',
            'documento' => '1020304050',
            'telefono' => '3001234567',
            'email' => 'valentina@ejemplo.com',
            'tier' => 'oro',
            'puntos_fidelidad' => 250,
            'activo' => true,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'ORD-FACT-778',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'mesa_id' => $this->mesa->id,
            'cliente_id' => $cliente->id,
            'usuario_id' => $this->mesero->id,
            'subtotal' => 100000,
            'descuento' => 10000,
            'total' => 90000,
            'metodo_pago' => 'tarjeta',
            'monto_pagado' => 90000,
            'cambio' => 0,
            'pagado_en' => now(),
        ]);

        $cat = Categoria::create(['nombre' => 'Bebidas', 'slug' => 'bebidas', 'activo' => true]);
        $prod = Producto::create(['categoria_id' => $cat->id, 'nombre' => 'Sake Junmai', 'slug' => 'sake', 'precio' => 45000, 'costo' => 20000, 'activo' => true]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $prod->id,
            'nombre_producto' => $prod->nombre,
            'cantidad' => 2,
            'precio_unitario' => 45000,
            'subtotal' => 90000,
            'area_cocina' => 'barra',
        ]);

        $trabajo = $this->impresionService->despacharTicketVenta($pedido, $this->admin);

        $this->assertEquals('ticket_venta', $trabajo->tipo);
        $this->assertStringContainsString('RESTOMASTER COLOMBIA', $trabajo->contenido_texto);
        $this->assertStringContainsString('ORD-FACT-778', $trabajo->contenido_texto);
        $this->assertStringContainsString('Valentina Restrepo', $trabajo->contenido_texto);
        $this->assertStringContainsString('Sake Junmai', $trabajo->contenido_texto);
        $this->assertStringContainsString('TOTAL A PAGAR:', $trabajo->contenido_texto);
        $this->assertStringContainsString('90,000 COP', $trabajo->contenido_texto);
        $this->assertStringContainsString('PUNTOS CLUB:', $trabajo->contenido_texto);
        $this->assertStringContainsString('250 pts (oro)', $trabajo->contenido_texto);
        $this->assertStringContainsString('DIAN', $trabajo->contenido_texto);
    }

    public function test_formateo_reporte_z_arqueo_de_caja(): void
    {
        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal Salón',
            'codigo' => 'CAJA-01',
            'activa' => true,
        ]);

        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->admin->id,
            'apertura_en' => now()->subHours(8),
            'cierre_en' => now(),
            'monto_inicial' => 200000,
            'total_ventas_efectivo' => 350000,
            'total_ventas_tarjeta' => 180000,
            'total_ventas_transferencia' => 0,
            'total_egresos' => 50000,
            'total_retiros' => 0,
            'monto_esperado_efectivo' => 500000,
            'monto_real_efectivo' => 500000,
            'diferencia' => 0,
            'estado' => 'cerrado',
            'cerrado_por_user_id' => $this->admin->id,
        ]);

        MovimientoCaja::create([
            'turno_caja_id' => $turno->id,
            'user_id' => $this->admin->id,
            'tipo' => 'egreso',
            'concepto' => 'Compra Hielo Emergencia',
            'monto' => 50000,
            'metodo_pago' => 'efectivo',
        ]);

        $trabajo = $this->impresionService->despacharReporteZ($turno, $this->admin);

        $this->assertEquals('reporte_z', $trabajo->tipo);
        $this->assertStringContainsString('REPORTE Z', $trabajo->contenido_texto);
        $this->assertStringContainsString('Caja Principal Salón', $trabajo->contenido_texto);
        $this->assertStringContainsString('FONDO INICIAL:', $trabajo->contenido_texto);
        $this->assertStringContainsString('TOTAL VENTAS (+):', $trabajo->contenido_texto);
        $this->assertStringContainsString('TOTAL EGRESOS (-):', $trabajo->contenido_texto);
        $this->assertStringContainsString('SALDO CALCULADO EN SISTEMA:', $trabajo->contenido_texto);
    }

    public function test_ejecucion_de_jobs_en_cola_y_actualizacion_de_estado(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-JOB-999',
            'tipo' => 'para_llevar',
            'estado' => 'creado',
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        $trabajo = TrabajoImpresion::create([
            'tipo' => 'ticket_venta',
            'pedido_id' => $pedido->id,
            'impresora_id' => $this->impresoraCaja->id,
            'area' => 'caja',
            'contenido_texto' => 'TEXTO TICKET PRUEBA',
            'contenido_raw' => 'RAW TICKET PRUEBA',
            'estado' => 'pendiente',
            'usuario_id' => $this->admin->id,
        ]);

        $job = new ImprimirTicketVentaJob($trabajo->id);
        $job->handle($this->impresionService);

        $trabajo->refresh();
        $this->assertEquals('enviado', $trabajo->estado);
        $this->assertNotNull($trabajo->impreso_en);
        $this->assertEquals(1, $trabajo->intentos);
    }

    public function test_reimpresion_y_registro_en_auditoria(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-REPRINT-1',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'subtotal' => 40000,
            'total' => 40000,
        ]);

        $trabajo = TrabajoImpresion::create([
            'tipo' => 'ticket_venta',
            'pedido_id' => $pedido->id,
            'impresora_id' => $this->impresoraCaja->id,
            'area' => 'caja',
            'contenido_texto' => 'ORIGINAL TICKET',
            'contenido_raw' => 'RAW TICKET',
            'estado' => 'completado',
            'usuario_id' => $this->mesero->id,
            'veces_reimpreso' => 0,
        ]);

        $resultado = $this->impresionService->reimprimir($trabajo, $this->admin);

        $this->assertInstanceOf(TrabajoImpresion::class, $resultado);
        $trabajo->refresh();

        $this->assertEquals(1, $trabajo->veces_reimpreso);
        $this->assertEquals($this->admin->id, $trabajo->reimpreso_por_id);

        // Validar registro en tabla auditorias
        $this->assertDatabaseHas('auditorias', [
            'entidad' => 'trabajo_impresion',
            'accion' => 'impresion.reimpreso',
            'user_id' => $this->admin->id,
            'entidad_id' => $trabajo->id,
        ]);
    }

    public function test_comando_artisan_backup_crea_archivo_sql(): void
    {
        $exitCode = $this->artisan('sushixpress:backup');
        $exitCode->assertExitCode(0);

        $backupPath = storage_path('app/backups');
        $this->assertTrue(File::exists($backupPath));

        $files = File::files($backupPath);
        $this->assertNotEmpty($files);

        $latestBackup = end($files);
        $this->assertStringEndsWith('.sql', $latestBackup->getFilename());
        $this->assertGreaterThan(0, $latestBackup->getSize());
    }

    public function test_comando_artisan_healthcheck_verifica_componentes(): void
    {
        $exitCode = $this->artisan('sushixpress:health');
        $exitCode->assertExitCode(0);
    }

    public function test_pantalla_livewire_imp01_gestion_impresion_y_preview(): void
    {
        $response = $this->actingAs($this->admin)->get('/impresion');
        $response->assertStatus(200);
        $response->assertSee('Servidor de Impresión', false);
        $response->assertSee('Térmica Barra Sushi (Simulador)', false);
        $response->assertSee('Térmica Caja Principal (Simulador)', false);

        // Probar interacción con Livewire Volt
        Volt::test('impresion.index')
            ->assertSee('Servidor de Impresión', false)
            ->call('probarImpresora', $this->impresoraSushi->id)
            ->assertHasNoErrors();
    }

    public function test_formatear_ticket_venta_cliente_ocasional_sin_telefono_ni_documento(): void
    {
        $impresionService = app(ImpresionService::class);

        $clienteOcasional = Cliente::create([
            'nombre' => 'Comensal Sin Telefono',
            'telefono' => null,
            'documento' => null,
            'tier' => Cliente::TIER_OCASIONAL,
            'puntos_fidelidad' => 0,
            'activo' => true,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'PED-TEST-001',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $this->mesa->id,
            'usuario_id' => $this->admin->id,
            'cliente_id' => $clienteOcasional->id,
            'nombre_cliente' => $clienteOcasional->nombre,
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'metodo_pago' => 'efectivo',
            'subtotal' => 25000,
            'total' => 25000,
            'monto_pagado' => 30000,
            'cambio' => 5000,
            'pagado_en' => now(),
        ]);

        $ticket = $impresionService->formatearTicketVentaTexto($pedido);

        $this->assertNotEmpty($ticket);
        $this->assertStringContainsString('Comensal Sin Telefono', $ticket);
        $this->assertStringContainsString('Consumidor Final', $ticket);
        $this->assertStringContainsString('TOTAL A PAGAR:', $ticket);
    }
}
