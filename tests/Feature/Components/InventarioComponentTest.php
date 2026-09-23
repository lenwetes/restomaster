<?php

namespace Tests\Feature\Components;

use App\Models\Insumo;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InventarioComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private Sucursal $sucursal;

    private Insumo $insumoA;

    private Insumo $insumoB;

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

        $this->insumoA = Insumo::create([
            'codigo' => 'INS-ATUN-01',
            'nombre' => 'Atún Rojo Aleta Amarilla',
            'categoria' => 'pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 12.0,
            'stock_minimo' => 4.0,
            'costo_unitario' => 75000.0,
            'activo' => true,
        ]);

        $this->insumoB = Insumo::create([
            'codigo' => 'INS-WAKAME-01',
            'nombre' => 'Algas Wakame Ensalada',
            'categoria' => 'vegetales',
            'unidad_medida' => 'kg',
            'stock_actual' => 1.0, // Bajo mínimo (crítico)
            'stock_minimo' => 3.0,
            'costo_unitario' => 32000.0,
            'activo' => true,
        ]);
    }

    /**
     * Test de renderizado del catálogo de inventario y stock.
     */
    public function test_inventario_index_renderiza_lista_de_insumos(): void
    {
        $this->actingAs($this->gerente);

        Volt::test('inventario.index')
            ->assertSee('Atún Rojo Aleta Amarilla')
            ->assertSee('Algas Wakame Ensalada');
    }

    /**
     * Test de búsqueda de insumo por nombre o código.
     */
    public function test_busqueda_de_insumo_filtra_correctamente(): void
    {
        $this->actingAs($this->gerente);
        $this->insumoB->update(['stock_actual' => 10.0]); // No crítico para evitar que aparezca en el banner superior

        Volt::test('inventario.index')
            ->call('selectInsumo', $this->insumoA->id)
            ->set('search', 'Atún')
            ->assertSee('Atún Rojo Aleta Amarilla')
            ->assertDontSee('Algas Wakame Ensalada');
    }

    /**
     * Test de filtrado por estado crítico de existencias.
     */
    public function test_filtro_criticos_solo_muestra_insumos_bajo_minimo(): void
    {
        $this->actingAs($this->gerente);

        Volt::test('inventario.index')
            ->set('selectedFiltro', 'criticos')
            ->assertSee('Algas Wakame Ensalada')
            ->assertDontSee('Atún Rojo Aleta Amarilla');
    }
}
