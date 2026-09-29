<?php

namespace Tests\Feature;

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
use Tests\TestCase;

/**
 * Fase 7.1 — No-repetición consecutiva de zonas entre semanas.
 */
class TurnoSemanalRotacionZonasTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    /** @var array<int, User> */
    protected array $meseros = [];

    /** @var array<int, Zona> */
    protected array $zonas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Rotacion Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'admin.rotacion@test.local',
        ]);

        foreach (['Juan', 'Maria'] as $i => $nombre) {
            $this->meseros[] = User::factory()->create([
                'role_id' => $roleMesero->id,
                'sucursal_id' => $this->sucursal->id,
                'name' => $nombre.' Rot',
                'email' => "mesero.rot{$i}@test.local",
                'activo' => true,
            ]);
        }

        foreach (['Salón' => 'salon', 'Barra' => 'barra', 'Terraza' => 'terraza'] as $nombre => $slug) {
            $this->zonas[] = Zona::create([
                'sucursal_id' => $this->sucursal->id,
                'nombre' => $nombre,
                'slug' => $slug,
                'color' => 'terracota',
                'icono' => 'mesa',
                'activa' => true,
                'orden' => count($this->zonas) + 1,
            ]);
        }

        PlantillaTurno::create([
            'nombre' => 'General',
            'hora_inicio' => '11:00',
            'hora_fin' => '23:00',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    protected function semanaFutura(int $offsetSemanas): array
    {
        $fecha = Carbon::today()->addWeeks($offsetSemanas);

        return [(int) $fecha->isoWeek, (int) $fecha->isoWeekYear];
    }

    protected function zonaLunes(int $programacionId, int $meseroId): ?string
    {
        $turno = TurnoMeseroSemana::where('programacion_semanal_id', $programacionId)
            ->where('user_id', $meseroId)
            ->get()
            ->first(fn ($t) => (int) $t->fecha->dayOfWeekIso === 1 && ! $t->es_descanso);

        return $turno?->zona?->nombre;
    }

    public function test_no_repite_zona_en_semana_consecutiva(): void
    {
        $svc = app(TurnoSemanalService::class);
        [$s1, $a1] = $this->semanaFutura(2);
        [$s2, $a2] = $this->semanaFutura(3);

        $prog1 = $svc->obtenerOCrearSemana($this->sucursal->id, $s1, $a1, $this->admin);
        $svc->autoProgramar($prog1->id, $this->admin, false);

        $prog2 = $svc->obtenerOCrearSemana($this->sucursal->id, $s2, $a2, $this->admin);
        $svc->autoProgramar($prog2->id, $this->admin, false);

        $zonaSemana1 = $this->zonaLunes($prog1->id, $this->meseros[0]->id);
        $zonaSemana2 = $this->zonaLunes($prog2->id, $this->meseros[0]->id);

        $this->assertNotNull($zonaSemana1);
        $this->assertNotNull($zonaSemana2);
        $this->assertNotEquals($zonaSemana1, $zonaSemana2);
    }

    public function test_toggle_apagado_permite_repeticion_identica(): void
    {
        $svc = app(TurnoSemanalService::class);
        $svc->guardarReglasRotacion($this->sucursal->id, ['impedir_zona_repetida' => false], $this->admin);

        [$s1, $a1] = $this->semanaFutura(2);
        [$s2, $a2] = $this->semanaFutura(3);

        $prog1 = $svc->obtenerOCrearSemana($this->sucursal->id, $s1, $a1, $this->admin);
        $svc->autoProgramar($prog1->id, $this->admin, false);

        $prog2 = $svc->obtenerOCrearSemana($this->sucursal->id, $s2, $a2, $this->admin);
        $svc->autoProgramar($prog2->id, $this->admin, false);

        $this->assertEquals(
            $this->zonaLunes($prog1->id, $this->meseros[0]->id),
            $this->zonaLunes($prog2->id, $this->meseros[0]->id)
        );
    }

    public function test_ciclo_reinicia_si_todas_las_zonas_fueron_usadas(): void
    {
        $svc = app(TurnoSemanalService::class);

        // Una sola zona: la exclusión la vacía y el ciclo debe reiniciar.
        Zona::where('sucursal_id', $this->sucursal->id)->where('slug', '!=', 'salon')->delete();

        [$s1, $a1] = $this->semanaFutura(2);
        [$s2, $a2] = $this->semanaFutura(3);

        $prog1 = $svc->obtenerOCrearSemana($this->sucursal->id, $s1, $a1, $this->admin);
        $svc->autoProgramar($prog1->id, $this->admin, false);

        $prog2 = $svc->obtenerOCrearSemana($this->sucursal->id, $s2, $a2, $this->admin);
        $total = $svc->autoProgramar($prog2->id, $this->admin, false);

        $this->assertGreaterThan(0, $total);
        $this->assertEquals('Salón', $this->zonaLunes($prog2->id, $this->meseros[0]->id));
    }

    public function test_reglas_por_defecto_y_validacion(): void
    {
        $svc = app(TurnoSemanalService::class);

        $reglas = $svc->obtenerReglasRotacion($this->sucursal->id);
        $this->assertTrue($reglas['impedir_zona_repetida']);
        $this->assertEquals(3, $reglas['longitud_ciclo']);
        $this->assertEquals([5, 6, 7], $reglas['dias_alta_demanda']);
        $this->assertEquals(4, $reglas['semanas_ciclo_descanso']);

        $this->expectException(\InvalidArgumentException::class);
        $svc->guardarReglasRotacion($this->sucursal->id, ['longitud_ciclo' => 99], $this->admin);
    }

    public function test_guardar_reglas_requiere_admin_o_gerente(): void
    {
        $svc = app(TurnoSemanalService::class);

        $this->expectException(AuthorizationException::class);
        $svc->guardarReglasRotacion($this->sucursal->id, ['longitud_ciclo' => 3], $this->meseros[0]);
    }
}
