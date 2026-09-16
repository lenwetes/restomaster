<?php

namespace Tests\Feature;

use App\Models\CuentaPorPagar;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\NotificacionService;
use App\Services\ReporteService;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class AuditoriaLote5BugsFuncionalesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $gerente;

    protected Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);
        $gerenteRole = Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Test',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001234567',
            'ciudad' => 'Medellin',
            'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admintest@restomaster.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test',
            'email' => 'gerentetest@restomaster.com',
            'password' => bcrypt('password123'),
            'role_id' => $gerenteRole->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    public function test_r15_cxp_wire_submit_handlers_function_correctly(): void
    {
        $this->actingAs($this->admin);

        // 1. Probar crearCuenta (antes guardarCuenta)
        Livewire::test('cxp.index')
            ->set('crearForm.proveedor_nombre', 'Distribuidora Carnes')
            ->set('crearForm.concepto', 'Compra lomo fino')
            ->set('crearForm.monto_total', 150000)
            ->set('crearForm.fecha_emision', now()->toDateString())
            ->call('crearCuenta')
            ->assertHasNoErrors();

        $cuenta = CuentaPorPagar::where('proveedor_nombre', 'Distribuidora Carnes')->first();
        $this->assertNotNull($cuenta);
        $this->assertEquals(150000, $cuenta->monto_total);

        // 2. Probar registrarPago (antes registrarAbono)
        Livewire::test('cxp.index')
            ->call('abrirPago', $cuenta->id)
            ->set('pagoForm.monto', 50000)
            ->set('pagoForm.metodo_pago', 'transferencia')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $cuenta->refresh();
        $this->assertEquals(100000, $cuenta->saldo_pendiente);
    }

    public function test_r16_admin_user_seeder_does_not_leak_passwords_to_output(): void
    {
        $seeder = new AdminUserSeeder;
        $output = '';

        $exitCode = Artisan::call('db:seed', [
            '--class' => AdminUserSeeder::class,
        ]);

        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);
        $this->assertStringNotContainsString('Contraseña asignada para usuarios demo:', $output);
    }

    public function test_r17_backup_database_command_streams_and_rotates(): void
    {
        $exitCode = Artisan::call('restomaster:backup', ['--tablas' => 'users,roles']);
        $this->assertEquals(0, $exitCode);

        $backupDir = storage_path('app/backups');
        $backups = File::glob("{$backupDir}/restomaster_backup_*.sql");
        $this->assertNotEmpty($backups);

        $latestBackup = end($backups);
        $content = File::get($latestBackup);
        $this->assertStringContainsString('RestoMaster POS Enterprise', $content);
        $this->assertStringContainsString('INSERT INTO "users"', $content);
    }

    public function test_r18_reporte_service_uses_sql_aggregations(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-TEST-R18',
            'sucursal_id' => $this->sucursal->id,
            'usuario_id' => $this->admin->id,
            'tipo' => 'mesa',
            'canal_origen' => 'pos',
            'estado' => 'pagado',
            'subtotal' => 100000,
            'total' => 100000,
            'pagado_en' => now(),
        ]);

        $producto = Producto::create([
            'nombre' => 'Corte Test',
            'slug' => 'corte-test',
            'precio' => 100000,
            'costo' => 40000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => $producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 100000,
            'subtotal' => 100000,
            'estado_cocina' => 'entregado',
        ]);

        $service = app(ReporteService::class);
        $hoy = now()->toDateString();

        $ventasPeriodo = $service->ventasPorPeriodo($hoy, $hoy);
        $this->assertNotEmpty($ventasPeriodo);
        $this->assertEquals(100000, $ventasPeriodo[0]['ventas']);

        $ventasProducto = $service->ventasPorProducto($hoy, $hoy);
        $this->assertNotEmpty($ventasProducto);
        $this->assertEquals('Corte Test', $ventasProducto[0]['producto']);
        $this->assertEquals(60000, $ventasProducto[0]['margen']);
    }

    public function test_r18_and_r31_reporte_export_controller_validations_and_csv_sanitization(): void
    {
        $this->actingAs($this->gerente);

        // 1. R18: Fecha mayor a 366 días debe dar 422
        $responseRange = $this->get(route('reportes.csv', [
            'reporte' => 'ventas',
            'desde' => '2024-01-01',
            'hasta' => '2026-01-01',
        ]));
        $responseRange->assertStatus(422);

        // 2. R31: Fórmula injection sanitizada
        $pedido = Pedido::create([
            'codigo' => 'ORD-CSV-TEST',
            'sucursal_id' => $this->sucursal->id,
            'usuario_id' => $this->gerente->id,
            'tipo' => 'mesa',
            'canal_origen' => 'pos',
            'estado' => 'pagado',
            'subtotal' => 50000,
            'total' => 50000,
            'pagado_en' => now(),
        ]);

        $productoMalicioso = Producto::create([
            'nombre' => '=cmd|"/C calc"!A0',
            'slug' => 'malicioso-test',
            'precio' => 50000,
            'costo' => 10000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $productoMalicioso->id,
            'nombre_producto' => $productoMalicioso->nombre,
            'cantidad' => 1,
            'precio_unitario' => 50000,
            'subtotal' => 50000,
            'estado_cocina' => 'entregado',
        ]);

        $hoy = now()->toDateString();
        $responseCsv = $this->get(route('reportes.csv', [
            'reporte' => 'ventas',
            'desde' => $hoy,
            'hasta' => $hoy,
        ]));

        $responseCsv->assertOk();
        $csvContent = $responseCsv->getContent();
        // Verificar que empiece con apóstrofe para neutralizar fórmula
        $this->assertStringContainsString("'=cmd", $csvContent);
    }

    public function test_r19_notificacion_service_no_cachea_colecciones_eloquent(): void
    {
        Cache::flush();
        $service = app(NotificacionService::class);

        $res1 = $service->obtenerResumen($this->admin);
        $this->assertArrayHasKey('total', $res1);
        $this->assertInstanceOf(Collection::class, $res1['pedidos_qr']);

        // No debe persistir colecciones Eloquent en caché: el store de BD las serializa
        // con serialize() y al hidratarlas pueden volverse objetos incompletos (LIVE-08).
        $cacheKey = "notif.resumen.{$this->admin->id}.{$this->admin->role_id}";
        $this->assertFalse(Cache::has($cacheKey));

        $res2 = $service->obtenerResumen($this->admin);
        $this->assertEquals($res1['total'], $res2['total']);
    }
}
