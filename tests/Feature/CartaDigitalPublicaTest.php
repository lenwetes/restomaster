<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartaDigitalPublicaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Categoria $categoria;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->categoria = Categoria::create([
            'nombre' => 'Parrilla & Asados',
            'slug' => 'parrilla-asados',
            'icono' => 'restaurant',
            'color' => '#e0442e',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->producto = Producto::create([
            'nombre' => 'Bife de Chorizo Angus',
            'slug' => 'bife-de-chorizo-angus',
            'categoria_id' => $this->categoria->id,
            'precio' => 68000,
            'area_cocina' => 'caliente',
            'imagen' => '/demo/platos/bife-de-chorizo-angus.jpg',
            'activo' => true,
        ]);
    }

    public function test_menu_service_incluye_imagen_url_en_menu_publico(): void
    {
        /** @var MenuService $menuService */
        $menuService = app(MenuService::class);
        $menuService->invalidarCacheMenu();

        $menu = $menuService->obtenerMenuPublico();

        $this->assertNotEmpty($menu);
        $primerCat = $menu->first();
        $this->assertNotNull($primerCat);
        $this->assertNotEmpty($primerCat['productos']);

        $primerProd = $primerCat['productos'][0];
        $this->assertArrayHasKey('imagen', $primerProd);
        $this->assertArrayHasKey('imagen_url', $primerProd);
        $this->assertNotNull($primerProd['imagen_url']);
        $this->assertStringContainsString('bife-de-chorizo-angus.jpg', $primerProd['imagen_url']);
    }

    public function test_carta_digital_publica_carga_con_imagenes_y_sin_sticky_en_categorias(): void
    {
        app(MenuService::class)->invalidarCacheMenu();

        $response = $this->get('/carta');

        $response->assertStatus(200);
        $response->assertSee('Bife de Chorizo Angus');
        $response->assertSee('bife-de-chorizo-angus.jpg');
        // El selector de categorías no debe tener 'sticky top-20'
        $response->assertDontSee('sticky top-20 z-30');
    }

    public function test_delivery_pedir_es_accesible_publicamente_sin_login(): void
    {
        $response = $this->get('/delivery/pedir');

        $response->assertStatus(200);
        $response->assertDontSee('Iniciar Sesión');
    }

    public function test_todas_las_foreign_keys_tienen_indices_en_postgresql(): void
    {
        try {
            config(['database.connections.pgsql.database' => 'restomaster']);
            DB::purge('pgsql');
            $connection = DB::connection('pgsql');
            $connection->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('PostgreSQL no disponible: '.$e->getMessage());
        }

        $unindexedFks = $connection->select("
            SELECT
                c.conrelid::regclass AS table_name,
                c.conname AS fk_name
            FROM pg_constraint c
            WHERE c.contype = 'f'
              AND c.connamespace = 'public'::regnamespace
              AND NOT EXISTS (
                  SELECT 1
                  FROM pg_index i
                  WHERE i.indrelid = c.conrelid
                    AND (i.indkey::smallint[])[0:array_length(c.conkey, 1) - 1] = c.conkey
              )
        ");

        $this->assertEmpty($unindexedFks, 'Se encontraron foreign keys sin índice en PostgreSQL: '.json_encode($unindexedFks));
    }
}
