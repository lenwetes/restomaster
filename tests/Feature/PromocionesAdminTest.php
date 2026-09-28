<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Promocion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromocionesAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $mesero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $roleAdmin = Role::where('slug', 'admin')->firstOrFail();
        $roleMesero = Role::where('slug', 'mesero')->firstOrFail();

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'name' => 'Gerente General',
            'email' => 'gerente@restomaster.test',
        ]);

        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'name' => 'Mesero Sala',
            'email' => 'mesero@restomaster.test',
        ]);
    }

    public function test_solo_administradores_y_gerentes_pueden_acceder_al_gestor_de_promociones(): void
    {
        $this->actingAs($this->mesero)
            ->get(route('promociones.index'))
            ->assertStatus(403);

        $this->actingAs($this->admin)
            ->get(route('promociones.index'))
            ->assertStatus(200)
            ->assertSee('Gestor de Promociones');
    }

    public function test_el_administrador_puede_crear_una_nueva_promocion(): void
    {
        Livewire::actingAs($this->admin)
            ->test('promociones.index')
            ->call('abrirModalCrear')
            ->set('titulo', 'Viernes de Sushi Roll 2x1')
            ->set('descripcion', 'Dos rollos de autor al precio de uno en salón y delivery.')
            ->set('tipo_beneficio', 'dos_por_uno')
            ->set('mostrar_en_portada', true)
            ->call('guardarPromocion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('promociones', [
            'titulo' => 'Viernes de Sushi Roll 2x1',
            'slug' => 'viernes-de-sushi-roll-2x1',
            'tipo_beneficio' => 'dos_por_uno',
            'mostrar_en_portada' => true,
        ]);
    }

    public function test_el_administrador_puede_ejecutar_un_lanzamiento_omnicanal(): void
    {
        $promo = Promocion::create([
            'titulo' => 'Promo Lanzamiento Test',
            'slug' => 'promo-lanzamiento-test',
            'descripcion' => 'Descripción de prueba para difusión',
            'tipo_beneficio' => 'porcentaje_descuento',
            'descuento_porcentaje' => 15.00,
            'activo' => true,
        ]);

        Cliente::create([
            'nombre' => 'Mateo Gómez',
            'telefono' => '+573009998877',
            'email' => 'mateo@ejemplo.test',
            'tier' => 'vip',
            'activo' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test('promociones.index')
            ->call('abrirModalLanzamiento', $promo->id)
            ->set('canalLanzamiento', 'ambos')
            ->set('segmentoLanzamiento', 'vip')
            ->call('ejecutarLanzamiento');

        $this->assertDatabaseHas('promocion_difusiones', [
            'promocion_id' => $promo->id,
            'canal' => 'ambos',
            'segmento' => 'vip',
            'estado' => 'completado',
        ]);

        $promo->refresh();
        $this->assertGreaterThan(0, $promo->total_notificados_whatsapp);
        $this->assertNotNull($promo->ultimo_lanzamiento_at);
    }
}
