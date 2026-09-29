<?php

namespace Tests\Feature;

use App\Events\HorarioSemanalPublicado;
use App\Models\PlantillaTurno;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoMeseroSemana;
use App\Models\User;
use App\Models\Zona;
use App\Services\TurnoSemanalService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Fase 3.1 + 3.2 — CRUD de programaciones, copiar semana, publicar.
 */
class TurnoSemanalCRUDTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    protected User $mesero1;

    protected User $mesero2;

    protected Zona $zonaSalon;

    protected Zona $zonaBarra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Turnos Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Admin Turnos',
            'email' => 'admin.turnos@test.local',
        ]);
        $this->mesero1 = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Juan Turnos',
            'email' => 'juan.turnos@test.local',
            'activo' => true,
        ]);
        $this->mesero2 = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Maria Turnos',
            'email' => 'maria.turnos@test.local',
            'activo' => true,
        ]);

        $this->zonaSalon = Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Salón',
            'slug' => 'salon',
            'color' => 'terracota',
            'icono' => 'mesa',
            'activa' => true,
            'orden' => 1,
        ]);
        $this->zonaBarra = Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Barra',
            'slug' => 'barra',
            'color' => 'salvia',
            'icono' => 'local_bar',
            'activa' => true,
            'orden' => 2,
        ]);
    }

    public function test_crear_plantilla_turno_con_validacion(): void
    {
        $plantilla = PlantillaTurno::create([
            'nombre' => 'Almuerzo',
            'hora_inicio' => '11:00',
            'hora_fin' => '16:00',
            'zona_default_id' => $this->zonaSalon->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('plantillas_turnos', ['nombre' => 'Almuerzo']);
        $this->assertEquals('11:00', $plantilla->hora_inicio->format('H:i'));
    }

    public function test_obtener_o_crear_semana_es_idempotente(): void
    {
        $svc = app(TurnoSemanalService::class);

        $a = $svc->obtenerOCrearSemana($this->sucursal->id, 40, 2026, $this->admin);
        $b = $svc->obtenerOCrearSemana($this->sucursal->id, 40, 2026, $this->admin);

        $this->assertEquals($a->id, $b->id);
        $this->assertEquals('borrador', $a->estado);
        $this->assertDatabaseCount('programaciones_semanales', 1);
    }

    public function test_copiar_semana_anterior_replica_turnos_en_fechas_destino(): void
    {
        $svc = app(TurnoSemanalService::class);

        $origen = $svc->obtenerOCrearSemana($this->sucursal->id, 39, 2026, $this->admin);
        TurnoMeseroSemana::create([
            'programacion_semanal_id' => $origen->id,
            'user_id' => $this->mesero1->id,
            'fecha' => Carbon::create(2026, 9, 21),
            'zona_id' => $this->zonaSalon->id,
            'es_descanso' => false,
        ]);

        $destino = $svc->copiarSemanaAnterior($this->sucursal->id, 40, 2026, $this->admin);

        $this->assertEquals(40, $destino->semana_iso);
        $this->assertEquals(1, $destino->turnos()->count());
        $turnoCopiado = $destino->turnos()->first();
        $this->assertEquals($this->mesero1->id, $turnoCopiado->user_id);
        $this->assertEquals($this->zonaSalon->id, $turnoCopiado->zona_id);
        $this->assertEquals(40, (int) $turnoCopiado->fecha->isoWeek);
    }

    public function test_publicar_semana_cambia_estado_dispara_evento_y_sincroniza_rotacion(): void
    {
        Event::fake([HorarioSemanalPublicado::class]);
        $svc = app(TurnoSemanalService::class);

        $prog = $svc->obtenerOCrearSemana($this->sucursal->id, 40, 2026, $this->admin);
        $hoy = Carbon::today();
        TurnoMeseroSemana::create([
            'programacion_semanal_id' => $prog->id,
            'user_id' => $this->mesero1->id,
            'fecha' => $hoy,
            'zona_id' => $this->zonaSalon->id,
            'es_descanso' => false,
        ]);

        $publicada = $svc->publicarSemana($prog->id, $this->admin);

        $this->assertEquals('publicado', $publicada->estado);
        $this->assertEquals($this->admin->id, $publicada->publicado_por);
        $this->assertNotNull($publicada->publicado_en);
        Event::assertDispatched(HorarioSemanalPublicado::class);

        $this->assertDatabaseHas('rotaciones_meseros', [
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->mesero1->id,
        ]);
    }

    public function test_publicar_semana_requiere_admin_o_gerente(): void
    {
        $svc = app(TurnoSemanalService::class);
        $prog = $svc->obtenerOCrearSemana($this->sucursal->id, 40, 2026, $this->admin);

        $this->expectException(AuthorizationException::class);
        $svc->publicarSemana($prog->id, $this->mesero1);
    }

    public function test_semana_iso_fuera_de_rango_es_rechazada(): void
    {
        $svc = app(TurnoSemanalService::class);

        $this->expectException(\InvalidArgumentException::class);
        $svc->obtenerOCrearSemana($this->sucursal->id, 99, 2026, $this->admin);
    }
}
