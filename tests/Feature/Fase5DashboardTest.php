<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase5DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);

        $sucursal = Sucursal::create(['nombre' => 'Sede', 'codigo' => 'MDE-01', 'direccion' => 'Calle', 'activa' => true]);
        Mesa::create(['sucursal_id' => $sucursal->id, 'numero' => 7, 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre', 'activa' => true]);

        $this->admin = User::factory()->create([
            'name' => 'Ad',
            'email' => 'a@t.com',
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Rolls', 'slug' => 'rolls', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
        $this->producto = Producto::create(['categoria_id' => $categoria->id, 'nombre' => 'Dragon', 'slug' => 'dragon', 'precio' => 50000, 'costo' => 20000, 'area_cocina' => 'sushi', 'activo' => true]);
    }

    private function pedidoEn(string $fecha): void
    {
        $pedido = Pedido::create([
            'codigo' => 'T-'.uniqid(),
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'usuario_id' => $this->admin->id,
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
    }

    public function test_dashboard_muestra_kpis_reales(): void
    {
        $this->pedidoEn(now()->toDateString());

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('50.000');
    }

    public function test_navegacion_incluye_modulos_fase5(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString(route('reservas'), $html);
        $this->assertStringContainsString(route('reportes'), $html);
        $this->assertStringContainsString(route('configuracion'), $html);
    }
}
