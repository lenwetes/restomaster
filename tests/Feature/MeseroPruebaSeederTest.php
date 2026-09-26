<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Role;
use App\Models\RotacionMesero;
use App\Models\RotacionZona;
use App\Models\Sucursal;
use App\Models\TurnoMeseroZona;
use App\Models\User;
use App\Models\Zona;
use App\Services\RotacionMeseroService;
use Database\Seeders\MeseroPruebaSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SucursalSeeder;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeseroPruebaSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            SucursalSeeder::class,
            ZonaSeeder::class,
        ]);
    }

    public function test_mesero_prueba_seeder_creates_ten_colombian_waiters(): void
    {
        $this->seed(MeseroPruebaSeeder::class);

        $meseroRole = Role::where('slug', 'mesero')->first();
        $this->assertNotNull($meseroRole);

        $meseros = User::where('role_id', $meseroRole->id)->get();
        $this->assertGreaterThanOrEqual(10, $meseros->count());

        // Verificar formato de nombres y teléfonos colombianos (+57 3...)
        $colombianos = [
            'carlos.restrepo@restomaster.com' => '+57 310 456 7890',
            'valentina.morales@restomaster.com' => '+57 312 876 5432',
            'mateo.echeverry@restomaster.com' => '+57 315 234 5678',
            'daniela.ospina@restomaster.com' => '+57 320 987 1234',
            'juan.benitez@restomaster.com' => '+57 301 543 8901',
        ];

        foreach ($colombianos as $email => $tel) {
            $user = User::where('email', $email)->first();
            $this->assertNotNull($user, "Mesero {$email} debe existir.");
            $this->assertEquals($tel, $user->telefono);
            $this->assertTrue($user->activo);
            $this->assertStringStartsWith('+57 3', $user->telefono);
        }
    }

    public function test_mesero_prueba_seeder_configures_rotation_queues_by_zone(): void
    {
        $this->seed(MeseroPruebaSeeder::class);

        $zonas = ['salon', 'barra', 'terraza', 'vip'];

        foreach ($zonas as $slug) {
            $zona = Zona::where('slug', $slug)->first();
            $this->assertNotNull($zona, "Zona {$slug} debe existir.");

            $rotacion = RotacionZona::where('zona_id', $zona->id)->first();
            $this->assertNotNull($rotacion, "Rotación para zona {$slug} debe existir.");
            $this->assertTrue($rotacion->activa);
            $this->assertEquals('automatico', $rotacion->modo);

            $turnos = TurnoMeseroZona::where('zona_id', $zona->id)
                ->where('activo', true)
                ->orderBy('orden')
                ->get();

            $this->assertNotEmpty($turnos, "Zona {$slug} debe tener meseros en cola.");
            $this->assertEquals(1, $turnos->first()->orden, 'El primer mesero debe tener orden 1.');

            // Verificar sincronización histórica con RotacionMesero
            $rotacionesHistoricas = RotacionMesero::where('zona_slug', $slug)->get();
            $this->assertNotEmpty($rotacionesHistoricas);
        }
    }

    public function test_auto_rotation_cycles_waiters_round_robin(): void
    {
        $this->seed(MeseroPruebaSeeder::class);

        $zonaSalon = Zona::where('slug', 'salon')->first();
        $this->assertNotNull($zonaSalon);

        $service = app(RotacionMeseroService::class);

        $colaInicial = $service->obtenerColaPorZona($zonaSalon->id);
        $this->assertGreaterThanOrEqual(2, $colaInicial->count());

        $primerMeseroInicial = $colaInicial->first()->mesero_id;
        $segundoMeseroInicial = $colaInicial->get(1)->mesero_id;

        // Avanzar la rotación (simula asignación de comensal / mesa)
        $service->avanzarRotacion($zonaSalon->id);

        $colaNueva = $service->obtenerColaPorZona($zonaSalon->id);

        // El segundo ahora debe ser el primero
        $this->assertEquals(
            $segundoMeseroInicial,
            $colaNueva->first()->mesero_id,
            'La auto-rotación debe mover al siguiente mesero a la primera posición.'
        );

        // El que era primero ahora está al final de la cola
        $this->assertEquals(
            $primerMeseroInicial,
            $colaNueva->last()->mesero_id,
            'El mesero atendido debe rotar al final de la fila.'
        );
    }

    public function test_mesas_are_assigned_to_waiters_correctly(): void
    {
        $sucursal = Sucursal::first();

        // Crear mesas de prueba antes de ejecutar el seeder
        Mesa::firstOrCreate(
            ['sucursal_id' => $sucursal->id, 'numero' => 'Mesa Demo 101'],
            ['zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre']
        );

        $this->seed(MeseroPruebaSeeder::class);

        $mesa = Mesa::where('numero', 'Mesa Demo 101')->first();
        $this->assertNotNull($mesa);
        $this->assertNotNull($mesa->mesero_id, 'La mesa debe tener un mesero asignado.');
        $this->assertNotNull($mesa->mesero, 'La relación mesero debe retornar el usuario.');
        $this->assertStringStartsWith('+57 3', $mesa->mesero->telefono);
    }
}
