<?php

namespace Tests\Feature;

use App\Models\PlantillaTurno;
use App\Models\ProgramacionSemanal;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoMeseroSemana;
use App\Models\User;
use App\Models\Zona;
use App\Services\TurnoSemanalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.2 — Ciclo de descansos equitativos (4 semanas, offset rotativo).
 */
class TurnoSemanalDescansoEquitativoTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    /** @var array<int, User> */
    protected array $meseros = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Descansos Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'admin.descanso@test.local',
        ]);

        foreach (['Ana', 'Luis', 'Sofia', 'Pedro'] as $i => $nombre) {
            $this->meseros[] = User::factory()->create([
                'role_id' => $roleMesero->id,
                'sucursal_id' => $this->sucursal->id,
                'name' => $nombre.' Desc',
                'email' => "mesero.desc{$i}@test.local",
                'activo' => true,
            ]);
        }

        foreach (['Salón' => 'salon', 'Barra' => 'barra', 'Terraza' => 'terraza'] as $nombre => $slug) {
            Zona::create([
                'sucursal_id' => $this->sucursal->id,
                'nombre' => $nombre,
                'slug' => $slug,
                'color' => 'terracota',
                'icono' => 'mesa',
                'activa' => true,
                'orden' => 1,
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

    /**
     * @return array<int, ProgramacionSemanal>
     */
    protected function programarRacha(int $desdeOffset, int $semanas): array
    {
        $svc = app(TurnoSemanalService::class);
        $progs = [];

        for ($i = 0; $i < $semanas; $i++) {
            $fecha = Carbon::today()->addWeeks($desdeOffset + $i);
            $prog = $svc->obtenerOCrearSemana($this->sucursal->id, (int) $fecha->isoWeek, (int) $fecha->isoWeekYear, $this->admin);
            $svc->autoProgramar($prog->id, $this->admin, false);
            $progs[] = $prog->fresh();
        }

        return $progs;
    }

    protected function diaDescanso(int $programacionId, int $meseroId): ?int
    {
        $turno = TurnoMeseroSemana::where('programacion_semanal_id', $programacionId)
            ->where('user_id', $meseroId)
            ->where('es_descanso', true)
            ->first();

        return $turno ? (int) $turno->fecha->dayOfWeekIso : null;
    }

    public function test_cada_mesero_descansa_exactamente_un_dia_por_semana(): void
    {
        [$prog] = $this->programarRacha(2, 1);

        foreach ($this->meseros as $mesero) {
            $this->assertEquals(1, TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
                ->where('user_id', $mesero->id)->where('es_descanso', true)->count());
        }
    }

    public function test_descansos_rotan_niveles_baja_media_alta_alta_en_4_semanas(): void
    {
        $svc = app(TurnoSemanalService::class);
        $progs = $this->programarRacha(2, 4);

        foreach ($this->meseros as $mesero) {
            $niveles = [];
            foreach ($progs as $prog) {
                $dia = $this->diaDescanso($prog->id, $mesero->id);
                $this->assertNotNull($dia, "Mesero {$mesero->name} sin descanso en semana {$prog->semana_iso}");
                $niveles[] = $svc->nivelDemanda($dia, $svc->obtenerReglasRotacion($this->sucursal->id));
            }
            sort($niveles);
            $this->assertEquals(['alta', 'alta', 'baja', 'media'], $niveles);
        }
    }

    public function test_offset_distinto_entre_meseros_en_la_misma_semana(): void
    {
        $svc = app(TurnoSemanalService::class);
        $reglas = $svc->obtenerReglasRotacion($this->sucursal->id);
        [$prog] = $this->programarRacha(2, 1);

        $niveles = [];
        foreach ($this->meseros as $mesero) {
            $niveles[] = $svc->nivelDemanda($this->diaDescanso($prog->id, $mesero->id), $reglas);
        }

        $this->assertContains('baja', $niveles);
        $this->assertContains('media', $niveles);
        $this->assertContains('alta', $niveles);
    }

    public function test_dias_alta_configurables(): void
    {
        $svc = app(TurnoSemanalService::class);
        $svc->guardarReglasRotacion($this->sucursal->id, ['dias_alta_demanda' => [6, 7]], $this->admin);

        $reglas = $svc->obtenerReglasRotacion($this->sucursal->id);
        $this->assertEquals([6, 7], $reglas['dias_alta_demanda']);
        $this->assertEquals('baja', $svc->nivelDemanda(5, $reglas));
        $this->assertEquals('alta', $svc->nivelDemanda(6, $reglas));
        $this->assertEquals('media', $svc->nivelDemanda(3, $reglas));
    }

    public function test_equilibrar_intercambia_descansos_para_compensar(): void
    {
        $svc = app(TurnoSemanalService::class);
        [$prog] = $this->programarRacha(2, 1);

        [$meseroA, $meseroB] = [$this->meseros[0], $this->meseros[1]];

        // Historial: A acumula 2 descansos en sábado (alta), B ninguno.
        // Semanas anteriores a la actual (offsets -2 y -1).
        for ($w = -2; $w <= -1; $w++) {
            $fecha = Carbon::today()->addWeeks($w);
            $hist = $svc->obtenerOCrearSemana($this->sucursal->id, (int) $fecha->isoWeek, (int) $fecha->isoWeekYear, $this->admin);
            $sabado = Carbon::now()->setISODate($hist->anio, $hist->semana_iso, 6)->toDateString();
            TurnoMeseroSemana::updateOrCreate(
                ['programacion_semanal_id' => $hist->id, 'user_id' => $meseroA->id, 'fecha' => $sabado],
                ['es_descanso' => true, 'zona_id' => null, 'plantilla_turno_id' => null]
            );
        }

        // Semana actual: A descansa sábado (alta), B descansa lunes (baja).
        $semana = Carbon::now()->setISODate($prog->anio, $prog->semana_iso);
        $sab = $semana->copy()->addDays(5)->toDateString();
        $lun = $semana->copy()->toDateString();
        TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)->where('user_id', $meseroA->id)->delete();
        TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)->where('user_id', $meseroB->id)->delete();
        foreach ([$meseroA, $meseroB] as $m) {
            for ($d = 0; $d < 7; $d++) {
                $fecha = $semana->copy()->addDays($d)->toDateString();
                $esDescanso = ($m->id === $meseroA->id && $fecha === $sab) || ($m->id === $meseroB->id && $fecha === $lun);
                TurnoMeseroSemana::create([
                    'programacion_semanal_id' => $prog->id,
                    'user_id' => $m->id,
                    'fecha' => $fecha,
                    'zona_id' => $esDescanso ? null : Zona::where('sucursal_id', $this->sucursal->id)->first()->id,
                    'es_descanso' => $esDescanso,
                ]);
            }
        }

        // Solo A y B participan: C y D trabajan toda la semana.
        $zonaCualquiera = Zona::where('sucursal_id', $this->sucursal->id)->first()->id;
        TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
            ->whereNotIn('user_id', [$meseroA->id, $meseroB->id])
            ->where('es_descanso', true)
            ->update(['es_descanso' => false, 'zona_id' => $zonaCualquiera]);

        $swaps = $svc->equilibrarDescansos($prog->id, $this->admin);

        $this->assertGreaterThanOrEqual(1, $swaps);
        $this->assertEquals(1, (int) TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
            ->where('user_id', $meseroA->id)->whereDate('fecha', $lun)->value('es_descanso'));
        $this->assertEquals(1, (int) TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
            ->where('user_id', $meseroB->id)->whereDate('fecha', $sab)->value('es_descanso'));
    }
}
