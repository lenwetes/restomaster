<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReservasCalendarioMensualTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Sucursal $sucursal;

    private Mesa $mesa;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin', 'descripcion' => 'Admin']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Principal',
            'codigo' => 'SUC-01',
            'direccion' => 'Calle 100 #20',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Gerente General',
            'email' => 'gerente@restomaster.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 5,
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::LIBRE->value,
            'activa' => true,
        ]);
    }

    public function test_carga_inicial_en_modo_calendario_mensual(): void
    {
        $this->actingAs($this->admin);

        Volt::test('reservas.index')
            ->assertSet('modoVista', 'calendario')
            ->assertSet('mesActual', (int) now()->month)
            ->assertSet('anioActual', (int) now()->year)
            ->assertSee('Tablón Mensual')
            ->assertSee('Agenda del Día')
            ->assertSee('Reservas:')
            ->assertSee('Comensales:');
    }

    public function test_permite_alternar_entre_tablon_mensual_y_agenda_diaria(): void
    {
        $this->actingAs($this->admin);

        Volt::test('reservas.index')
            ->assertSet('modoVista', 'calendario')
            ->call('cambiarModoVista', 'diario')
            ->assertSet('modoVista', 'diario')
            ->call('cambiarModoVista', 'calendario')
            ->assertSet('modoVista', 'calendario');
    }

    public function test_navegacion_mes_anterior_siguiente_e_ir_a_hoy(): void
    {
        $this->actingAs($this->admin);

        $mesActual = (int) now()->month;
        $anioActual = (int) now()->year;

        $component = Volt::test('reservas.index');

        // Avanzar mes
        $component->call('mesSiguiente');
        $fechaSiguiente = Carbon::createFromDate($anioActual, $mesActual, 1)->addMonth();
        $component->assertSet('mesActual', (int) $fechaSiguiente->month)
            ->assertSet('anioActual', (int) $fechaSiguiente->year);

        // Retroceder mes dos veces
        $component->call('mesAnterior')->call('mesAnterior');
        $fechaAnterior = Carbon::createFromDate($anioActual, $mesActual, 1)->subMonth();
        $component->assertSet('mesActual', (int) $fechaAnterior->month)
            ->assertSet('anioActual', (int) $fechaAnterior->year);

        // Ir a hoy
        $component->call('irAHoy');
        $component->assertSet('mesActual', $mesActual)
            ->assertSet('anioActual', $anioActual);
    }

    public function test_calcula_kpis_mensuales_y_notificaciones_por_dia(): void
    {
        $this->actingAs($this->admin);

        $hoy = now();
        $fechaManana = now()->copy()->addDay()->toDateString();
        $fechaPasado = now()->copy()->addDays(2)->toDateString();

        // 1. Reserva confirmada para mañana (4 personas)
        $res1 = app(ReservaService::class)->crear([
            'nombre_contacto' => 'Carlos Gardel',
            'telefono_contacto' => '3001234567',
            'fecha' => $fechaManana,
            'hora_llegada' => '13:00',
            'personas' => 4,
            'mesa_ids' => [$this->mesa->id],
        ], 'sistema');
        app(ReservaService::class)->confirmar($res1, $this->admin, [$this->mesa->id]);

        // 2. Reserva solicitada (web) para pasado mañana (2 personas)
        app(ReservaService::class)->crear([
            'nombre_contacto' => 'Laura Restrepo',
            'telefono_contacto' => '3009876543',
            'fecha' => $fechaPasado,
            'hora_llegada' => '20:00',
            'personas' => 2,
        ], 'publico');

        $component = Volt::test('reservas.index')
            ->set('mesActual', (int) $hoy->month)
            ->set('anioActual', (int) $hoy->year);

        // Debe reflejar las reservas en las tarjetas de KPIs
        $component->assertSee('Reservas:')
            ->assertSee('Comensales:')
            ->call('seleccionarDia', $fechaManana)
            ->assertSee('Carlos Gardel')
            ->assertSee('4 personas');
    }

    public function test_seleccionar_dia_muestra_agenda_del_dia_e_inspeccion(): void
    {
        $this->actingAs($this->admin);

        $fechaPrueba = now()->copy()->addDays(3)->toDateString();

        $reserva = app(ReservaService::class)->crear([
            'nombre_contacto' => 'Familia Gómez',
            'telefono_contacto' => '3112223344',
            'fecha' => $fechaPrueba,
            'hora_llegada' => '14:30',
            'personas' => 5,
        ], 'sistema');

        Volt::test('reservas.index')
            ->call('seleccionarDia', $fechaPrueba)
            ->assertSet('diaSeleccionado', $fechaPrueba)
            ->assertSee('Familia Gómez')
            ->assertSee('14:30')
            ->assertSee('5 personas');
    }

    public function test_abrir_agenda_de_dia_conmuta_a_vista_diaria(): void
    {
        $this->actingAs($this->admin);

        $fechaPrueba = now()->copy()->addDays(4)->toDateString();

        Volt::test('reservas.index')
            ->call('abrirAgendaDeDia', $fechaPrueba)
            ->assertSet('modoVista', 'diario')
            ->assertSet('fecha', $fechaPrueba);
    }

    public function test_crear_reserva_con_fecha_precargada_desde_el_calendario(): void
    {
        $this->actingAs($this->admin);

        $fechaPrueba = now()->copy()->addDays(5)->toDateString();

        Volt::test('reservas.index')
            ->call('abrirCrearConFecha', $fechaPrueba)
            ->assertSet('modalCrear', true)
            ->assertSet('crearForm.fecha', $fechaPrueba)
            ->set('crearForm.nombre_contacto', 'Pedro Almodóvar')
            ->set('crearForm.telefono_contacto', '3201112233')
            ->set('crearForm.hora_llegada', '19:00')
            ->set('crearForm.personas', 3)
            ->call('crearReserva')
            ->assertHasNoErrors()
            ->assertSet('modalCrear', false);

        $this->assertDatabaseHas('reservas', [
            'nombre_contacto' => 'Pedro Almodóvar',
            'personas' => 3,
        ]);
        $this->assertTrue(Reserva::where('nombre_contacto', 'Pedro Almodóvar')->whereDate('fecha', $fechaPrueba)->exists());
    }

    public function test_calendario_renderiza_controles_superiores_identicos_a_referencia(): void
    {
        $this->actingAs($this->admin);

        // El nuevo diseño tipo tabla clásica tiene: navegación de mes, botón Hoy, Nueva Reserva y cabecera de días
        Volt::test('reservas.index')
            ->assertSee('Nueva reserva')
            ->assertSee('Hoy')
            ->assertSee('Reservas:')
            ->assertSee('LUNES')
            ->assertSee('MARTES')
            ->assertSee('MIÉRCOLES')
            ->assertSee('JUEVES')
            ->assertSee('VIERNES')
            ->assertSee('SÁBADO')
            ->assertSee('DOMINGO');
    }

    public function test_celda_activa_muestra_eventos_como_barras_de_color(): void
    {
        $this->actingAs($this->admin);

        $fechaManana = now()->copy()->addDay()->toDateString();

        app(ReservaService::class)->crear([
            'nombre_contacto' => 'Akira Kurosawa',
            'telefono_contacto' => '3119998877',
            'fecha' => $fechaManana,
            'hora_llegada' => '13:30',
            'personas' => 6,
        ], 'sistema');

        // El calendario tabla muestra los eventos como barras con hora y nombre
        Volt::test('reservas.index')
            ->assertSee('Akira Kurosawa')
            ->assertSee('13:30')
            ->assertSee('6p')               // total personas del día en el encabezado de la celda
            ->call('seleccionarDia', $fechaManana)
            ->assertSet('modalDiaOpen', true)
            ->assertSee('Agenda del')
            ->assertSee('Akira Kurosawa')
            ->assertSee('6 personas')
            ->call('cerrarModalDia')
            ->assertSet('modalDiaOpen', false);
    }
}
