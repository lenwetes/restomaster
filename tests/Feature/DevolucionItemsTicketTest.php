<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\NotaCredito;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\InventarioService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DevolucionItemsTicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;
    protected User $gerente;
    protected Sucursal $sucursal;
    protected Caja $caja;
    protected TurnoCaja $turno;
    protected Producto $bebida;
    protected Insumo $insumoBebida;
    protected PedidoService $pedidoService;

    protected function setUp(): void
    {
        parent::setUp();

        $roleCajero = Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero']);
        $roleGerente = Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Principal',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->gerente = User::factory()->create([
            'role_id' => $roleGerente->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Barra',
            'codigo' => 'CAJA-BAR',
            'activa' => true,
        ]);

        $this->turno = app(CajaService::class)->abrirTurno($this->caja, $this->cajero, 100000.0, 'Turno Test');

        // Crear insumo y producto con receta
        $this->insumoBebida = Insumo::create([
            'nombre' => 'Botella Cerveza Corona 330ml',
            'codigo' => 'INS-CORONA',
            'unidad_medida' => 'unidad',
            'stock_actual' => 50.0,
            'stock_minimo' => 10.0,
            'costo_unitario' => 5000.0,
            'activo' => true,
        ]);

        $categoria = \App\Models\Categoria::create([
            'nombre' => 'Bebidas',
            'slug' => 'bebidas',
            'area_impresion' => 'barra',
            'activo' => true,
        ]);

        $this->bebida = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Cerveza Corona',
            'slug' => 'cerveza-corona',
            'precio' => 12000.0,
            'costo' => 5000.0,
            'area_cocina' => 'barra',
            'activo' => true,
        ]);

        Receta::create([
            'producto_id' => $this->bebida->id,
            'insumo_id' => $this->insumoBebida->id,
            'cantidad' => 1.0,
            'merma_esperada_pct' => 0.0,
        ]);

        $this->pedidoService = app(PedidoService::class);
    }

    public function test_devolucion_exitosa_ajusta_caja_inventario_y_contabilidad(): void
    {
        // 1. Crear y cobrar pedido con 2 cervezas
        $pedido = $this->pedidoService->crearPedido([
            'tipo' => 'barra',
            'sucursal_id' => $this->sucursal->id,
            'turno_caja_id' => $this->turno->id,
            'total' => 24000.0,
        ], [
            [
                'producto_id' => $this->bebida->id,
                'nombre_producto' => $this->bebida->nombre,
                'cantidad' => 2,
                'precio_unitario' => 12000.0,
                'subtotal' => 24000.0,
                'area_cocina' => 'barra',
                'estado_cocina' => 'entregado',
            ]
        ], $this->cajero);

        $this->pedidoService->cobrarPedido($pedido, 'efectivo', 24000.0);

        // Descontar inventario por venta (simulando cocina/KDS o cierre)
        app(InventarioService::class)->descontarPorPedido($pedido);

        // Stock debe haber bajado de 50 a 48
        $this->insumoBebida->refresh();
        $this->assertEquals(48.0, (float) $this->insumoBebida->stock_actual);

        // Esperado en caja: 100.000 fondo + 24.000 venta = 124.000
        $this->turno->refresh();
        $this->assertEquals(124000.0, (float) $this->turno->monto_esperado_efectivo);

        // 2. EL ERROR HUMANO: El cajero cobró 2 pero el cliente solo consumió 1.
        // Fase 8.3: primero se emite la Nota de Crédito obligatoria.
        $nc = NotaCredito::emitir($pedido, 'error_cargo', $this->cajero, 12000.0);
        $item = $pedido->items()->first();
        $devolucion = $this->pedidoService->devolverItemPedido(
            item: $item,
            cantidad: 1,
            motivo: 'Error de digitación del cajero: cliente solo consumió 1 bebida',
            autorizadoPor: 'Gerente Carlos',
            usuario: $this->cajero,
            metodoReembolso: 'efectivo',
            notaCreditoId: $nc->id
        );

        // 3. Verificaciones de la Transacción Atómica:
        // A. Caja: Se registró egreso de 12.000 y el esperado en caja baja a 112.000
        $this->turno->refresh();
        $this->assertEquals(12000.0, (float) $this->turno->total_egresos);
        $this->assertEquals(112000.0, (float) $this->turno->monto_esperado_efectivo);

        // B. Inventario: 1 cerveza regresó al stock (de 48 a 49)
        $this->insumoBebida->refresh();
        $this->assertEquals(49.0, (float) $this->insumoBebida->stock_actual);

        // C. Contabilidad: Se creó asiento de devoluciones_ventas por 12.000
        $this->assertDatabaseHas('asientos_contables', [
            'tipo' => 'gasto',
            'cuenta' => 'devoluciones_ventas',
            'monto' => 12000.0,
            'referencia_id' => $pedido->id,
        ]);

        // D. Registro en pedido_devoluciones
        $this->assertDatabaseHas('pedido_devoluciones', [
            'id' => $devolucion->id,
            'pedido_id' => $pedido->id,
            'cantidad' => 1,
            'monto_devuelto' => 12000.0,
            'autorizado_por' => 'Gerente Carlos',
        ]);

        // E. Ítem del pedido: cantidad_devuelta = 1, disponible para devolver = 1
        $item->refresh();
        $this->assertEquals(1, $item->cantidad_devuelta);
        $this->assertEquals(1, $item->cantidadDisponibleDevolucion());
    }

    public function test_bloquea_devolucion_si_supera_cantidad_disponible(): void
    {
        $pedido = $this->pedidoService->crearPedido([
            'tipo' => 'barra',
            'sucursal_id' => $this->sucursal->id,
            'turno_caja_id' => $this->turno->id,
            'total' => 12000.0,
        ], [
            [
                'producto_id' => $this->bebida->id,
                'nombre_producto' => $this->bebida->nombre,
                'cantidad' => 1,
                'precio_unitario' => 12000.0,
                'subtotal' => 12000.0,
                'area_cocina' => 'barra',
                'estado_cocina' => 'entregado',
            ]
        ], $this->cajero);

        $this->pedidoService->cobrarPedido($pedido, 'efectivo', 12000.0);

        $item = $pedido->items()->first();

        // Con NC válida se llega a la validación de cantidad disponible.
        $nc = NotaCredito::emitir($pedido, 'error_cargo', $this->cajero, 24000.0);

        // Intentar devolver 2 cuando solo se cobró 1
        $this->expectException(\InvalidArgumentException::class);
        $this->pedidoService->devolverItemPedido($item, 2, 'Intento excedido', 'Admin', $this->cajero, 'efectivo', $nc->id);
    }

    public function test_caja_livewire_procesa_devolucion_con_pin_supervisor(): void
    {
        // 1. Configurar PIN de supervisor '4321'
        app(\App\Services\ConfiguracionService::class)->establecerPinSeguridad('4321', $this->gerente);

        // 2. Crear pedido cobrado
        $pedido = $this->pedidoService->crearPedido([
            'tipo' => 'barra',
            'sucursal_id' => $this->sucursal->id,
            'turno_caja_id' => $this->turno->id,
            'total' => 24000.0,
        ], [
            [
                'producto_id' => $this->bebida->id,
                'nombre_producto' => $this->bebida->nombre,
                'cantidad' => 2,
                'precio_unitario' => 12000.0,
                'subtotal' => 24000.0,
                'area_cocina' => 'barra',
                'estado_cocina' => 'entregado',
            ]
        ], $this->cajero);

        $this->pedidoService->cobrarPedido($pedido, 'efectivo', 24000.0);
        app(InventarioService::class)->descontarPorPedido($pedido);

        $item = $pedido->items()->first();

        // 3. Cajero interactúa con la terminal de caja Livewire
        $componente = Volt::actingAs($this->cajero)
            ->test('caja.control', ['cajaId' => $this->caja->id])
            ->call('abrirPrevisualizarTicket', $pedido->id)
            ->assertSet('modalPrevisualizarTicket', true)
            ->call('abrirModalDevolucion', $item->id)
            ->assertSet('modalDevolucionItem', true)
            ->set('cantidadDevolucion', 1)
            ->set('motivoDevolucion', 'Cliente cambió de opinión');

        // 4. Probar keypad táctil del PIN: digitar '4', '3', '2', '1'
        $componente->call('agregarDigitoPinDevolucion', '4')
            ->call('agregarDigitoPinDevolucion', '3')
            ->call('agregarDigitoPinDevolucion', '2')
            ->call('agregarDigitoPinDevolucion', '1')
            ->assertSet('pinAutorizacionDevolucion', '4321');

        // 5. Procesar devolución
        $componente->call('procesarDevolucionItem')
            ->assertHasNoErrors()
            ->assertSet('modalDevolucionItem', false)
            ->assertDispatched('notificacion');

        // 6. Validar que la devolución quedó registrada y el stock recuperado
        $item->refresh();
        $this->assertEquals(1, $item->cantidad_devuelta);
        $this->insumoBebida->refresh();
        $this->assertEquals(49.0, (float) $this->insumoBebida->stock_actual);
    }
}
