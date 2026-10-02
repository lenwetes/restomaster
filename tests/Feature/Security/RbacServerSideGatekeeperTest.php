<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacServerSideGatekeeperTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $roles = ['admin', 'gerente', 'cajero', 'mesero', 'cocina', 'barra', 'repartidor'];

        foreach ($roles as $slug) {
            $role = Role::firstOrCreate(['slug' => $slug], ['nombre' => ucfirst($slug)]);
            $this->users[$slug] = User::create([
                'name' => "Usuario {$slug}",
                'email' => "{$slug}@restomaster.com",
                'password' => bcrypt('password'),
                'role_id' => $role->id,
                'sucursal_id' => $this->sucursal->id,
                'is_active' => true,
            ]);
        }
    }

    public function test_usuario_no_autenticado_es_redirigido_al_login_en_rutas_privadas(): void
    {
        $rutasProtegidas = ['/dashboard', '/pos', '/cocina', '/caja', '/inventario', '/reportes', '/configuracion'];

        foreach ($rutasProtegidas as $ruta) {
            $response = $this->get($ruta);
            $response->assertRedirect('/login');
        }
    }

    public function test_mesero_no_puede_acceder_a_configuracion_ni_reportes(): void
    {
        $responseConfig = $this->actingAs($this->users['mesero'])->get('/configuracion');
        $this->assertContains($responseConfig->status(), [403, 302]);

        $responseReportes = $this->actingAs($this->users['mesero'])->get('/reportes');
        $this->assertContains($responseReportes->status(), [403, 302]);
    }

    public function test_cocina_no_puede_acceder_a_caja_ni_a_turnos(): void
    {
        $responseCaja = $this->actingAs($this->users['cocina'])->get('/caja');
        $this->assertContains($responseCaja->status(), [403, 302]);

        $responseTurnos = $this->actingAs($this->users['cocina'])->get('/turnos');
        $this->assertContains($responseTurnos->status(), [403, 302]);
    }

    public function test_gerente_y_admin_tienen_acceso_a_turnos_semanales(): void
    {
        $responseAdmin = $this->actingAs($this->users['admin'])->get('/turnos');
        $responseAdmin->assertOk();

        $responseGerente = $this->actingAs($this->users['gerente'])->get('/turnos');
        $responseGerente->assertOk();
    }

    public function test_cajero_y_mesero_tienen_acceso_a_pos(): void
    {
        $responseMesero = $this->actingAs($this->users['mesero'])->get('/pos');
        $responseMesero->assertOk();

        $responseCajero = $this->actingAs($this->users['cajero'])->get('/pos');
        $responseCajero->assertOk();
    }
}
