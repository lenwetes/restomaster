<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\ConfiguracionService;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A2 (dead code→server-side): en el checkout público `web_delivery` el campo
 * `costo_envio` lo envía el cliente (`pedido-publico.blade:26` `public float
 * $costoEnvio`, `:187` `'costo_envio' => $this->costoEnvio`) y el servidor lo
 * toma tal cual (`DeliveryService::crearPedidoDelivery:50`). Un cliente puede
 * mandar `costo_envio => 0` y pagar delivery gratis.
 *
 * Fix (server-side, tarifa fija): cuando `canal_origen === 'web_delivery'`
 * (canal web DELIVERY público) el servidor IGNORA el `costo_envio` del cliente
 * e impone la tarifa fija `costo_envio_base` de ConfiguracionService
 * (ej.: 8000.0). Los canales INTERNOS (`pos`/terminal/cajero, que fijan el
 * costo del envío legítimamente) NO se tocan.
 */
class RemediacionTarifaEnvioServidorTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryService $deliveryService;

    private ConfiguracionService $configService;

    private TurnoCaja $turno;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin', 'descripcion' => 'Admin']);
        $rolDelivery = Role::create(['nombre' => 'Delivery', 'slug' => 'delivery', 'descripcion' => 'Delivery']);

        $sucursal = Sucursal::create(['nombre' => 'Sucursal', 'codigo' => 'S-01', 'direccion' => 'Cra 1', 'activa' => true]);
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin+tarifa@restomaster.com',
            'role_id' => $rolAdmin->id,
            'sucursal_id' => $sucursal->id,
            'password' => bcrypt('password-ok'),
            'activo' => true,
            'email_verified_at' => now(),
        ]);

        $caja = Caja::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja', 'codigo' => 'CAJA-01', 'activa' => true]);
        $this->turno = app(CajaService::class)->abrirTurno($caja, $this->admin, 100000.0, 'Apertura');

        // Tarifa fija oficial server-side: 8000 COP
        $this->configService = app(ConfiguracionService::class);
        $this->configService->guardar('general', 'costo_envio_base', 8000.0);
        config(['general.costo_envio_base' => 8000.0]);

        $this->deliveryService = app(DeliveryService::class);
    }

    public function test_canal_web_delivery_publico_ignora_costo_envio_del_cliente_e_impone_tarifa_fija_8000(): void
    {
        // Cliente manipula costo_envio → 0 (gratis) en el checkout público
        $pedido = $this->deliveryService->crearPedidoDelivery([
            'canal_origen' => 'web_delivery',
            'nombre_cliente' => 'Cliente Delivery',
            'telefono_cliente' => '+57 300 000 0000',
            'direccion_delivery' => 'Cra 10 #20-30',
            'costo_envio' => 0,
            'turno_caja_id' => $this->turno->id,
            'sucursal_id' => $this->turno->sucursal_id,
        ]);

        $this->assertSame(8000.0, (float) $pedido->costo_envio);
        $this->assertSame(8000.0, (float) $pedido->total);
    }

    public function test_canal_interno_pos_preserva_costo_envio_legitimo_del_terminal(): void
    {
        // Canal interno POS: el costo del envío lo fija el terminal/cajero
        $pedido = $this->deliveryService->crearPedidoDelivery([
            'canal_origen' => 'pos',
            'nombre_cliente' => 'Cliente Interno',
            'telefono_cliente' => '+57 300 111 2222',
            'direccion_delivery' => 'Cra 5 #13',
            'costo_envio' => 5000.0,
            'turno_caja_id' => $this->turno->id,
            'sucursal_id' => $this->turno->sucursal_id,
        ]);

        $this->assertSame(5000.0, (float) $pedido->costo_envio);
        $this->assertSame(5000.0, (float) $pedido->total);
    }
}
