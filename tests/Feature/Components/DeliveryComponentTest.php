<?php

namespace Tests\Feature\Components;

use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeliveryComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private User $repartidor;

    private Sucursal $sucursal;

    private Pedido $pedidoDelivery;

    protected function setUp(): void
    {
        parent::setUp();

        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $roleRepartidor = Role::create(['nombre' => 'Repartidor', 'slug' => 'repartidor']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'SushiXpress Envigado',
            'codigo' => 'ENV-01',
            'activa' => true,
        ]);

        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->repartidor = User::factory()->create([
            'name' => 'Mateo Domicilios',
            'role_id' => $roleRepartidor->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->pedidoDelivery = Pedido::create([
            'codigo' => 'ORD-DELIV-01',
            'sucursal_id' => $this->sucursal->id,
            'tipo' => 'delivery',
            'estado' => 'en_cocina',
            'nombre_cliente' => 'Laura Restrepo',
            'telefono_cliente' => '3009998877',
            'direccion_delivery' => 'Transversal 32 # 10-50',
            'subtotal' => 54000,
            'total' => 62000,
            'costo_envio' => 8000,
        ]);
    }

    /**
     * Test de renderizado del centro de control de delivery.
     */
    public function test_delivery_index_renderiza_pedidos_a_domicilio(): void
    {
        $this->actingAs($this->cajero);

        Volt::test('delivery.index')
            ->assertSee('Laura Restrepo')
            ->assertSee('Transversal 32 # 10-50')
            ->assertSee('ORD-DELIV-01');
    }

    /**
     * Test de asignación de repartidor a un pedido en despacho.
     */
    public function test_asignar_repartidor_y_cambiar_estado(): void
    {
        $this->actingAs($this->cajero);

        $component = Volt::test('delivery.index')
            ->set('pedidoSeleccionadoId', $this->pedidoDelivery->id)
            ->set('repartidorIdSeleccionado', $this->repartidor->id)
            ->call('asignarRepartidor');

        $this->pedidoDelivery->refresh();
        $this->assertEquals($this->repartidor->id, $this->pedidoDelivery->repartidor_id);
    }
}
