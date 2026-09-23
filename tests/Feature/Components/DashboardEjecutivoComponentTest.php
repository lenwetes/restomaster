<?php

namespace Tests\Feature\Components;

use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardEjecutivoComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $roleGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'SushiXpress Provenza',
            'codigo' => 'PRV-01',
            'activa' => true,
        ]);

        $this->gerente = User::factory()->create([
            'role_id' => $roleGerente->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        Pedido::create([
            'codigo' => 'ORD-DASH-COMP-01',
            'sucursal_id' => $this->sucursal->id,
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'subtotal' => 120000,
            'total' => 120000,
            'pagado_en' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Test de renderizado inicial con período 'hoy' y datos reactivos.
     */
    public function test_dashboard_ejecutivo_renderiza_con_periodo_hoy(): void
    {
        $this->actingAs($this->gerente);

        Volt::test('dashboard.ejecutivo')
            ->assertSet('periodo', 'hoy')
            ->assertSee('Panel Ejecutivo RestoMaster')
            ->assertSee('$120.000');
    }

    /**
     * Test de cambio reactivo de período a 'ayer' o 'mes'.
     */
    public function test_cambio_de_periodo_actualiza_estado(): void
    {
        $this->actingAs($this->gerente);

        Volt::test('dashboard.ejecutivo')
            ->call('setPeriodo', 'ayer')
            ->assertSet('periodo', 'ayer')
            ->call('setPeriodo', 'mes')
            ->assertSet('periodo', 'mes');
    }
}
