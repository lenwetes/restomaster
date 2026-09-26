<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase5ReservasTest extends TestCase
{
    use RefreshDatabase;

    private ReservaService $service;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        Role::create(['nombre' => 'Cocina', 'slug' => 'cocina']);

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Medellín', 'codigo' => 'MDE-01', 'direccion' => 'Calle 10', 'activa' => true]);
        $this->service = app(ReservaService::class);
    }

    private function crearMesas(): array
    {
        $m1 = Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 1, 'zona' => 'salon', 'capacidad' => 2, 'estado' => MesaEstado::LIBRE->value, 'activa' => true]);
        $m4 = Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 2, 'zona' => 'salon', 'capacidad' => 6, 'estado' => MesaEstado::LIBRE->value, 'activa' => true]);

        return [$m1, $m4];
    }

    public function test_crear_reserva_interna(): void
    {
        [$m1, $m4] = $this->crearMesas();

        $reserva = $this->service->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Ana Torres',
            'telefono_contacto' => '3005551122',
            'fecha' => now()->toDateString(),
            'hora_llegada' => '13:00',
            'personas' => 4,
            'mesa_ids' => [$m4->id],
            'notas' => 'Aniversario',
        ], 'sistema');

        $this->assertSame('solicitada', $reserva->estado);
        $this->assertSame('sistema', $reserva->origen);
        $this->assertNotNull($reserva->token_publico);
        $this->assertCount(1, $reserva->mesas);
        $this->assertSame('Ana Torres', $reserva->nombre_contacto);
    }

    public function test_crear_rechaza_personas_cero_y_fecha_pasada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->crear([
            'nombre_contacto' => 'X', 'telefono_contacto' => '300',
            'fecha' => '2020-01-01', 'hora_llegada' => '13:00', 'personas' => 0,
        ]);
    }

    public function test_disponibilidad_devuelve_mesas_con_capacidad_sin_ocupadas(): void
    {
        [$m1, $m4] = $this->crearMesas();
        $ocupada = Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 3, 'zona' => 'barra', 'capacidad' => 4, 'estado' => MesaEstado::OCUPADA->value, 'activa' => true]);

        $disponibles = $this->service->verificarDisponibilidad(now()->toDateString(), '13:00', 2);

        $this->assertTrue($disponibles->contains('id', $m1->id));
        $this->assertTrue($disponibles->contains('id', $m4->id));
        $this->assertFalse($disponibles->contains('id', $ocupada->id));
    }

    public function test_confirmar_bloquea_mesa_y_rechaza_solapamiento(): void
    {
        [$m1, $m4] = $this->crearMesas();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        $reserva = $this->service->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Ana', 'telefono_contacto' => '300',
            'fecha' => now()->toDateString(), 'hora_llegada' => '13:00', 'personas' => 2,
            'mesa_ids' => [$m1->id],
        ]);

        $this->service->confirmar($reserva, $admin);

        $this->assertSame('confirmada', $reserva->fresh()->estado);
        $this->assertSame(MesaEstado::RESERVADA->value, $m1->fresh()->estado);

        $otra = $this->service->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Luis', 'telefono_contacto' => '301',
            'fecha' => now()->toDateString(), 'hora_llegada' => '13:30', 'personas' => 2,
            'mesa_ids' => [$m1->id],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->confirmar($otra, $admin);
    }

    public function test_ciclo_vida_completo_bloquea_y_libera_mesa(): void
    {
        [$m1] = $this->crearMesas();
        $reserva = $this->service->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Ana', 'telefono_contacto' => '300',
            'fecha' => now()->toDateString(), 'hora_llegada' => '13:00', 'personas' => 2,
            'mesa_ids' => [$m1->id],
        ]);

        $this->service->confirmar($reserva);
        $this->service->marcarLlego($reserva);
        $this->assertSame('llego', $reserva->fresh()->estado);
        $this->assertSame(MesaEstado::OCUPADA->value, $m1->fresh()->estado);

        $this->service->finalizar($reserva);
        $this->assertSame('finalizada', $reserva->fresh()->estado);
        $this->assertSame(MesaEstado::LIBRE->value, $m1->fresh()->estado);
    }

    public function test_cancelar_y_no_show_liberan_mesa(): void
    {
        [$m1] = $this->crearMesas();
        $r1 = $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'A', 'telefono_contacto' => '1', 'fecha' => now()->addDays(1)->toDateString(), 'hora_llegada' => '20:00', 'personas' => 2, 'mesa_ids' => [$m1->id]]);
        $this->service->confirmar($r1);
        $this->service->cancelar($r1);
        $this->assertSame('cancelada', $r1->fresh()->estado);
        $this->assertSame(MesaEstado::LIBRE->value, $m1->fresh()->estado);

        $r2 = $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'B', 'telefono_contacto' => '2', 'fecha' => now()->addDays(2)->toDateString(), 'hora_llegada' => '20:00', 'personas' => 2, 'mesa_ids' => [$m1->id]]);
        $this->service->confirmar($r2);
        $this->service->marcarNoShow($r2);
        $this->assertSame('no_mostro', $r2->fresh()->estado);
        $this->assertSame(MesaEstado::LIBRE->value, $m1->fresh()->estado);
    }

    public function test_reservas_del_dia_filtra_fecha(): void
    {
        $this->crearMesas();
        $hoy = now()->toDateString();
        $manana = now()->addDays(1)->toDateString();
        $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'A', 'telefono_contacto' => '1', 'fecha' => $hoy, 'hora_llegada' => '13:00', 'personas' => 2]);
        $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'B', 'telefono_contacto' => '2', 'fecha' => $manana, 'hora_llegada' => '13:00', 'personas' => 2]);

        $dia = $this->service->reservasDelDia($hoy);
        $this->assertCount(1, $dia);
        $this->assertSame('A', $dia->first()->nombre_contacto);
    }

    public function test_auditoria_registra_reserva_creada(): void
    {
        $this->crearMesas();
        $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'A', 'telefono_contacto' => '1', 'fecha' => now()->toDateString(), 'hora_llegada' => '13:00', 'personas' => 2]);

        $this->assertDatabaseHas('auditorias', ['accion' => 'reserva.creada', 'entidad' => 'reserva']);
    }

    public function test_rbac_ruta_reservas(): void
    {
        $mesero = User::create(['name' => 'M', 'email' => 'm@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'mesero')->value('id'), 'activo' => true]);
        $cocina = User::create(['name' => 'C', 'email' => 'c@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'cocina')->value('id'), 'activo' => true]);

        $this->actingAs($mesero)->get(route('reservas'))->assertOk();
        $this->actingAs($cocina)->get(route('reservas'))->assertForbidden();
    }

    public function test_pantalla_reservas_renders(): void
    {
        $admin = User::create(['name' => 'Ad', 'email' => 'ad@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);
        $this->actingAs($admin)->get(route('reservas'))->assertOk();
        $this->actingAs($admin)->get(route('reservas'))->assertSeeVolt('reservas.index');
        $this->actingAs($admin)->get(route('reservas'))->assertSee('RES-01');
    }

    public function test_agenda_muestra_reservas_del_dia(): void
    {
        $this->crearMesas();
        $hoy = now()->toDateString();
        $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'Clara Estrada', 'telefono_contacto' => '300', 'fecha' => $hoy, 'hora_llegada' => '13:00', 'personas' => 2]);

        $admin = User::create(['name' => 'Ad', 'email' => 'ad2@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        Volt::actingAs($admin)
            ->test('reservas.index')
            ->set('fecha', $hoy)
            ->assertSee('Clara Estrada');
    }

    public function test_confirmar_desde_ui_bloquea_mesa(): void
    {
        [$m1] = $this->crearMesas();
        $hoy = now()->toDateString();
        $reserva = $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'Ana', 'telefono_contacto' => '300', 'fecha' => $hoy, 'hora_llegada' => '13:00', 'personas' => 2, 'mesa_ids' => [$m1->id]]);
        $admin = User::create(['name' => 'Ad', 'email' => 'ad3@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        Volt::actingAs($admin)
            ->test('reservas.index')
            ->set('fecha', $hoy)
            ->set('reservaSeleccionada', $reserva->id)
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertSame('confirmada', $reserva->fresh()->estado);
        $this->assertSame(MesaEstado::RESERVADA->value, $m1->fresh()->estado);
    }

    public function test_boton_abrir_crear_despliega_modal_en_ui(): void
    {
        $this->crearMesas();
        $admin = User::create(['name' => 'Ad', 'email' => 'ad4@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        Volt::actingAs($admin)
            ->test('reservas.index')
            ->assertDontSee('Nombre del cliente')
            ->call('abrirCrear')
            ->assertSet('modalCrear', true)
            ->assertSee('Nombre del cliente')
            ->assertSee('Crear reserva');
    }
}
