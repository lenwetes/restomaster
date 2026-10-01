<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\FacturaElectronica;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\Dian\DianPosElectronicoService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DianPosElectronicoTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $cajero;

    protected User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sucursal DIAN Test']);

        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'cajero.dian@test.local',
        ]);
        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Mesero DIAN',
            'email' => 'mesero.dian@test.local',
            'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja DIAN',
            'codigo' => 'CAJ-DIAN',
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

    protected function crearPedidoParaCobro(): Pedido
    {
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 12,
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'ocupada',
            'mesero_id' => $this->mesero->id,
        ]);

        $producto = Producto::create([
            'nombre' => 'Sushi Master Roll',
            'slug' => 'sushi-master-roll',
            'precio' => 108000,
            'costo' => 35000,
            'area_cocina' => 'cocina',
            'activo' => true,
        ]);

        return app(PedidoService::class)->crearPedido([
            'tipo' => 'mesa',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'mesero_id' => $this->mesero->id,
            'usuario_id' => $this->mesero->id,
            'subtotal' => 108000,
            'total' => 108000,
        ], [
            [
                'producto_id' => $producto->id,
                'nombre_producto' => $producto->nombre,
                'cantidad' => 1,
                'precio_unitario' => 108000,
                'subtotal' => 108000,
                'area_cocina' => 'cocina',
                'estado_cocina' => 'entregado',
            ],
        ], $this->mesero);
    }

    public function test_servicio_dian_calcula_cufe_sha384_valido_y_emite_pos(): void
    {
        $pedido = $this->crearPedidoParaCobro();
        $service = app(DianPosElectronicoService::class);

        $factura = $service->emitirPosElectronico($pedido);

        $this->assertInstanceOf(FacturaElectronica::class, $factura);
        $this->assertSame(96, strlen($factura->cufe), 'CUFE debe ser un hash SHA-384 de 96 caracteres hexadecimales.');
        $this->assertStringContainsString('POS-', $factura->numero_factura);
        $this->assertSame('emitida', $factura->estado);
        $this->assertStringContainsString('NumFac:', $factura->qr_cadena);
        $this->assertStringContainsString('CUFE:', $factura->qr_cadena);
        $this->assertNotNull($factura->qr_imagen_url);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $factura->qr_imagen_url);
    }

    public function test_cobrar_pedido_genera_factura_electronica_y_asocia_cufe_al_evento(): void
    {
        $this->actingAs($this->cajero);
        $pedido = $this->crearPedidoParaCobro();

        $pedidoCobrado = app(PedidoService::class)->cobrarPedido($pedido, 'efectivo', 108000.0);

        $this->assertSame('pagado', $pedidoCobrado->estado);

        // Verificamos persistencia en tabla facturas_electronicas
        $factura = FacturaElectronica::where('pedido_id', $pedido->id)->first();
        $this->assertNotNull($factura);
        $this->assertSame(96, strlen($factura->cufe));
        $this->assertSame($pedido->id, $factura->pedido_id);
        $this->assertSame((float) $pedido->total, (float) $factura->total);

        // Relación en el modelo Pedido
        $this->assertNotNull($pedidoCobrado->facturaElectronica);
        $this->assertSame($factura->cufe, $pedidoCobrado->facturaElectronica->cufe);
    }

    public function test_emision_es_idempotente(): void
    {
        $pedido = $this->crearPedidoParaCobro();
        $service = app(DianPosElectronicoService::class);

        $factura1 = $service->emitirPosElectronico($pedido);
        $factura2 = $service->emitirPosElectronico($pedido);

        $this->assertSame($factura1->id, $factura2->id);
        $this->assertSame($factura1->cufe, $factura2->cufe);
        $this->assertSame(1, FacturaElectronica::where('pedido_id', $pedido->id)->count());
    }
}
