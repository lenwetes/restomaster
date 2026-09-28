<?php

namespace Tests\Feature;

use App\Models\Promocion;
use Database\Seeders\PromocionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromocionesPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromocionSeeder::class);
    }

    public function test_la_portada_muestra_la_seccion_de_promociones_destacadas(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Promociones Especiales en Provenza');
        $response->assertSee('Ver todas las promociones');
        $response->assertSee('Jueves de Gin Tonic 2x1');
    }

    public function test_el_catalogo_publico_de_promociones_responde_exitosamente(): void
    {
        $response = $this->get(route('promociones.publico'));

        $response->assertStatus(200);
        $response->assertSee('Experiencias Exclusivas');
        $response->assertSee('Vigentes Hoy');
        $response->assertSee('Jueves de Gin Tonic 2x1');
        $response->assertSee('Cortes Tomahawk');
    }

    public function test_el_detalle_de_una_promocion_carga_con_su_informacion_completa(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();

        $response = $this->get(route('promociones.detalle', $promo->slug));

        $response->assertStatus(200);
        $response->assertSee($promo->titulo);
        $response->assertSee('Términos');
        $response->assertSee('Compartir por WhatsApp');
        $response->assertSee('Reservar Mesa con esta Promo');
    }
}
