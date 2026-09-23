<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ConfiguracionService;
use App\Services\ImpresionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TicketConfigurableTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Mesa $mesa;

    private User $mesero;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Test',
            'codigo' => 'TST-01',
            'direccion' => 'Calle Test',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleAdmin->id,
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero Test',
            'email' => 'mesero@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleMesero->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 7,
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => true,
        ]);
    }

    private function crearPedidoConItem(): Pedido
    {
        $cat = Categoria::create(['nombre' => 'Test', 'slug' => 'test-cat', 'activo' => true]);
        $prod = Producto::create([
            'categoria_id' => $cat->id,
            'nombre' => 'Producto Test',
            'slug' => 'producto-test',
            'precio' => 50000,
            'costo' => 20000,
            'activo' => true,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'ORD-TICKET-001',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'mesa_id' => $this->mesa->id,
            'usuario_id' => $this->admin->id,
            'mesero_id' => $this->mesero->id,
            'subtotal' => 100000,
            'total' => 100000,
            'metodo_pago' => 'efectivo',
            'monto_pagado' => 110000,
            'cambio' => 10000,
            'pagado_en' => now(),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $prod->id,
            'nombre_producto' => $prod->nombre,
            'cantidad' => 2,
            'precio_unitario' => 50000,
            'subtotal' => 100000,
            'area_cocina' => 'sushi',
        ]);

        return $pedido->fresh(['items', 'mesa', 'usuario', 'mesero']);
    }

    public function test_ticket_impreso_usa_nombre_comercial_y_pie_configurados(): void
    {
        $svc = app(ConfiguracionService::class);
        $svc->guardar('ticket_80mm', 'nombre_comercial', 'MI RESTO TEST UNICO');
        $svc->guardar('ticket_80mm', 'pie_pagina', 'GRACIAS TEST 123 UNICO');
        $svc->guardar('ticket_80mm', 'resolucion_dian', 'RES TEST 999 UNICA');
        $svc->guardar('ticket_80mm', 'rango_autorizado', 'RANGO TEST UNICO');

        $ticket = app(ImpresionService::class)->formatearTicketVentaTexto($this->crearPedidoConItem());

        $this->assertStringContainsString('MI RESTO TEST UNICO', $ticket);
        $this->assertStringContainsString('GRACIAS TEST 123 UNICO', $ticket);
        $this->assertStringContainsString('RES TEST 999 UNICA', $ticket);
        $this->assertStringContainsString('RANGO TEST UNICO', $ticket);
    }

    public function test_ticket_impreso_respeta_ocultar_mesero(): void
    {
        $svc = app(ConfiguracionService::class);
        $svc->guardar('ticket_80mm', 'mostrar_datos_mesero', false);

        $ticket = app(ImpresionService::class)->formatearTicketVentaTexto($this->crearPedidoConItem());

        $this->assertStringNotContainsString('MESERO:', $ticket);
        $this->assertStringNotContainsString($this->mesero->name, $ticket);
    }

    public function test_ticket_impreso_no_contiene_japones_ni_hardcode_viejo(): void
    {
        $svc = app(ConfiguracionService::class);
        $svc->guardar('ticket_80mm', 'nombre_comercial', 'MI RESTO TEST UNICO');
        $svc->guardar('ticket_80mm', 'pie_pagina', 'GRACIAS TEST 123 UNICO');

        $ticket = app(ImpresionService::class)->formatearTicketVentaTexto($this->crearPedidoConItem());

        $this->assertStringNotContainsString('ありがとう', $ticket);
        $this->assertStringNotContainsString('Arigat', $ticket);
        $this->assertStringNotContainsString('AURA GASTRO', $ticket);
        $this->assertStringNotContainsString('901.884.200-1', $ticket);
    }

    public function test_pos_modal_usa_config_y_sin_japones(): void
    {
        $svc = app(ConfiguracionService::class);
        $svc->guardar('ticket_80mm', 'nombre_comercial', 'MI RESTO POS UNICO');
        $svc->guardar('ticket_80mm', 'pie_pagina', 'PIE POS UNICO 456');
        $svc->guardar('ticket_80mm', 'mostrar_datos_mesero', false);

        $pedido = $this->crearPedidoConItem();

        $component = Volt::actingAs($this->admin)
            ->test('pos.terminal')
            ->set('pedidoCompletado', $pedido)
            ->set('mostrarTicket', true);

        $html = $component->html();

        $this->assertStringContainsString('MI RESTO POS UNICO', $html);
        $this->assertStringContainsString('PIE POS UNICO 456', $html);
        $this->assertStringNotContainsString('ありがとう', $html);
        $this->assertStringNotContainsString('Arigat', $html);
        $this->assertStringNotContainsString('AURA GASTRO', $html);
        $this->assertStringNotContainsString('901.884.200-1', $html);
    }
}
