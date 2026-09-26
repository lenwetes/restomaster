<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\User;
use App\Services\PermisoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PermisosPrivilegiosTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioMesero(?string $email = null): User
    {
        $rol = Role::firstOrCreate(
            ['slug' => 'mesero'],
            ['nombre' => 'Mesero', 'descripcion' => 'Mesero']
        );

        return User::factory()->create([
            'role_id' => $rol->id,
            'email' => $email ?? fake()->unique()->safeEmail(),
        ]);
    }

    private function crearUsuarioAdmin(?string $email = null): User
    {
        $rol = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['nombre' => 'Admin', 'descripcion' => 'Admin']
        );

        return User::factory()->create([
            'role_id' => $rol->id,
            'email' => $email ?? fake()->unique()->safeEmail(),
        ]);
    }

    public function test_permiso_explicito_devuelve_grant_deny_o_null(): void
    {
        $mesero = $this->crearUsuarioMesero();

        $this->assertNull($mesero->permisoExplicito('pedidos.cobrar'));

        DB::table('permission_user')->insert([
            'user_id' => $mesero->id, 'permission' => 'pedidos.cobrar',
            'tipo' => 'deny', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mesero->olvidarPermisosMemo();

        $this->assertFalse($mesero->permisoExplicito('pedidos.cobrar'));
    }

    public function test_catalogo_cubre_todas_las_abilities_sin_scope(): void
    {
        $catalogo = array_keys(config('permisos.catalogo'));
        $excluidas = ['liquidarRepartidor', 'cocinar'];
        $modelos = [
            'PedidoPolicy' => 'Pedido', 'MesaPolicy' => 'Mesa',
            'CajaPolicy' => 'Caja', 'TurnoCajaPolicy' => 'TurnoCaja',
            'ClientePolicy' => 'Cliente', 'ReservaPolicy' => 'Reserva',
            'InsumoPolicy' => 'Insumo', 'CuentaPorPagarPolicy' => 'CuentaPorPagar',
            'ProveedorPolicy' => 'Proveedor', 'CompraPolicy' => 'Compra',
        ];

        foreach (glob(app_path('Policies/*.php')) as $archivo) {
            $policy = basename($archivo, '.php');
            if (! isset($modelos[$policy])) {
                continue;
            }
            $clase = 'App\\Policies\\'.$policy;
            foreach (get_class_methods($clase) as $metodo) {
                if (in_array($metodo, $excluidas, true)) {
                    continue;
                }
                $key = config('permisos.mapa')[$modelos[$policy]].'.'.Str::snake($metodo);
                // viewAny/view→ver, create→crear, update→actualizar, delete→eliminar:
                $key = str_replace(['.view_any', '.view', '.create', '.update', '.delete'], ['.ver', '.ver', '.crear', '.actualizar', '.eliminar'], $key);
                $this->assertContains($key, $catalogo, "Ability sin mapear: {$clase}::{$metodo}");
            }
        }
    }

    public function test_cada_rol_seed_tiene_plantilla_y_toda_key_existe(): void
    {
        $plantillas = config('permisos.plantillas');
        foreach (['admin', 'gerente', 'cajero', 'mesero', 'cocina', 'barra', 'delivery'] as $slug) {
            $this->assertArrayHasKey($slug, $plantillas, "Sin plantilla: {$slug}");
        }
        $catalogo = array_keys(config('permisos.catalogo'));
        foreach ($plantillas as $slug => $keys) {
            if ($keys === '*') {
                continue;
            }
            foreach ($keys as $key) {
                $this->assertContains($key, $catalogo, "Key fantasma en plantilla {$slug}: {$key}");
            }
        }
    }

    public function test_spot_checks_plantillas(): void
    {
        $plantillas = config('permisos.plantillas');
        $this->assertNotContains('pedidos.aplicar_descuento', $plantillas['mesero']);
        $this->assertNotContains('caja.eliminar', $plantillas['gerente']);
        $this->assertNotContains('caja.eliminar', $plantillas['cajero']);
        $this->assertContains('pedidos.cobrar', $plantillas['mesero']);
        $this->assertContains('turnos.abrir', $plantillas['cajero']);
        $this->assertContains('proveedores.crear', $plantillas['gerente']);
        $this->assertContains('compras.anular', $plantillas['gerente']);
        $this->assertNotContains('proveedores.crear', $plantillas['mesero']);
        $this->assertNotContains('compras.crear', $plantillas['cajero']);
    }

    public function test_resolver_mapea_ability_mas_modelo_y_null_si_no_mapea(): void
    {
        $svc = app(PermisoService::class);
        $this->assertSame('pedidos.cobrar', $svc->resolverKey('cobrar', new Pedido));
        $this->assertSame('caja.eliminar', $svc->resolverKey('delete', Caja::class));
        $this->assertNull($svc->resolverKey('cocinar', new Pedido));
        $this->assertNull($svc->resolverKey('liquidarRepartidor', new Pedido));
        $this->assertNull($svc->resolverKey('cobrar', null));
    }

    public function test_aplicar_plantilla_crea_grants_y_guardar_sincroniza_con_diff(): void
    {
        $svc = app(PermisoService::class);
        $mesero = $this->crearUsuarioMesero();

        $svc->aplicarPlantilla($mesero, 'mesero');
        $this->assertTrue((bool) $mesero->fresh()->permisoExplicito('pedidos.cobrar'));
        $this->assertNull($mesero->fresh()->permisoExplicito('caja.eliminar'));

        $diff = $svc->guardarChecks($mesero, [
            'pedidos.cobrar' => 'quitar',
            'caja.ver' => 'otorgar',
        ]);
        $this->assertSame(['pedidos.cobrar'], $diff['quitados']);
        $this->assertSame(['caja.ver'], $diff['otorgados']);
        $this->assertFalse((bool) $mesero->fresh()->permisoExplicito('pedidos.cobrar'));
        $this->assertTrue((bool) $mesero->fresh()->permisoExplicito('caja.ver'));
    }

    public function test_gate_deny_explicito_niega_aunque_el_rol_lo_permita(): void
    {
        $mesero = $this->crearUsuarioMesero();
        DB::table('permission_user')->insert([
            'user_id' => $mesero->id, 'permission' => 'pedidos.cobrar',
            'tipo' => 'deny', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($mesero->fresh()->can('cobrar', Pedido::class));
    }

    public function test_gate_grant_explicito_otorga_aunque_el_rol_no_lo_tenga(): void
    {
        $mesero = $this->crearUsuarioMesero();
        DB::table('permission_user')->insert([
            'user_id' => $mesero->id, 'permission' => 'pedidos.aplicar_descuento',
            'tipo' => 'grant', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($mesero->fresh()->can('aplicarDescuento', Pedido::class));
    }

    public function test_admin_guarda_permisos_con_preview_y_auditoria(): void
    {
        $admin = $this->crearUsuarioAdmin();
        $mesero = $this->crearUsuarioMesero();
        Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero', 'descripcion' => 'Cajero']);

        Volt::actingAs($admin)
            ->test('trabajadores.index')
            ->call('abrirModalPermisos', $mesero->id)
            ->set('permisosPlantillaRolId', Role::where('slug', 'cajero')->first()->id)
            ->call('aplicarPlantillaPermisos')
            ->call('cambiarCheck', 'pedidos.cobrar', 'quitar')
            ->call('guardarPermisos')
            ->assertDispatched('notificacion');

        $this->assertFalse((bool) $mesero->fresh()->permisoExplicito('pedidos.cobrar'));
        $this->assertTrue((bool) $mesero->fresh()->permisoExplicito('caja.ver'));
        $this->assertDatabaseHas('auditorias', ['accion' => 'usuarios.permisos_actualizados', 'entidad_id' => $mesero->id]);
    }

    public function test_no_admin_no_puede_guardar_permisos_403(): void
    {
        $mesero = $this->crearUsuarioMesero();
        $otro = $this->crearUsuarioMesero('otro@x.com');

        Volt::actingAs($mesero)
            ->test('trabajadores.index')
            ->call('abrirModalPermisos', $otro->id)
            ->assertForbidden();
    }

    public function test_admin_no_puede_editar_sus_propios_permisos_403(): void
    {
        $admin = $this->crearUsuarioAdmin();

        Volt::actingAs($admin)
            ->test('trabajadores.index')
            ->call('abrirModalPermisos', $admin->id)
            ->assertForbidden();
    }

    public function test_guardar_rechaza_key_fantasma(): void
    {
        $admin = $this->crearUsuarioAdmin();
        $mesero = $this->crearUsuarioMesero();

        Volt::actingAs($admin)
            ->test('trabajadores.index')
            ->call('abrirModalPermisos', $mesero->id)
            ->call('cambiarCheck', 'no.existe', 'otorgar')
            ->assertHasErrors('permisosChecks');
    }

    public function test_crear_usuario_aplica_plantilla_de_su_rol(): void
    {
        $admin = $this->crearUsuarioAdmin();
        $rolMesero = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero', 'descripcion' => 'Mesero']);

        Volt::actingAs($admin)
            ->test('trabajadores.index')
            ->set('nuevo.nombre', 'Mesero Nuevo')
            ->set('nuevo.email', 'nuevo@x.com')
            ->set('nuevo.telefono', '3001112233')
            ->set('nuevo.password', 'password')
            ->set('nuevo.role_id', $rolMesero->id)
            ->call('guardarNuevo')
            ->assertDispatched('notificacion');

        $creado = User::where('email', 'nuevo@x.com')->first();
        $this->assertTrue((bool) $creado->permisoExplicito('pedidos.cobrar'));
        $this->assertNull($creado->permisoExplicito('caja.eliminar'));
    }

    public function test_comando_verificar_lista_usuarios_con_permisos_explicitos(): void
    {
        $mesero = $this->crearUsuarioMesero();
        DB::table('permission_user')->insert([
            'user_id' => $mesero->id, 'permission' => 'caja.ver',
            'tipo' => 'grant', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('permisos:verificar')
            ->assertSuccessful()
            ->expectsOutputToContain($mesero->email);
    }
}
