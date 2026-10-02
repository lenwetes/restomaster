<?php

namespace Tests\Feature\Performance;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceP95AndAntiNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        // Crear 5 categorías con 4 productos cada una (20 productos en total)
        for ($c = 1; $c <= 5; $c++) {
            $cat = Categoria::create([
                'nombre' => "Categoría {$c}",
                'slug' => "categoria-{$c}",
                'orden' => $c,
                'activo' => true,
            ]);

            for ($p = 1; $p <= 4; $p++) {
                Producto::create([
                    'nombre' => "Plato C{$c} P{$p}",
                    'slug' => "plato-c{$c}-p{$p}",
                    'categoria_id' => $cat->id,
                    'precio' => 30000 + ($p * 5000),
                    'activo' => true,
                ]);
            }
        }
    }

    public function test_carta_digital_no_genera_consultas_n_plus_one(): void
    {
        /** @var MenuService $menuService */
        $menuService = app(MenuService::class);
        $menuService->invalidarCacheMenu();

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Primera llamada: sin caché
        $menu = $menuService->obtenerMenuPublico();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotEmpty($menu);
        // Debe ejecutar un número fijo y mínimo de consultas (<= 2: categorias + eager loading de productos)
        $this->assertLessThanOrEqual(3, count($queries), 'La consulta del menú público no debe tener N+1');
    }

    public function test_carta_digital_con_cache_responde_con_cero_queries_de_base_de_datos(): void
    {
        /** @var MenuService $menuService */
        $menuService = app(MenuService::class);

        // Precalentar caché
        $menuService->obtenerMenuPublico();

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Segunda llamada: debe servirse 100% desde caché
        $menu = $menuService->obtenerMenuPublico();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotEmpty($menu);
        $this->assertCount(0, $queries, 'El menú público en caché debe ejecutarse con 0 queries SQL');
    }
}
