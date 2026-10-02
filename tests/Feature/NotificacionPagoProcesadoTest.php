<?php

namespace Tests\Feature;

use App\Events\PagoProcesadoPorCaja;
use App\Models\Caja;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Fase 8.2 — Notificación al mesero cuando caja procesa el pago.
 */
class NotificacionPagoProcesadoTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $cajero;

    protected User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Pago Test']);

        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'cajero.pago@test.local',
        ]);
        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id, 'sucursal_id' => $this->sucursal->id, 'name' => 'Mesero Pago',
            'email' => 'mesero.pago@test.local', 'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id, 'nombre' => 'Caja Principal', 'codigo' => 'CAJ-01',
            'tipo' => 'principal', 'activa' => true,
        ]);
        TurnoCaja::create([
            'caja_id' => $caja->id, 'user_id' => $this->cajero->id,
            'apertura_en' => now(), 'monto_inicial' => 100000, 'estado' => 'abierto',
        ]);
    }

    protected function crearPedidoCobrable(): Pedido
    {
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id, 'numero' => 7, 'capacidad' => 2,
            'zona' => 'salon', 'estado' => 'ocupada', 'mesero_id' => $this->mesero->id,
        ]);
        $producto = Producto::create([
            'nombre' => 'Roll Pago', 'slug' => 'roll-pago', 'precio' => 85000,
            'costo' => 30000, 'area_cocina' => 'cocina', 'activo' => true,
        ]);

        $pedido = app(PedidoService::class)->crearPedido([
            'tipo' => 'mesa',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'mesero_id' => $this->mesero->id,
            'usuario_id' => $this->mesero->id,
            'subtotal' => 85000,
            'total' => 85000,
        ], [
            [
                'producto_id' => $producto->id,
                'nombre_producto' => $producto->nombre,
                'cantidad' => 1,
                'precio_unitario' => 85000,
                'subtotal' => 85000,
                'area_cocina' => 'cocina',
                'estado_cocina' => 'entregado',
            ],
        ], $this->mesero);

        return $pedido->fresh();
    }

    public function test_cobrar_dispara_evento_con_payload_completo(): void
    {
        Event::fake([PagoProcesadoPorCaja::class]);
        $this->actingAs($this->cajero);
        $pedido = $this->crearPedidoCobrable();

        app(PedidoService::class)->cobrarPedido($pedido, 'tarjeta', 85000.0);

        Event::assertDispatched(PagoProcesadoPorCaja::class, function (PagoProcesadoPorCaja $evento) use ($pedido) {
            return $evento->pedidoId === $pedido->id
                && $evento->meseroId === $this->mesero->id
                && str_contains($evento->mesa, '7')
                && $evento->total === 85000.0
                && $evento->metodoPago === 'tarjeta'
                && $evento->ticketUrl !== ''
                && str_contains($evento->mensaje, 'Caja procesó el pago');
        });
    }

    public function test_evento_emite_en_canal_privado_del_mesero(): void
    {
        $evento = new PagoProcesadoPorCaja(
            pedidoId: 1,
            meseroId: $this->mesero->id,
            mesa: 'Mesa 7',
            total: 85000.0,
            metodoPago: 'efectivo',
            ticketUrl: '/caja',
            mensaje: 'test'
        );

        $canales = $evento->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $canales);
        $this->assertEquals('private-mesero.'.$this->mesero->id, $canales->name);
    }

    public function test_sin_mesero_no_se_dispara_evento(): void
    {
        Event::fake([PagoProcesadoPorCaja::class]);
        $this->actingAs($this->cajero);
        $pedido = $this->crearPedidoCobrable();
        $pedido->forceFill(['mesero_id' => null, 'usuario_id' => null])->save();

        app(PedidoService::class)->cobrarPedido($pedido, 'efectivo', 85000.0);

        Event::assertNotDispatched(PagoProcesadoPorCaja::class);
    }

    public function test_alerta_mesero_escucha_pagos_procesados(): void
    {
        $response = $this->actingAs($this->mesero)->get(route('pos'));

        $response->assertOk();
        $response->assertSee('PagoProcesadoPorCaja', false);
    }
}
