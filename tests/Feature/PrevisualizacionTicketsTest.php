<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Caja;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PrevisualizacionTicketsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Sucursal $sucursal;

    private Caja $caja;

    private TurnoCaja $turno;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Poblado',
            'codigo' => 'POB-01',
            'direccion' => 'Carrera 43A #1-50',
            'activa' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Alejandro Admin',
            'email' => 'admin@restomaster.test',
            'role_id' => $rolAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJA-01',
            'activa' => true,
        ]);

        $this->turno = TurnoCaja::create([
            'caja_id' => $this->caja->id,
            'user_id' => $this->admin->id,
            'apertura_en' => now(),
            'monto_inicial' => 100000,
            'estado' => 'abierto',
        ]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 5,
            'nombre' => 'Mesa 5',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::OCUPADA->value,
            'activa' => true,
        ]);

        $producto = Producto::create([
            'nombre' => 'Roll Dragón Especial',
            'slug' => 'roll-dragon-especial',
            'precio' => 38000,
            'costo' => 12000,
            'activo' => true,
            'categoria' => 'sushi',
        ]);

        $this->pedido = Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'TICK-9021',
            'turno_caja_id' => $this->turno->id,
            'usuario_id' => $this->admin->id,
            'mesa_id' => $mesa->id,
            'nombre_cliente' => 'Valeria Gómez',
            'estado' => 'pagado',
            'tipo' => 'mesa',
            'subtotal' => 76000,
            'total' => 83600,
            'propina' => 7600,
            'metodo_pago' => 'efectivo',
            'monto_pagado' => 100000,
            'cambio' => 16400,
            'pagado_en' => now(),
        ]);

        ItemPedido::create([
            'pedido_id' => $this->pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => 'Roll Dragón Especial',
            'cantidad' => 2,
            'precio_unitario' => 38000,
            'subtotal' => 76000,
            'area_cocina' => 'sushi',
        ]);
    }

    public function test_caja_control_puede_abrir_modal_previsualizar_ticket_confirmando_datos_en_bd(): void
    {
        $this->actingAs($this->admin);

        Volt::test('caja.control')
            ->assertSee('CAJA-01')
            ->assertSee('TICK-9021')
            ->assertSee('Ver Ticket')
            ->call('abrirPrevisualizarTicket', $this->pedido->id)
            ->assertSet('modalPrevisualizarTicket', true)
            ->assertSet('ticketPrevisualizadoId', $this->pedido->id)
            ->assertSee('Ticket Fiscal POS #TICK-9021')
            ->assertSee('Generado en BD')
            ->assertSee('Roll Dragón Especial')
            ->assertSee('83,600.00')
            ->assertSee('16,400.00')
            ->call('cerrarModalTicket')
            ->assertSet('modalPrevisualizarTicket', false);
    }

    public function test_caja_control_previsualizar_ultimo_ticket(): void
    {
        $this->actingAs($this->admin);

        Volt::test('caja.control')
            ->call('previsualizarUltimoTicket')
            ->assertSet('modalPrevisualizarTicket', true)
            ->assertSet('ticketPrevisualizadoId', $this->pedido->id)
            ->assertSee('REGISTRO REAL EN BD');
    }

    public function test_pos_terminal_previsualizar_ultimo_ticket_pos(): void
    {
        $this->actingAs($this->admin);

        Volt::test('pos.terminal')
            ->call('previsualizarUltimoTicketPos')
            ->assertSet('mostrarTicket', true)
            ->assertSee('Ticket Guardado en BD')
            ->assertSee("ID #{$this->pedido->id}")
            ->assertSee('Roll Dragón Especial');
    }
}
