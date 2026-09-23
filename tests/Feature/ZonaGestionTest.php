<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Zona;
use App\Services\MesaService;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ZonaGestionTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);
        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Test',
            'codigo' => 'TST-01',
            'direccion' => 'Calle 1',
            'telefono' => '3000000000',
            'activa' => true,
        ]);
        $this->admin = User::factory()->create(['role_id' => $roleAdmin->id, 'sucursal_id' => $this->sucursal->id]);
    }

    public function test_seeder_crea_zonas_clasicas_por_sucursal(): void
    {
        $this->seed(ZonaSeeder::class);

        $this->assertEquals(4, Zona::where('sucursal_id', $this->sucursal->id)->count());
        $this->assertDatabaseHas('zonas', ['sucursal_id' => $this->sucursal->id, 'slug' => 'salon']);
    }

    public function test_policy_zonas_solo_gerente_admin_gestionan_y_cajero_mueve(): void
    {
        $gerente = User::factory()->create(['role_id' => Role::create(['nombre' => 'Gerente', 'slug' => 'gerente'])->id]);
        $cajero = User::factory()->create(['role_id' => Role::create(['nombre' => 'Cajero', 'slug' => 'cajero'])->id]);
        $mesero = User::factory()->create(['role_id' => Role::create(['nombre' => 'Mesero', 'slug' => 'mesero'])->id]);

        $this->assertTrue($gerente->can('create', Zona::class));
        $this->assertTrue($gerente->can('update', Zona::class));
        $this->assertFalse($cajero->can('create', Zona::class));
        $this->assertTrue($cajero->can('mover', Zona::class));
        $this->assertFalse($mesero->can('mover', Zona::class));
    }

    public function test_mover_mesa_actualiza_zona_y_rechaza_invalida(): void
    {
        $this->seed(ZonaSeeder::class);
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '99',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
        ]);

        $movida = app(MesaService::class)->moverMesa($mesa, 'terraza');

        $this->assertSame('terraza', $movida->fresh()->zona);

        $this->expectException(\DomainException::class);
        app(MesaService::class)->moverMesa($mesa->fresh(), 'zona-fantasma');
    }

    public function test_crear_mesa_acepta_zona_nueva_del_catalogo(): void
    {
        $this->seed(ZonaSeeder::class);
        Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Jardín',
            'slug' => 'jardin',
            'color' => 'salvia',
            'icono' => 'terraza',
            'orden' => 5,
            'activa' => true,
        ]);

        Volt::actingAs($this->admin)
            ->test('mesas.index')
            ->set('formMesa.numero', 'J-01')
            ->set('formMesa.zona', 'jardin')
            ->set('formMesa.capacidad', 4)
            ->call('guardarMesa')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mesas', ['numero' => 'J-01', 'zona' => 'jardin']);
    }

    public function test_mapa_muestra_zona_nueva_con_su_color(): void
    {
        $this->seed(ZonaSeeder::class);
        Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Jardín',
            'slug' => 'jardin',
            'color' => 'salvia',
            'icono' => 'terraza',
            'orden' => 5,
            'activa' => true,
        ]);
        Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'J-01',
            'capacidad' => 4,
            'zona' => 'jardin',
            'estado' => 'libre',
        ]);

        Volt::actingAs($this->admin)
            ->test('mesas.index')
            ->assertSee('Jardín')
            ->assertSee('bg-secondary-container/70', false);
    }

    public function test_gestionar_zonas_crea_y_desactivar_bloqueada_con_mesas(): void
    {
        $this->seed(ZonaSeeder::class);

        $t = Volt::actingAs($this->admin)->test('mesas.index');

        $t->call('abrirModalZonas')
            ->set('zonaForm.nombre', 'Jardín')
            ->set('zonaForm.color', 'salvia')
            ->set('zonaForm.icono', 'terraza')
            ->call('guardarZona')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('zonas', ['slug' => 'jardin', 'sucursal_id' => $this->sucursal->id]);

        Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'J-01',
            'capacidad' => 4,
            'zona' => 'jardin',
            'estado' => 'libre',
        ]);

        $t->call('alternarZona', Zona::where('slug', 'jardin')->value('id'))
            ->assertSee('tiene mesas asignadas');
    }

    public function test_mesero_no_puede_gestionar_zonas(): void
    {
        $mesero = User::factory()->create([
            'role_id' => Role::create(['nombre' => 'Mesero', 'slug' => 'mesero'])->id,
        ]);

        Volt::actingAs($mesero)
            ->test('mesas.index')
            ->call('abrirModalZonas')
            ->assertForbidden();
    }

    public function test_gestionar_zonas_rechaza_nombre_duplicado_con_mensaje_visible(): void
    {
        $this->seed(ZonaSeeder::class);

        $t = Volt::actingAs($this->admin)->test('mesas.index');

        $t->call('abrirModalZonas')
            ->set('zonaForm.nombre', 'Jardín')
            ->set('zonaForm.color', 'salvia')
            ->set('zonaForm.icono', 'terraza')
            ->call('guardarZona')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('zonas', ['slug' => 'jardin', 'sucursal_id' => $this->sucursal->id]);

        $t->call('abrirModalZonas')
            ->set('zonaForm.nombre', 'Jardín')
            ->set('zonaForm.color', 'salvia')
            ->set('zonaForm.icono', 'terraza')
            ->call('guardarZona')
            ->assertSee('Ya existe una zona');

        $this->assertSame(1, Zona::deSucursal($this->sucursal->id)->where('slug', 'jardin')->count());
    }

    public function test_mover_mesa_cambia_zona_y_notifica(): void
    {
        $this->seed(ZonaSeeder::class);
        $cajero = User::factory()->create([
            'role_id' => Role::create(['nombre' => 'Cajero', 'slug' => 'cajero'])->id,
            'sucursal_id' => $this->sucursal->id,
        ]);
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-01',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
        ]);

        Volt::actingAs($cajero)
            ->test('mesas.index')
            ->call('moverMesaAZona', $mesa->id, 'terraza')
            ->assertHasNoErrors();

        $this->assertSame('terraza', $mesa->fresh()->zona);
    }

    public function test_mover_mesa_rechaza_zona_ajena_y_permiso(): void
    {
        $this->seed(ZonaSeeder::class);
        $mesero = User::factory()->create([
            'role_id' => Role::create(['nombre' => 'Mesero', 'slug' => 'mesero'])->id,
            'sucursal_id' => $this->sucursal->id,
        ]);
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M-02',
            'capacidad' => 2,
            'zona' => 'salon',
            'estado' => 'libre',
        ]);

        Volt::actingAs($mesero)
            ->test('mesas.index')
            ->call('moverMesaAZona', $mesa->id, 'terraza')
            ->assertForbidden();
    }
}
