<?php

namespace Tests\Feature;

use App\Jobs\ImprimirTicketVentaJob;
use App\Models\Impresora;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TrabajoImpresion;
use App\Models\User;
use App\Services\ImpresionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImpresorasLocalesUsbTest extends TestCase
{
    use RefreshDatabase;

    private ImpresionService $impresionService;

    private Sucursal $sucursal;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->impresionService = app(ImpresionService::class);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'is_active' => true,
        ]);
    }

    public function test_creacion_y_persistencia_impresora_usb_local(): void
    {
        $impresora = Impresora::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Térmica USB Caja',
            'tipo_conexion' => 'usb_local',
            'driver_nombre' => 'POS-80',
            'ip_address' => '127.0.0.1',
            'puerto' => 9100,
            'area' => 'caja',
            'ancho_columnas' => 48,
            'copias' => 1,
            'activa' => true,
        ]);

        $this->assertDatabaseHas('impresoras', [
            'id' => $impresora->id,
            'tipo_conexion' => 'usb_local',
            'driver_nombre' => 'POS-80',
            'area' => 'caja',
        ]);
    }

    public function test_obtener_impresoras_instaladas_so_retorna_array(): void
    {
        $impresorasSO = $this->impresionService->obtenerImpresorasInstaladasSO();
        $this->assertIsArray($impresorasSO);
    }

    public function test_probar_conexion_impresora_usb_local(): void
    {
        $impresora = Impresora::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Térmica USB Test',
            'tipo_conexion' => 'usb_local',
            'driver_nombre' => 'Microsoft Print to PDF',
            'ip_address' => '127.0.0.1',
            'puerto' => 9100,
            'area' => 'caja',
            'ancho_columnas' => 48,
            'copias' => 1,
            'activa' => true,
        ]);

        $resultado = $impresora->probarConexion();

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('ok', $resultado);
        $this->assertArrayHasKey('mensaje', $resultado);
        $this->assertArrayHasKey('latencia_ms', $resultado);
    }

    public function test_encolar_trabajo_impresion_a_impresora_usb(): void
    {
        Queue::fake();

        $impresora = Impresora::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Térmica USB Despacho',
            'tipo_conexion' => 'usb_local',
            'driver_nombre' => 'POS-80',
            'ip_address' => '127.0.0.1',
            'puerto' => 9100,
            'area' => 'caja',
            'ancho_columnas' => 48,
            'copias' => 1,
            'activa' => true,
        ]);

        $trabajo = $this->impresionService->encolarTrabajo(
            $impresora,
            'ticket_venta',
            'TICK-TEST-001',
            "================================\n   SUSHIXPRESS TICKET USB PRUEBA\n================================\n",
            1
        );

        $this->assertInstanceOf(TrabajoImpresion::class, $trabajo);
        $this->assertDatabaseHas('trabajos_impresion', [
            'id' => $trabajo->id,
            'impresora_id' => $impresora->id,
            'tipo' => 'ticket_venta',
            'estado' => 'pendiente',
        ]);

        Queue::assertPushed(ImprimirTicketVentaJob::class);
    }
}
