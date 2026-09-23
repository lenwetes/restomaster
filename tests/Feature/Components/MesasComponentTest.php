<?php

namespace Tests\Feature\Components;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MesasComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $mesero;

    private Sucursal $sucursal;

    private Mesa $mesaSalon;

    private Mesa $mesaTerraza;

    protected function setUp(): void
    {
        parent::setUp();

        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'activa' => true,
        ]);

        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->mesaSalon = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '101',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
            'activo' => true,
        ]);

        $this->mesaTerraza = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '201',
            'capacidad' => 6,
            'zona' => 'terraza',
            'estado' => MesaEstado::OCUPADA->value,
            'activo' => true,
        ]);
    }

    /**
     * Test de renderizado del plano de mesas y presencia de mesas de la sucursal.
     */
    public function test_mesas_index_renderiza_mesas_de_la_sucursal(): void
    {
        $this->actingAs($this->mesero);

        Volt::test('mesas.index')
            ->assertSee('101')
            ->assertSee('201')
            ->assertSet('filtroZona', 'todas');
    }

    /**
     * Test de filtrado por zona operativa (salon vs terraza).
     */
    public function test_filtro_por_zona_actualiza_renderizado(): void
    {
        $this->actingAs($this->mesero);

        Volt::test('mesas.index')
            ->set('filtroZona', 'salon')
            ->assertSee('101')
            ->assertSet('filtroZona', 'salon');
    }
}
