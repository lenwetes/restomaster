<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Ai\AdminAiCopilotService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 9.2 — Análisis con IA estructurado + PDF ejecutivo con acceso por rol.
 */
class CopilotoReportesIntegracionTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    protected User $gerente;

    protected User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede IA Reportes Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'admin.iarep@test.local',
        ]);
        $this->gerente = User::factory()->create([
            'role_id' => $roleGerente->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'gerente.iarep@test.local',
        ]);
        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'mesero.iarep@test.local',
        ]);

        Pedido::create([
            'codigo' => 'REP-IA-001', 'tipo' => 'mesa', 'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id, 'subtotal' => 200000, 'total' => 200000,
            'metodo_pago' => 'efectivo', 'pagado_en' => Carbon::create(2026, 9, 10, 13),
        ]);
        Pedido::create([
            'codigo' => 'REP-IA-002', 'tipo' => 'mesa', 'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id, 'subtotal' => 100000, 'total' => 100000,
            'metodo_pago' => 'tarjeta', 'pagado_en' => Carbon::create(2026, 9, 5, 13),
        ]);
    }

    public function test_analizar_reporte_devuelve_estructura_ejecutiva(): void
    {
        $analisis = app(AdminAiCopilotService::class)->analizarReporte([
            'desde' => '2026-09-10',
            'hasta' => '2026-09-10',
            'comparar' => true,
            'desde_b' => '2026-09-05',
            'hasta_b' => '2026-09-05',
        ], $this->gerente);

        $this->assertEquals('reporte_ia', $analisis['tipo']);
        $this->assertNotEmpty($analisis['mensaje']);
        $this->assertContains($analisis['datos']['tendencia'], ['crecimiento', 'meseta', 'caída']);
        $this->assertCount(3, $analisis['datos']['recomendaciones']);
        $this->assertArrayHasKey('infografia', $analisis['datos']);
        $this->assertArrayHasKey('ventas', $analisis['datos']['infografia']);
    }

    public function test_analizar_reporte_detecta_crecimiento(): void
    {
        $analisis = app(AdminAiCopilotService::class)->analizarReporte([
            'desde' => '2026-09-10',
            'hasta' => '2026-09-10',
            'comparar' => true,
            'desde_b' => '2026-09-05',
            'hasta_b' => '2026-09-05',
        ], $this->gerente);

        $this->assertEquals('crecimiento', $analisis['datos']['tendencia']);
        $this->assertEquals(100.0, $analisis['datos']['delta_ventas_pct']);
    }

    public function test_analizar_reporte_requiere_admin_o_gerente(): void
    {
        $this->expectException(AuthorizationException::class);
        app(AdminAiCopilotService::class)->analizarReporte(['desde' => '2026-09-10', 'hasta' => '2026-09-10'], $this->mesero);
    }

    public function test_informe_pdf_accesible_para_admin_y_gerente(): void
    {
        $this->actingAs($this->admin)
            ->get('/reportes/informe-ejecutivo?desde=2026-09-10&hasta=2026-09-10')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->gerente)
            ->get('/reportes/informe-ejecutivo?desde=2026-09-10&hasta=2026-09-10')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_informe_pdf_bloqueado_para_otros_roles(): void
    {
        $this->actingAs($this->mesero)
            ->get('/reportes/informe-ejecutivo?desde=2026-09-10&hasta=2026-09-10')
            ->assertForbidden();
    }

    public function test_informe_pdf_valida_rango(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/reportes/informe-ejecutivo?desde=2026-09-10&hasta=2026-09-01')
            ->assertStatus(422);
    }
}
