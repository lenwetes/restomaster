<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
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

        $conArchivo = Producto::all(['slug'])
            ->filter(fn ($p) => File::exists(public_path("demo/platos/{$p->slug}.jpg")))
            ->count();
        $this->assertGreaterThanOrEqual(22, $conArchivo);
        $this->assertEquals($conArchivo, Producto::where('imagen', 'like', '/demo/%')->count());
    }

    public function test_seed_demo_es_idempotente(): void
    {
        $this->artisan('restomaster:seed-demo')->assertSuccessful();
        $conteo = Producto::count();

        $this->artisan('restomaster:seed-demo')->assertSuccessful();
        $this->assertEquals($conteo, Producto::count());
    }

    public function test_seed_demo_deja_comandas_visibles_en_kds_por_sucursal(): void
    {
        $this->artisan('restomaster:seed-demo')->assertSuccessful();

        $this->assertEquals(0, Pedido::whereNull('sucursal_id')->count(), 'Todo pedido demo debe llevar sucursal.');

        $usuario = User::whereNotNull('sucursal_id')->first();
        $this->assertNotNull($usuario);

        $visiblesEnKds = Pedido::whereIn('estado', ['en_cocina', 'en_preparacion', 'creado', 'listo'])
            ->where('sucursal_id', $usuario->sucursal_id)
            ->whereHas('items', fn ($q) => $q->whereIn('estado_cocina', ['pendiente', 'en_preparacion', 'listo']))
            ->count();

        $this->assertGreaterThanOrEqual(2, $visiblesEnKds, 'Las comandas demo deben aparecer en el KDS del usuario.');
    }
}
