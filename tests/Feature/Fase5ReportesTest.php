<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ReporteService;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase5ReportesTest extends TestCase
{
    use RefreshDatabase;

    private ReporteService $service;

    private Sucursal $sucursal;

    private Producto $producto;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);

        $this->sucursal = Sucursal::create(['nombre' => 'Sede', 'codigo' => 'MDE-01', 'direccion' => 'Calle', 'activa' => true]);
        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 7, 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre', 'activa' => true]);
        $this->admin = User::create(['name' => 'Ad', 'email' => 'a@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        $categoria = Categoria::create(['nombre' => 'Rolls', 'slug' => 'rolls', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
        $this->producto = Producto::create(['categoria_id' => $categoria->id, 'nombre' => 'Dragon', 'slug' => 'dragon', 'precio' => 50000, 'costo' => 20000, 'area_cocina' => 'sushi', 'activo' => true]);

        $this->service = app(ReporteService::class);
    }

    private function pedidoEn(string $fecha, string $tipo = 'mesa', ?User $usuario = null): Pedido
    {
        $pedido = Pedido::create([
            'codigo' => 'T-'.uniqid(),
            'tipo' => $tipo,
            'estado' => 'pagado',
            'usuario_id' => ($usuario ?? $this->admin)->id,
            'nombre_cliente' => 'Cliente Test',
            'subtotal' => 50000,
            'total' => 50000,
            'metodo_pago' => 'efectivo',
            'monto_pagado' => 50000,
            'pagado_en' => $fecha.' 13:00:00',
        ]);
        $pedido->forceFill(['created_at' => $fecha.' 12:00:00'])->save();

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 50000,
            'subtotal' => 50000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'entregado',
        ]);

        return $pedido;
    }

    public function test_kpis_realtime_computa_ventas_del_dia(): void
    {
        $this->pedidoEn(now()->toDateString(), 'mesa');
        $this->pedidoEn(now()->toDateString(), 'delivery');

        $kpis = $this->service->kpisRealtime();

        $this->assertSame(100000.0, (float) $kpis['ventas_dia']);
        $this->assertSame(2, $kpis['transacciones_dia']);
        $this->assertSame(50000.0, (float) $kpis['ticket_promedio']);
        $this->assertSame('Dragon', $kpis['top_productos_hoy'][0]['producto']);
        $this->assertSame(2, $kpis['top_productos_hoy'][0]['cantidad'] ?? 1);
    }

    public function test_kpis_realtime_food_cost_y_mesas_ocupadas(): void
    {
        $this->pedidoEn(now()->toDateString());
        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 8, 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'ocupada', 'activa' => true]);

        $kpis = $this->service->kpisRealtime();

        $this->assertSame(40.0, (float) $kpis['food_cost_porcentaje']); // 20000 / 50000
        $this->assertSame(1, $kpis['mesas_ocupadas']);
    }

    public function test_ventas_por_tipo_agrupa_canal(): void
    {
        $this->pedidoEn('2026-09-01', 'mesa');
        $this->pedidoEn('2026-09-02', 'delivery');

        $series = $this->service->ventasPorTipo('2026-09-01', '2026-09-30');

        $this->assertCount(2, $series);
        $this->assertSame(100000.0, (float) collect($series)->sum('ventas'));
    }

    public function test_ventas_por_producto_top(): void
    {
        $this->pedidoEn('2026-09-01');

        $top = $this->service->ventasPorProducto('2026-09-01', '2026-09-30', 5);

        $this->assertCount(1, $top);
        $this->assertSame('Dragon', $top[0]['producto']);
        $this->assertSame(50000.0, (float) $top[0]['ventas']);
        $this->assertSame(30000.0, (float) $top[0]['margen']); // 50000 - 20000
    }

    public function test_ventas_por_trabajador(): void
    {
        $this->pedidoEn('2026-09-01', 'mesa', $this->admin);

        $serie = $this->service->ventasPorTrabajador('2026-09-01', '2026-09-30');

        $this->assertCount(1, $serie);
        $this->assertSame('Ad', $serie[0]['trabajador']);
    }

    public function test_comparativa_periodos_con_periodo_anterior(): void
    {
        $this->pedidoEn('2026-09-05');
        $this->pedidoEn('2026-08-05');

        $cmp = $this->service->comparativaPeriodos('2026-09-01', '2026-09-30');

        $this->assertSame(50000.0, (float) $cmp['periodo_actual']['ventas']);
        $this->assertSame(50000.0, (float) $cmp['periodo_anterior']['ventas']);
        $this->assertSame(0.0, (float) $cmp['variacion_ventas']);
    }

    public function test_top_clientes(): void
    {
        $cliente = Cliente::create(['nombre' => 'Vip Uno', 'telefono' => '3001111', 'puntos_fidelidad' => 0, 'tier' => 'regular']);
        $pedido = $this->pedidoEn('2026-09-05', 'mesa');
        $pedido->update(['cliente_id' => $cliente->id]);

        $top = $this->service->topClientes('2026-09-01', '2026-09-30');

        $this->assertCount(1, $top);
        $this->assertSame('Vip Uno', $top[0]['cliente']);
        $this->assertSame(50000.0, (float) $top[0]['gastado']);
    }

    public function test_tiempos_entrega(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'D-1', 'tipo' => 'delivery', 'estado' => 'pagado',
            'usuario_id' => $this->admin->id, 'total' => 30000, 'costo_envio' => 5000,
            'hora_despacho' => '2026-09-01 12:30:00',
            'hora_entrega' => '2026-09-01 12:45:00', 'estado_delivery' => 'entregado',
            'pagado_en' => '2026-09-01 12:10:00',
        ]);
        $pedido->forceFill(['created_at' => '2026-09-01 12:00:00'])->save();

        $res = $this->service->tiemposEntrega('2026-09-01', '2026-09-30');

        $this->assertSame(45, $res['promedio_min']);
        $this->assertSame(1, $res['entregados']);
    }

    public function test_resumen_reservas_por_estado(): void
    {
        $svcReservas = app(ReservaService::class);
        $base = ['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'X', 'telefono_contacto' => '1', 'hora_llegada' => '13:00', 'personas' => 2, 'fecha' => '2026-09-10'];
        $r1 = $svcReservas->crear($base);
        $svcReservas->confirmar($r1);
        $r2 = $svcReservas->crear($base);
        $svcReservas->cancelar($r2);
        $r3 = $svcReservas->crear($base);
        $svcReservas->marcarNoShow($r3);

        $resumen = $this->service->resumenReservas('2026-09-01', '2026-09-30');

        $this->assertSame(3, $resumen['total']);
        $this->assertSame(1, $resumen['confirmadas']);
        $this->assertSame(1, $resumen['canceladas']);
        $this->assertSame(1, $resumen['no_shows']);
    }

    public function test_pantalla_reportes_renders_para_gerente(): void
    {
        $gerente = User::create(['name' => 'G', 'email' => 'g@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);

        $this->actingAs($gerente)->get(route('reportes'))->assertOk();
        $this->actingAs($gerente)->get(route('reportes'))->assertSeeVolt('reportes.index');
    }

    public function test_pestanas_reportes_muestran_tablas_detalladas(): void
    {
        $gerente = User::create(['name' => 'G2', 'email' => 'g2@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);
        $this->pedidoEn('2026-09-05');

        Volt::actingAs($gerente)
            ->test('reportes.index')
            ->set('desde', '2026-09-01')
            ->set('hasta', '2026-09-30')
            ->assertSee('Ventas netas')
            ->set('pestana', 'ventas')
            ->assertSee('Dragon')
            ->set('pestana', 'clientes')
            ->assertSee('Top clientes')
            ->set('pestana', 'estado')
            ->assertSee('Ventas netas');
    }

    public function test_pestana_reservas_muestra_resumen(): void
    {
        $gerente = User::create(['name' => 'G3', 'email' => 'g3@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);

        Volt::actingAs($gerente)
            ->test('reportes.index')
            ->set('pestana', 'reservas')
            ->assertSee('Cumplimiento')
            ->assertSee('No-shows');
    }

    public function test_export_pdf_devuelve_pdf(): void
    {
        $gerente = User::create(['name' => 'G4', 'email' => 'g4@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);
        $this->pedidoEn('2026-09-05');

        $response = $this->actingAs($gerente)->get(route('reportes.pdf', ['reporte' => 'ventas', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', $response->baseResponse->getContent());
    }

    public function test_export_csv_devuelve_csv_con_datos(): void
    {
        $gerente = User::create(['name' => 'G5', 'email' => 'g5@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);
        $this->pedidoEn('2026-09-05');

        $response = $this->actingAs($gerente)->get(route('reportes.csv', ['reporte' => 'ventas', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Dragon', $response->baseResponse->getContent());
    }

    public function test_export_restringido_a_gerente(): void
    {
        $mesero = User::create(['name' => 'M', 'email' => 'm@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'mesero')->value('id'), 'activo' => true]);

        $this->actingAs($mesero)->get(route('reportes.pdf', ['reporte' => 'ventas', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']))->assertForbidden();
    }
}
