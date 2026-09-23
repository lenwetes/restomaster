<?php

namespace Tests\Feature;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SeedDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_demo_carga_catalogo_y_vincula_imagenes(): void
    {
        $this->artisan('restomaster:seed-demo')->assertSuccessful();

        $this->assertGreaterThanOrEqual(22, Producto::count());

        $archivos = File::glob(public_path('demo/platos/*.jpg')) ?: [];
        $this->assertNotEmpty($archivos, 'Debe existir la carpeta con imágenes demo.');
        $this->assertEquals(count($archivos), Producto::where('imagen', 'like', '/demo/%')->count());
    }

    public function test_seed_demo_es_idempotente(): void
    {
        $this->artisan('restomaster:seed-demo')->assertSuccessful();
        $conteo = Producto::count();

        $this->artisan('restomaster:seed-demo')->assertSuccessful();
        $this->assertEquals($conteo, Producto::count());
    }
}
