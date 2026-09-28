<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Encuesta;
use App\Models\Insumo;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\Ai\AdminAiCopilotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAiCopilotTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $mesero;

    protected Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Poblado Test',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001234567',
            'activo' => true,
        ]);

        $roleAdmin = Role::create([
            'nombre' => 'Administrador',
            'slug' => 'admin',
        ]);

        $roleMesero = Role::create([
            'nombre' => 'Mesero',
            'slug' => 'mesero',
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Admin Ejecutivo',
            'email' => 'admin.ejecutivo@restomaster.test',
        ]);

        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Mesero Juan',
            'email' => 'mesero.juan@restomaster.test',
        ]);
    }

    public function test_no_administrador_es_bloqueado_por_el_servicio_de_copiloto(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('muéstrame las ventas de hoy', $this->mesero);

        $this->assertEquals('error_autorizacion', $resultado['tipo']);
        $this->assertStringContainsString('Acceso denegado', $resultado['mensaje']);
    }

    public function test_administrador_puede_consultar_ventas_de_hoy_con_grafico(): void
    {
        // Registrar un pedido pagado hoy
        Pedido::create([
            'codigo' => 'PED-TEST-100',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 100000,
            'descuento' => 0,
            'total' => 100000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('muéstrame una estadística de ventas de hoy', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertStringContainsString('Estadísticas de Ventas de Hoy', $resultado['mensaje']);
        $this->assertArrayHasKey('kpis', $resultado['datos']);
        $this->assertEquals(100000, $resultado['datos']['kpis']['ventas']);
        $this->assertEquals('/reportes', $resultado['accion_rapida']['url']);
    }

    public function test_administrador_puede_consultar_inventario_e_insumos_criticos(): void
    {
        // Insumo crítico
        Insumo::create([
            'nombre' => 'Salmón Fresco Premium',
            'codigo' => 'SAL-01',
            'categoria' => 'Pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 1.5,
            'stock_minimo' => 10.0,
            'costo_unitario' => 45000,
            'proveedor_nombre' => 'Pescadería del Mar',
            'proveedor_telefono' => '+573110000000',
            'activo' => true,
        ]);

        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('qué insumos están críticos en inventario', $this->admin);

        $this->assertEquals('inventario', $resultado['tipo']);
        $this->assertStringContainsString('Auditoría de Insumos Críticos', $resultado['mensaje']);
        $this->assertStringContainsString('Salmón Fresco Premium', $resultado['mensaje']);
        $this->assertEquals('/inventario', $resultado['accion_rapida']['url']);
    }

    public function test_administrador_puede_generar_y_guardar_encuesta_de_satisfaccion(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('diseña una encuesta para clientes de delivery', $this->admin);

        $this->assertEquals('encuesta', $resultado['tipo']);
        $this->assertStringContainsString('Experiencia y Empaque en Delivery', $resultado['mensaje']);
        $this->assertEquals('guardar_encuesta', $resultado['accion_rapida']['accion']);

        // Guardar la encuesta
        $encuesta = $copilot->guardarEncuesta($resultado['accion_rapida']['payload'], $this->admin->sucursal_id);

        $this->assertDatabaseHas('encuestas', [
            'id' => $encuesta->id,
            'nombre' => 'Experiencia y Empaque en Delivery',
            'disparador' => 'post_delivery',
        ]);
    }

    public function test_administrador_puede_generar_y_guardar_propuesta_de_promocion(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('crea una promoción 2x1 en rolls para los martes', $this->admin);

        $this->assertEquals('promocion', $resultado['tipo']);
        $this->assertStringContainsString('2x1 en Rolls', $resultado['mensaje']);
        $this->assertEquals('guardar_promocion', $resultado['accion_rapida']['accion']);

        // Guardar la promoción
        $promo = $copilot->guardarPromocion($resultado['accion_rapida']['payload'], $this->admin->sucursal_id);

        $this->assertDatabaseHas('promociones', [
            'id' => $promo->id,
            'tipo_beneficio' => 'dos_por_uno',
            'activo' => true,
        ]);
    }

    public function test_componente_livewire_copilot_drawer_funciona_con_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin.copilot-drawer')
            ->assertSee('Copiloto Ejecutivo IA')
            ->call('enviarConsulta', 'ventas de hoy')
            ->assertSee('Estadísticas de Ventas de Hoy')
            ->assertSee('Facturación');
    }

    public function test_administrador_puede_consultar_ventas_en_caja_especifica(): void
    {
        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal #01 - Salón',
            'codigo' => 'CAJ-01',
            'tipo' => 'principal',
            'activa' => true,
        ]);

        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->admin->id,
            'apertura_en' => now()->subHours(4),
            'monto_inicial' => 100000,
            'estado' => 'abierto',
        ]);

        Pedido::create([
            'codigo' => 'PED-CAJ-101',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'turno_caja_id' => $turno->id,
            'subtotal' => 250000,
            'descuento' => 0,
            'total' => 250000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now()->subHour(),
        ]);

        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('ventas en caja 1', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertStringContainsString('Caja Principal #01', $resultado['mensaje']);
        $this->assertStringContainsString('250.000', $resultado['mensaje']);
        $this->assertArrayHasKey('grafico', $resultado['datos']);
        $this->assertEquals('bar', $resultado['datos']['grafico']['tipo']);
        $this->assertEquals('/caja', $resultado['accion_rapida']['url']);
    }

    public function test_administrador_puede_consultar_ventas_del_dia_martes_de_esta_semana(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('dame las ventas del dia martes de esta semana', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertStringContainsString('Martes', $resultado['mensaje']);
        $this->assertArrayHasKey('grafico', $resultado['datos']);
        $this->assertEquals('bar', $resultado['datos']['grafico']['tipo']);
        $this->assertContains('Mar', $resultado['datos']['grafico']['etiquetas']);
    }

    public function test_administrador_puede_consultar_desglose_de_metodos_de_pago(): void
    {
        Pedido::create([
            'codigo' => 'PED-MET-01',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 80000,
            'total' => 80000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        Pedido::create([
            'codigo' => 'PED-MET-02',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 120000,
            'total' => 120000,
            'metodo_pago' => 'tarjeta',
            'pagado_en' => now(),
        ]);

        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('cuánto se vendió en efectivo vs tarjeta', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertStringContainsString('Desglose de Métodos de Pago', $resultado['mensaje']);
        $this->assertArrayHasKey('grafico', $resultado['datos']);
        $this->assertEquals('doughnut', $resultado['datos']['grafico']['tipo']);
        $this->assertContains('Efectivo', $resultado['datos']['grafico']['etiquetas']);
        $this->assertContains('Tarjeta', $resultado['datos']['grafico']['etiquetas']);
    }

    public function test_administrador_puede_solicitar_infografia_ejecutiva(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('genera una infografía de estadísticas', $this->admin);

        $this->assertEquals('infografia', $resultado['tipo']);
        $this->assertStringContainsString('Infografía Ejecutiva Generada', $resultado['mensaje']);
        $this->assertArrayHasKey('infografia', $resultado['datos']);
        $this->assertEquals('/reportes', $resultado['accion_rapida']['url']);
    }
}
