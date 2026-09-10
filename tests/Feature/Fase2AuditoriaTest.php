<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\MenuService;
use App\Services\TrabajadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase2AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private AuditoriaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        $this->gerente = User::factory()->create(['role_id' => Role::where('slug', 'gerente')->value('id')]);

        $this->service = app(AuditoriaService::class);
    }

    public function test_registrar_auditoria_guarda_usuario_accion_y_datos(): void
    {
        $this->service->registrar(
            usuario: $this->gerente,
            accion: 'categoria.creada',
            entidad: 'categoria',
            entidadId: 1,
            descripcion: 'Se creó la categoría Entradas',
            datos: ['nombre' => 'Entradas', 'orden' => 3],
        );

        $this->assertDatabaseHas('auditorias', [
            'user_id' => $this->gerente->id,
            'accion' => 'categoria.creada',
            'entidad' => 'categoria',
            'entidad_id' => 1,
            'descripcion' => 'Se creó la categoría Entradas',
        ]);

        $auditoria = Auditoria::first();
        $this->assertSame(['nombre' => 'Entradas', 'orden' => 3], $auditoria->datos);
    }

    public function test_registrar_auditoria_sin_usuario_es_valida(): void
    {
        $this->service->registrar(accion: 'sistema.start', entidad: 'sistema');

        $this->assertDatabaseHas('auditorias', [
            'accion' => 'sistema.start',
            'entidad' => 'sistema',
            'user_id' => null,
        ]);
    }

    public function test_por_entidad_filtra_solo_esa_entidad(): void
    {
        $this->service->registrar(usuario: $this->gerente, accion: 'producto.creado', entidad: 'producto', entidadId: 10);
        $this->service->registrar(usuario: $this->gerente, accion: 'categoria.creada', entidad: 'categoria', entidadId: 2);

        $productos = $this->service->porEntidad('producto', 10);

        $this->assertCount(1, $productos);
        $this->assertSame('producto.creado', $productos->first()->accion);
    }

    public function test_menu_service_registra_auditoria_de_categoria(): void
    {
        $this->actingAs($this->gerente);

        app(MenuService::class)->crearCategoria(['nombre' => 'Entradas', 'icono' => '🥟', 'orden' => 1]);

        $this->assertDatabaseHas('auditorias', [
            'user_id' => $this->gerente->id,
            'accion' => 'categoria.creada',
            'entidad' => 'categoria',
        ]);
        $this->assertDatabaseHas('categorias', ['nombre' => 'Entradas']);
    }

    public function test_trabajador_service_registra_auditoria_de_creacion(): void
    {
        $this->actingAs($this->gerente);

        $rolMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        app(TrabajadorService::class)->crear([
            'name' => 'Nuevo Mesero',
            'email' => 'nuevo@test.com',
            'password' => 'Secret-123',
            'role_id' => $rolMesero->id,
        ]);

        $this->assertDatabaseHas('auditorias', [
            'user_id' => $this->gerente->id,
            'accion' => 'trabajador.creado',
            'entidad' => 'usuario',
        ]);
    }

    public function test_inventario_tiene_consulta_por_tipo(): void
    {
        $this->service->registrar(usuario: $this->gerente, accion: 'merma.registrada', entidad: 'insumo', entidadId: 5);
        $this->service->registrar(usuario: $this->gerente, accion: 'compra.registrada', entidad: 'insumo', entidadId: 5);

        $this->assertSame(2, $this->service->porEntidad('insumo', 5)->count());
        $this->assertSame(1, $this->service->porAccion('merma.registrada')->count());
    }
}
