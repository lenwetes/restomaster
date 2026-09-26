<?php

namespace Tests\Feature;

use App\Jobs\ImprimirComandaJob;
use App\Jobs\ImprimirReporteZJob;
use App\Jobs\ImprimirTicketVentaJob;
use App\Jobs\ImprimirTrabajoJob;
use App\Models\Insumo;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\InventarioService;
use App\Services\PedidoService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class AuditoriaLote8HigieneTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Test Sucursal',
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
    }

    public function test_r28_only_standard_docker_compose_exists(): void
    {
        $this->assertTrue(File::exists(base_path('docker-compose.yml')));
        if (File::exists(base_path('docker-compose.yaml'))) {
            // Espejo para despliegue en Coolify (commit 876792c)
            $this->assertStringContainsString('ESPEJO de docker-compose.yml', File::get(base_path('docker-compose.yaml')));
        }
        $this->assertFalse(File::exists(base_path('compose.yml')));
    }

    public function test_r29_print_jobs_consolidation(): void
    {
        $unifiedJob = new ImprimirTrabajoJob(1, 'comanda');
        $this->assertInstanceOf(ImprimirTrabajoJob::class, $unifiedJob);

        $comandaJob = new ImprimirComandaJob(1);
        $this->assertInstanceOf(ImprimirTrabajoJob::class, $comandaJob);
        $this->assertEquals('comanda', $comandaJob->tipo);

        $ticketJob = new ImprimirTicketVentaJob(1);
        $this->assertInstanceOf(ImprimirTrabajoJob::class, $ticketJob);
        $this->assertEquals('ticket_venta', $ticketJob->tipo);

        $reporteZJob = new ImprimirReporteZJob(1);
        $this->assertInstanceOf(ImprimirTrabajoJob::class, $reporteZJob);
        $this->assertEquals('reporte_z', $reporteZJob->tipo);
    }

    public function test_r33_inventario_bounds_and_validations(): void
    {
        $service = app(InventarioService::class);

        $insumo = Insumo::create([
            'nombre' => 'Salmón Premium',
            'codigo' => 'INS-SALMON-01',
            'categoria' => 'proteinas',
            'unidad_medida' => 'kg',
            'stock_actual' => 10.0,
            'stock_minimo' => 2.0,
            'costo_unitario' => 45000,
            'activo' => true,
        ]);

        // 1. Compra con cantidad <= 0 rechazada
        try {
            $service->registrarCompra($insumo->id, 0, 45000);
            $this->fail('Se esperaba InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('mayor a cero', $e->getMessage());
        }

        // 2. Compra con costo < 0 rechazada
        try {
            $service->registrarCompra($insumo->id, 5, -100);
            $this->fail('Se esperaba InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('no puede ser negativo', $e->getMessage());
        }

        // 3. Merma superior al stock disponible rechazada con DomainException
        try {
            $service->registrarMerma($insumo->id, 15.0, 'Pérdida cadena frío');
            $this->fail('Se esperaba DomainException');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no puede superar el stock actual', $e->getMessage());
        }

        // 4. Ajuste con stock negativo rechazado
        try {
            $service->registrarAjuste($insumo->id, -5.0, 'Ajuste erróneo');
            $this->fail('Se esperaba InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('no puede ser negativo', $e->getMessage());
        }
    }

    public function test_r34_order_codes_uniqueness_and_format(): void
    {
        $pedidoService = app(PedidoService::class);
        $deliveryService = app(DeliveryService::class);

        // Crear dos pedidos POS
        $p1 = $pedidoService->crearPedido([
            'tipo' => 'mesa',
            'sucursal_id' => $this->sucursal->id,
        ], []);

        $p2 = $pedidoService->crearPedido([
            'tipo' => 'mesa',
            'sucursal_id' => $this->sucursal->id,
        ], []);

        $this->assertNotEquals($p1->codigo, $p2->codigo);
        $this->assertStringStartsWith('ORD-', $p1->codigo);
        $this->assertStringStartsWith('ORD-', $p2->codigo);

        // Crear pedido delivery
        $d1 = $deliveryService->crearPedidoDelivery([
            'nombre_cliente' => 'Cliente Test',
            'telefono_cliente' => '3001234567',
            'direccion_delivery' => 'Calle 50 # 20-10',
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->assertStringStartsWith('DLV-', $d1->codigo);
    }
}
