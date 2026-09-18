<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Tests RED — Remediación lote L2 (R5, R6) y L5 (R7).
 *
 * Hallazgos: docs/auditoria/remediacion-seguridad-rendimiento-2026-09-15.md
 * Requisito: deben FALLAR en HEAD actual y pasar tras el fix.
 */
class RemediacionPosCocinaTest extends TestCase
{
    use RefreshDatabase;

    private User $mesero;

    private Mesa $mesa;

    private Producto $productoA;

    private Producto $productoB;

    protected function setUp(): void
    {
        parent::setUp();

        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero', 'descripcion' => 'Mesero']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero', 'descripcion' => 'Cajero']);

        $sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->mesero = User::factory()->create([
            'name' => 'Carlos Mesero',
            'email' => 'mesero@restomaster.com',
            'role_id' => $roleMesero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        // Se usa un cajero (rol autorizado) para abrir el turno de caja
        $cajero = User::factory()->create([
            'name' => 'Cajero Apertura',
            'email' => 'cajero@restomaster.com',
            'role_id' => $roleCajero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $sucursal->id,
            'numero' => 4,
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'ocupada',
            'activo' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Parrilla',
            'slug' => 'parrilla',
            'icono' => '🥩',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->productoA = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Ribeye 400g',
            'slug' => 'ribeye-400g',
            'precio' => 85000,
            'area_cocina' => 'cocina',
            'activo' => true,
        ]);

        $this->productoB = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Costillas BBQ',
            'slug' => 'costillas-bbq',
            'precio' => 65000,
            'area_cocina' => 'cocina',
            'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJA-R7',
            'activa' => true,
        ]);

        // El cajero (no el mesero) es quien abre el turno de caja
        app(CajaService::class)->abrirTurno($caja, $cajero, 100000.00, 'Apertura');
    }

    private function crearPedidoActivoConItemProductoA(): Pedido
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-TEST-R5-001',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'mesa_id' => $this->mesa->id,
            'usuario_id' => $this->mesero->id,
            'subtotal' => 85000,
            'total' => 85000,
        ]);

        $pedido->items()->create([
            'producto_id' => $this->productoA->id,
            'nombre_producto' => $this->productoA->nombre,
            'cantidad' => 1,
            'precio_unitario' => $this->productoA->precio,
            'subtotal' => $this->productoA->precio,
            'area_cocina' => 'cocina',
            'estado_cocina' => 'en_preparacion',
        ]);

        return $pedido;
    }

    public function test_r5_enviar_a_cocina_con_pedido_activo_no_duplica_items(): void
    {
        // Pedido activo ya existente en la mesa (1 item del producto A)
        $pedidoActivo = $this->crearPedidoActivoConItemProductoA();

        // El terminal carga la comanda activa al carrito al seleccionar la mesa
        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id);

        $carrito = $component->get('carrito');
        $this->assertArrayHasKey($this->productoA->id, $carrito);

        // El mesero agrega 1 item nuevo (producto B)
        $component->call('agregarProducto', $this->productoB->id);

        $component->call('enviarACocina');

        // NO debe haberse creado un segundo pedido para la misma mesa
        $totalPedidos = Pedido::where('mesa_id', $this->mesa->id)->count();
        $this->assertEquals(1, $totalPedidos, "Se crearon {$totalPedidos} pedidos en vez de 1 (duplicación de comanda).");

        // El pedido único debe haber reutilizado el pedido activo existente
        $pedido = Pedido::where('mesa_id', $this->mesa->id)->first();

        $this->assertEquals($pedidoActivo->id, $pedido->id, 'Debe reutilizarse el pedido activo, no crearse uno nuevo.');

        // Con los 2 items (A original + B nuevo), sin duplicados
        $items = $pedido->items()->get();

        $this->assertCount(2, $items, 'El pedido debe tener exactamente 2 ítems (A + B), sin duplicados.');

        $itemsA = $items->where('producto_id', $this->productoA->id);
        $itemsB = $items->where('producto_id', $this->productoB->id);

        $this->assertCount(1, $itemsA, 'El ítem A fue duplicado.');
        $this->assertCount(1, $itemsB, 'El ítem B nuevo no se agregó.');
    }

    public function test_r6_procesar_cobro_incluye_items_agregados_al_carrito(): void
    {
        // Pedido activo con 1 item del producto A (85.000), ya servido para habilitar el cobro
        $pedidoActivo = $this->crearPedidoActivoConItemProductoA();
        $pedidoActivo->items()->update(['estado_cocina' => 'entregado']);
        $pedidoActivo->update(['estado' => 'entregado']);

        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id);

        // El mesero agrega producto B (65.000) => carrito = 150.000
        $component->call('agregarProducto', $this->productoB->id);

        $this->assertEquals(150000.0, (float) $component->get('total'));

        $component
            ->call('abrirModalCobro')
            ->set('metodoPago', 'efectivo')
            ->set('montoPagado', 150000)
            ->call('procesarCobro');

        // El cobro debe incluir AMBOS items y total completo
        $pedido = Pedido::find($pedidoActivo->id);
        $this->assertEquals('pagado', $pedido->estado);
        $this->assertEquals(150000.0, (float) $pedido->total, 'El cobro ignoró los items agregados al carrito.');
        $this->assertSame(2, $pedido->items()->count(), 'El pedido cobrado no contiene todos los items del carrito.');
        $this->assertEquals(0.0, (float) $pedido->cambio);
    }

    public function test_r7_cobro_con_tarjeta_no_conserva_monto_pagado_residual_de_efectivo(): void
    {
        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id)
            ->call('agregarProducto', $this->productoA->id);

        $total = (float) $component->get('total');
        $this->assertEquals(85000.0, $total);

        $component
            ->call('abrirModalCobro')
            ->set('metodoPago', 'efectivo')
            ->set('montoPagado', 100000);   // el cajero escribe 100.000 en efectivo

        // Cambia de opinión y paga con tarjeta: el monto debe resetearse al total automáticamente
        $component->set('metodoPago', 'tarjeta');

        $component->call('procesarCobro');

        $this->assertNotNull($component->get('pedidoCompletado'));

        $pedido = Pedido::where('mesa_id', $this->mesa->id)->first();
        $this->assertEquals('tarjeta', $pedido->metodo_pago);
        $this->assertEquals(85000.0, (float) $pedido->total);
        $this->assertEquals(85000.0, (float) $pedido->monto_pagado, 'Cobro con tarjeta registró monto residual de efectivo.');
        $this->assertEquals(0.0, (float) $pedido->cambio, 'Cobro con tarjeta no debe generar cambio.');
    }
}
