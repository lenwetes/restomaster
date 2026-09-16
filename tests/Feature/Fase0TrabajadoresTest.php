<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\TrabajadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase0TrabajadoresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $gerente;

    private TrabajadorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);
        $rolGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        $rolCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);

        $this->admin = User::factory()->create(['role_id' => $rolAdmin->id]);
        $this->gerente = User::factory()->create(['role_id' => $rolGerente->id]);
        $this->cajero = User::factory()->create(['role_id' => $rolCajero->id]);

        $this->sucursal = Sucursal::create(['nombre' => 'El Poblado MDE-01', 'activo' => true]);
        $this->sucursal2 = Sucursal::create(['nombre' => 'Laureles MED-02', 'activo' => true]);

        $this->service = app(TrabajadorService::class);
    }

    public function test_pantalla_trabajadores_solo_disponible_para_admin(): void
    {
        $this->actingAs($this->gerente)->get(route('trabajadores'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('trabajadores'))->assertOk();
        $this->actingAs($this->admin)->get(route('trabajadores'))->assertSeeVolt('trabajadores.index');
        $this->actingAs($this->admin)->get(route('trabajadores'))->assertSee($this->cajero->name);
    }

    public function test_crear_trabajador_asigna_rol_y_hash_de_password(): void
    {
        $user = $this->service->crear([
            'name' => 'Ana Mesera',
            'email' => 'ana@test.com',
            'password' => 'Secret-123',
            'telefono' => '3001234567',
            'role_id' => $this->cajero->role_id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ana Mesera',
            'email' => 'ana@test.com',
            'role_id' => $this->cajero->role_id,
            'activo' => true,
        ]);
        $this->assertNotEquals('Secret-123', $user->password);
        $this->assertTrue(Hash::check('Secret-123', $user->password));
    }

    public function test_crear_trabajador_rechaza_email_duplicado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->crear([
            'name' => 'Duplicado',
            'email' => $this->cajero->email,
            'password' => 'Secret-123',
            'role_id' => $this->cajero->role_id,
        ]);
    }

    public function test_desactivar_trabajador_con_historial_no_lo_elimina(): void
    {
        $this->service->desactivar($this->cajero);

        $this->assertSame(0, (int) $this->cajero->fresh()->activo);
        $this->assertDatabaseHas('users', ['id' => $this->cajero->id]);
    }

    public function test_admin_no_puede_desactivarse_a_si_mismo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->desactivar($this->admin);
    }

    public function test_editar_trabajador_actualiza_datos_y_rol(): void
    {
        $roleCajeroNuevo = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->service->actualizar($this->cajero, [
            'name' => 'Cajera Actualizada',
            'role_id' => $roleCajeroNuevo->id,
            'telefono' => '3115556677',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->cajero->id,
            'name' => 'Cajera Actualizada',
            'role_id' => $roleCajeroNuevo->id,
            'telefono' => '3115556677',
        ]);
    }

    public function test_componente_puede_abrir_modal_y_crear_desde_ui(): void
    {
        Volt::actingAs($this->admin)
            ->test('trabajadores.index')
            ->set('mostrarModalNuevo', true)
            ->set('nuevo.nombre', 'Mario Mesero')
            ->set('nuevo.email', 'mario@test.com')
            ->set('nuevo.password', 'Secret-123')
            ->set('nuevo.telefono', '3211112222')
            ->set('nuevo.role_id', $this->cajero->role_id)
            ->call('guardarNuevo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Mario Mesero', 'email' => 'mario@test.com']);
    }

    public function test_resetear_password_genera_clave_temporal_valida(): void
    {
        $claveTemporal = $this->service->resetearPassword($this->cajero);

        $this->assertGreaterThanOrEqual(10, strlen($claveTemporal));
        $this->assertTrue(Hash::check($claveTemporal, $this->cajero->fresh()->password));
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'trabajador.password_reseteado',
            'entidad' => 'usuario',
            'entidad_id' => $this->cajero->id,
        ]);
    }

    public function test_crear_trabajador_persiste_sucursal(): void
    {
        $user = $this->service->crear([
            'name' => 'Luis Sucursal',
            'email' => 'luis@test.com',
            'password' => 'Secret-123',
            'role_id' => $this->cajero->role_id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'sucursal_id' => $this->sucursal->id,
        ]);
    }

    public function test_actualizar_trabajador_cambia_sucursal(): void
    {
        $this->service->actualizar($this->cajero, [
            'sucursal_id' => $this->sucursal2->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->cajero->id,
            'sucursal_id' => $this->sucursal2->id,
        ]);
    }

    public function test_crear_trabajador_rechaza_sucursal_inexistente(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->crear([
            'name' => 'Sin Sucursal',
            'email' => 'sin@test.com',
            'password' => 'Secret-123',
            'role_id' => $this->cajero->role_id,
            'sucursal_id' => 999999,
        ]);
    }

    public function test_componente_crea_trabajador_con_sucursal_desde_ui(): void
    {
        Volt::actingAs($this->admin)
            ->test('trabajadores.index')
            ->set('mostrarModalNuevo', true)
            ->set('nuevo.nombre', 'Paula Sede')
            ->set('nuevo.email', 'paula@test.com')
            ->set('nuevo.telefono', '3119876543')
            ->set('nuevo.password', 'Secret-123')
            ->set('nuevo.role_id', $this->cajero->role_id)
            ->set('nuevo.sucursal_id', $this->sucursal->id)
            ->call('guardarNuevo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Paula Sede', 'email' => 'paula@test.com', 'sucursal_id' => $this->sucursal->id]);
    }

    public function test_componente_resetea_clave_y_la_muestra_una_sola_vez(): void
    {
        $componente = Volt::actingAs($this->admin)
            ->test('trabajadores.index')
            ->call('resetearClave', $this->cajero->id);

        $componente->assertSet('mostrarClaveTemporal', true)
            ->assertSet('clavePara', $this->cajero->name);

        $claveTemporal = $componente->get('claveTemporal');
        $this->assertNotNull($claveTemporal);
        $this->assertTrue(Hash::check($claveTemporal, $this->cajero->fresh()->password));
    }

    public function test_link_configuracion_perfiles_solo_visible_para_admin(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Configuración de Perfiles');
        $this->actingAs($this->gerente)->get(route('dashboard'))->assertOk()->assertDontSee('Configuración de Perfiles');
    }

    public function test_boton_nuevo_trabajador_queda_dentro_del_root_livewire(): void
    {
        $html = $this->actingAs($this->admin)->get(route('trabajadores'))->getContent();

        $posRaiz = strpos($html, 'wire:name="trabajadores.index"');
        $posBoton = strpos($html, 'wire:click="abrirModalNuevo"');
        $posMain = strpos($html, '<main');

        $this->assertNotFalse($posRaiz, 'No se encontró el root del componente Livewire.');
        $this->assertNotFalse($posBoton, 'No se encontró el botón Nuevo Trabajador.');
        $this->assertLessThan(
            $posBoton,
            $posRaiz,
            'El botón quedó fuera del root Livewire (en el slot header) y su wire:click no funciona.'
        );
        $this->assertLessThan(
            $posBoton,
            $posMain,
            'El botón quedó en la cabecera del layout y no dentro del contenido del componente.'
        );
    }
}
