<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Mesa;
use App\Models\PagoPasarela;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\Pasarela\PasarelaPagoService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasarelaPagoTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $cajero;

    protected User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sucursal Pasarela']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'cajero.pasarela@test.local',
        ]);
        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'mesero.pasarela@test.local',
            'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Pasarela',
            'codigo' => 'CAJ-PAS',
            'tipo' => 'principal',
            'activa' => true,
        ]);

        TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->cajero->id,
            'apertura_en' => now(),
            'monto_inicial' => 100000,
            'estado' => 'abierto',
        ]);
    }

    protected function crearPedido(): Pedido
    {
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 5,
            'capacidad' => 2,
            'zona' => 'salon',
            'estado' => 'ocupada',
            'mesero_id' => $this->mesero->id,
        ]);

        $producto = Producto::create([
            'nombre' => 'Sushi Roll Wompi',
            'slug' => 'sushi-roll-wompi',
            'precio' => 75000,
            'costo' => 25000,
            'area_cocina' => 'cocina',
            'activo' => true,
        ]);

        return app(PedidoService::class)->crearPedido([
            'tipo' => 'mesa',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'mesero_id' => $this->mesero->id,
            'usuario_id' => $this->mesero->id,
            'subtotal' => 75000,
            'total' => 75000,
        ], [
            [
                'producto_id' => $producto->id,
                'nombre_producto' => $producto->nombre,
                'cantidad' => 1,
                'precio_unitario' => 75000,
                'subtotal' => 75000,
                'area_cocina' => 'cocina',
                'estado_cocina' => 'entregado',
            ],
        ], $this->mesero);
    }

    public function test_generar_qr_wompi_calcula_firma_de_integridad_y_qr(): void
    {
        $pedido = $this->crearPedido();
        $service = app(PasarelaPagoService::class);

        $pago = $service->generarQrCobroMesa($pedido, 'wompi');

        $this->assertInstanceOf(PagoPasarela::class, $pago);
        $this->assertSame('wompi', $pago->proveedor);
        $this->assertSame('pendiente', $pago->estado);
        $this->assertSame(75000.0, (float) $pago->monto);
        $this->assertStringContainsString('https://checkout.wompi.co/p/?', $pago->checkout_url);
        $this->assertNotEmpty($pago->firma_integridad);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $pago->qr_imagen);
    }

    public function test_generar_qr_bold_crea_link_de_pago(): void
    {
        $pedido = $this->crearPedido();
        $service = app(PasarelaPagoService::class);

        $pago = $service->generarQrCobroMesa($pedido, 'bold');

        $this->assertSame('bold', $pago->proveedor);
        $this->assertSame('pendiente', $pago->estado);
        $this->assertStringContainsString('https://checkout.bold.co/payment/', $pago->checkout_url);
    }

    public function test_enviar_cobro_a_datafono_inteligente(): void
    {
        $pedido = $this->crearPedido();
        $service = app(PasarelaPagoService::class);

        $pago = $service->enviarCobroDatafono($pedido, 'TERM-0099', 'bold');

        $this->assertSame('datafono_smart', $pago->metodo_pasarela);
        $this->assertSame('TERM-0099', $pago->terminal_id);
        $this->assertSame('pendiente', $pago->estado);
    }

    public function test_webhook_wompi_aprobado_cobra_pedido_automaticamente(): void
    {
        $pedido = $this->crearPedido();
        $service = app(PasarelaPagoService::class);
        $pago = $service->generarQrCobroMesa($pedido, 'wompi');

        $payload = [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => 'TRX-WOMPI-999',
                    'reference' => $pago->referencia,
                    'status' => 'APPROVED',
                    'amount_in_cents' => 7500000,
                    'currency' => 'COP',
                ],
            ],
            'sent_at' => now()->toIso8601String(),
        ];

        $response = $this->postJson('/api/webhooks/pasarela/wompi', $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'referencia' => $pago->referencia,
                'estado' => 'aprobado',
            ]);

        $pago->refresh();
        $this->assertSame('aprobado', $pago->estado);
        $this->assertSame('TRX-WOMPI-999', $pago->transaccion_id);

        $pedido->refresh();
        $this->assertSame('pagado', $pedido->estado);
        $this->assertSame('pasarela_wompi', $pedido->metodo_pago);
    }
}
