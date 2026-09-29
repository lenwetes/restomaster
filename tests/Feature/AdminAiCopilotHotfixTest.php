<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\Ai\AdminAiCopilotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 1 — Hotfix Inmediato del Copiloto IA
 *
 * Cubre las 3 sub-tareas del plan:
 *   1.1 — Sin crash en ventas ($p puede ser array o Eloquent object)
 *   1.2 — Soporte de Egresos / Salidas de Base de Caja
 *   1.3 — Fallback NO repite el saludo de bienvenida; usa mensaje analítico
 */
class AdminAiCopilotHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Sucursal $sucursal;

    protected Caja $caja;

    protected TurnoCaja $turno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre'    => 'Sede Test Hotfix',
            'direccion' => 'Calle 1 # 1-1',
            'telefono'  => '3001234500',
            'activo'    => true,
        ]);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);

        $this->admin = User::factory()->create([
            'role_id'     => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'name'        => 'Admin Hotfix',
            'email'       => 'admin.hotfix@test.local',
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre'      => 'Caja Principal Hotfix',
            'codigo'      => 'CAJ-01',
            'tipo'        => 'principal',
            'activa'      => true,
        ]);

        $this->turno = TurnoCaja::create([
            'caja_id'       => $this->caja->id,
            'user_id'       => $this->admin->id,
            'apertura_en'   => now()->subHours(3),
            'monto_inicial' => 200000,
            'estado'        => 'abierto',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1.1 — Fix crash en ventas: $p puede ser array o Eloquent object
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * "ventas de hoy?" responde con KPIs y gráfico de barras por hora sin excepción.
     */
    public function test_ventas_de_hoy_responde_sin_crash_con_pedidos_y_top_platos(): void
    {
        Pedido::create([
            'codigo'      => 'PED-HOY-001',
            'tipo'        => 'mesa',
            'estado'      => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'subtotal'    => 85000,
            'descuento'   => 0,
            'total'       => 85000,
            'metodo_pago' => 'efectivo',
            'pagado_en'   => now(),
        ]);

        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('ventas de hoy?', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertStringContainsString('Estadísticas de Ventas de Hoy', $resultado['mensaje']);
        $this->assertArrayHasKey('kpis', $resultado['datos']);
        $this->assertArrayHasKey('grafico', $resultado['datos']);
        $this->assertEquals('bar', $resultado['datos']['grafico']['tipo']);
        $this->assertEquals(85000, $resultado['datos']['kpis']['ventas']);
    }

    /**
     * Con 0 pedidos "ventas de hoy?" no crashea (topProductos vacío = sin iteración).
     */
    public function test_ventas_de_hoy_sin_pedidos_no_genera_excepcion(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('ventas de hoy?', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertArrayHasKey('kpis', $resultado['datos']);
        $this->assertEquals(0.0, $resultado['datos']['kpis']['ventas']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1.2 — Soporte de Egresos / Salidas de Base de Caja
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * "cuánto dinero de la base ha salido" detecta la intención y responde con desglose.
     */
    public function test_consulta_egresos_detecta_intencion_y_responde_con_desglose(): void
    {
        MovimientoCaja::create([
            'turno_caja_id' => $this->turno->id,
            'user_id'       => $this->admin->id,
            'tipo'          => 'egreso',
            'concepto'      => 'Compra de servilletas',
            'monto'         => 15000,
        ]);

        MovimientoCaja::create([
            'turno_caja_id' => $this->turno->id,
            'user_id'       => $this->admin->id,
            'tipo'          => 'retiro',
            'concepto'      => 'Retiro parcial para banco',
            'monto'         => 50000,
        ]);

        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('cuánto dinero de la base ha salido', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertStringContainsString('Egresos y Retiros de Caja', $resultado['mensaje']);
        $this->assertStringContainsString('65.000', $resultado['mensaje']); // 15k + 50k
        $this->assertStringContainsString('Fondo Inicial Total', $resultado['mensaje']);
        $this->assertArrayHasKey('grafico', $resultado['datos']);
        $this->assertEquals('doughnut', $resultado['datos']['grafico']['tipo']);
        $this->assertEquals('/caja', $resultado['accion_rapida']['url']);
    }

    /**
     * "retiros de caja" activa la intención de egresos correctamente.
     */
    public function test_consulta_retiros_de_caja_activa_intencion_egresos(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('retiros de caja de hoy', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertStringContainsString('Egresos y Retiros de Caja', $resultado['mensaje']);
        $this->assertStringContainsString('No se registran egresos', $resultado['mensaje']);
    }

    /**
     * Keyword "egresos" activa la intención correcta.
     */
    public function test_consulta_egresos_sin_movimientos_informa_sin_salidas(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('egresos', $this->admin);

        $this->assertStringContainsString('Egresos y Retiros de Caja', $resultado['mensaje']);
        $this->assertStringContainsString('No se registran egresos', $resultado['mensaje']);
    }

    /**
     * El gráfico desglosa retiros vs egresos operativos con valores separados.
     */
    public function test_egresos_desglose_en_grafico_separa_retiros_y_egresos(): void
    {
        MovimientoCaja::create([
            'turno_caja_id' => $this->turno->id,
            'user_id'       => $this->admin->id,
            'tipo'          => 'retiro',
            'concepto'      => 'Retiro nocturno',
            'monto'         => 100000,
        ]);

        MovimientoCaja::create([
            'turno_caja_id' => $this->turno->id,
            'user_id'       => $this->admin->id,
            'tipo'          => 'egreso',
            'concepto'      => 'Pago proveedor',
            'monto'         => 30000,
        ]);

        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('movimientos de caja', $this->admin);

        $grafico = $resultado['datos']['grafico'];
        $this->assertContains('Retiros de Base', $grafico['etiquetas']);
        $this->assertContains('Egresos Operativos', $grafico['etiquetas']);

        $idxRetiros = array_search('Retiros de Base', $grafico['etiquetas']);
        $idxEgresos = array_search('Egresos Operativos', $grafico['etiquetas']);

        $this->assertEquals(100000, $grafico['valores'][$idxRetiros]);
        $this->assertEquals(30000, $grafico['valores'][$idxEgresos]);
    }

    /**
     * El saldo estimado nunca es negativo aunque los egresos superen el fondo inicial.
     */
    public function test_egresos_saldo_estimado_nunca_es_negativo(): void
    {
        // Fondo inicial = 200.000. Egreso = 250.000 (supera el fondo)
        MovimientoCaja::create([
            'turno_caja_id' => $this->turno->id,
            'user_id'       => $this->admin->id,
            'tipo'          => 'egreso',
            'concepto'      => 'Gasto extraordinario',
            'monto'         => 250000,
        ]);

        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $resultado = $copilot->procesarConsulta('cuánto se ha retirado de caja', $this->admin);

        $this->assertStringContainsString('Saldo Estimado en Base', $resultado['mensaje']);
        // El saldo formateado nunca debe mostrarse negativo
        $this->assertStringNotContainsString('-$', $resultado['mensaje']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1.3 — Fallback NO repite el saludo de bienvenida completo
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cuando la consulta no encaja con ninguna intención conocida, el fallback
     * NO devuelve el saludo de bienvenida. Devuelve el mensaje analítico guiado.
     */
    public function test_fallback_no_repite_saludo_de_bienvenida(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        // Texto sin ninguna intención reconocida (en entorno testing el LLM no responde)
        $resultado = $copilot->procesarConsulta('xyzzy nada que ver con el sistema', $this->admin);

        $this->assertEquals('orientacion', $resultado['tipo']);

        // NO debe contener el saludo de bienvenida antiguo
        $this->assertStringNotContainsString(
            'Hola, soy el Copiloto Ejecutivo IA de RestoMaster',
            $resultado['mensaje']
        );

        // SÍ debe contener el mensaje analítico de orientación
        $this->assertStringContainsString('No encontré datos sobre eso', $resultado['mensaje']);
        $this->assertStringContainsString('ventas de hoy', $resultado['mensaje']);
        $this->assertStringContainsString('egresos de caja', $resultado['mensaje']);
        $this->assertStringContainsString('insumos', $resultado['mensaje']);
        $this->assertStringContainsString('platos', $resultado['mensaje']);
        $this->assertStringContainsString('meseros', $resultado['mensaje']);
    }

    /**
     * Múltiples consultas desconocidas consecutivas nunca repiten el saludo completo.
     */
    public function test_fallback_no_repite_saludo_en_multiples_consultas_desconocidas(): void
    {
        /** @var AdminAiCopilotService $copilot */
        $copilot = app(AdminAiCopilotService::class);

        $consultas = [
            'quiero hablar con soporte técnico',
            'necesito configurar la impresora',
            'esto es una prueba aleatoria abc123',
        ];

        foreach ($consultas as $consulta) {
            $resultado = $copilot->procesarConsulta($consulta, $this->admin);

            if ($resultado['tipo'] === 'orientacion') {
                $this->assertStringNotContainsString(
                    'Hola, soy el Copiloto Ejecutivo IA de RestoMaster',
                    $resultado['mensaje'],
                    "La consulta '{$consulta}' devolvió el saludo de bienvenida completo"
                );
                $this->assertStringContainsString(
                    'No encontré datos sobre eso',
                    $resultado['mensaje'],
                    "La consulta '{$consulta}' no tiene el mensaje analítico de fallback"
                );
            }
        }
    }
}
