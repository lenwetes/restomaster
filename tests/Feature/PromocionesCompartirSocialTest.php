<?php

namespace Tests\Feature;

use App\Models\Promocion;
use App\Models\PromocionDifusion;
use Database\Seeders\PromocionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Fase 6.2 — Botones de compartir en promociones (WA/FB/IG + registro difusión).
 */
class PromocionesCompartirSocialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromocionSeeder::class);
    }

    public function test_catalogo_muestra_botones_compartir_wa_fb_ig(): void
    {
        $response = $this->get(route('promociones.publico'));

        $response->assertStatus(200);
        $response->assertSee('Compartir');
        $response->assertSee('wa.me');
        $response->assertSee('facebook.com/sharer');
        $response->assertSee('Link copiado');
    }

    public function test_detalle_muestra_botones_compartir(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();

        $response = $this->get(route('promociones.detalle', $promo->slug));

        $response->assertStatus(200);
        $response->assertSee('Compartir');
        $response->assertSee('wa.me');
        $response->assertSee('facebook.com/sharer');
    }

    public function test_registrar_difusion_guarda_canal_y_url(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();

        Volt::test('promociones.publico')
            ->call('registrarDifusion', $promo->id, 'social_whatsapp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('promocion_difusiones', [
            'promocion_id' => $promo->id,
            'canal' => 'social_whatsapp',
        ]);

        $difusion = PromocionDifusion::where('promocion_id', $promo->id)->first();
        $this->assertStringContainsString('promociones/', $difusion->detalles['url']);
    }

    public function test_registrar_difusion_rechaza_canal_desconocido(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();

        Volt::test('promociones.publico')
            ->call('registrarDifusion', $promo->id, 'canal_falso')
            ->assertHasErrors(['canal']);
    }

    public function test_registrar_difusion_requiere_promocion_existente(): void
    {
        Volt::test('promociones.publico')
            ->call('registrarDifusion', 999999, 'social_whatsapp')
            ->assertHasErrors(['promocionId']);
    }

    public function test_catalogo_muestra_boton_nativo_share(): void
    {
        $response = $this->get(route('promociones.publico'));

        $response->assertStatus(200);
        $response->assertSee('navigator.share', false);
    }

    public function test_catalogo_usa_aspecto_16_9_en_tarjetas(): void
    {
        $response = $this->get(route('promociones.publico'));

        $response->assertStatus(200);
        $response->assertSee('aspect-video', false);
    }

    public function test_detalle_registra_difusion(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();

        Volt::test('promociones.detalle', ['slug' => $promo->slug])
            ->call('registrarDifusion', $promo->id, 'social_facebook')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('promocion_difusiones', [
            'promocion_id' => $promo->id,
            'canal' => 'social_facebook',
        ]);
    }

    public function test_detalle_rechaza_canal_desconocido(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();

        Volt::test('promociones.detalle', ['slug' => $promo->slug])
            ->call('registrarDifusion', $promo->id, 'canal_falso')
            ->assertHasErrors(['canal']);
    }

    public function test_registrar_difusion_nativa(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();

        Volt::test('promociones.publico')
            ->call('registrarDifusion', $promo->id, 'social_nativo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('promocion_difusiones', [
            'promocion_id' => $promo->id,
            'canal' => 'social_nativo',
        ]);
    }

    public function test_detalle_inyecta_open_graph_para_facebook(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();

        $response = $this->get(route('promociones.detalle', $promo->slug));

        $response->assertStatus(200);
        $response->assertSee('property="og:title" content="'.e($promo->titulo), false);
        $response->assertSee('property="og:description" content="', false);
        $response->assertSee('property="og:image" content="http', false);
        $response->assertSee('property="og:url" content="'.route('promociones.detalle', $promo->slug), false);
        $response->assertSee('property="og:type" content="article"', false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
    }

    public function test_share_de_facebook_incluye_quote_con_titulo_promo(): void
    {
        $promo = Promocion::where('slug', 'jueves-gin-tonic-parrilla-2x1')->firstOrFail();
        $quote = urlencode($promo->titulo);

        $catalogo = $this->get(route('promociones.publico'));
        $catalogo->assertStatus(200);
        $catalogo->assertSee('facebook.com/sharer', false);
        $catalogo->assertSee('quote='.$quote, false);

        $detalle = $this->get(route('promociones.detalle', $promo->slug));
        $detalle->assertStatus(200);
        $detalle->assertSee('quote='.$quote, false);
    }
}
