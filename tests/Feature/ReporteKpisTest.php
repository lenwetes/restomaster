<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MenuService;
use App\Services\PedidoService;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteKpisTest extends TestCase
{
    use RefreshDatabase;

    public function test_picos_por_hora_agrupa_por_hora_local(): void
    {
        $s = Sucursal::create(['nombre' => 'KPI', 'codigo' => 'KPI', 'direccion' => 'x', 'activa' => true]);
        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin', 'descripcion' => 'A']);
        $admin = User::create([
            'name' => 'K',
            'email' => 'k@t.com',
            'password' => bcrypt('clave-segura-1'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $s->id,
            'activo' => true,
        ]);

        $menu = app(MenuService::class);
        $cat = $menu->crearCategoria(['nombre' => 'K', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
        $prod = $menu->crearProducto(['categoria_id' => $cat->id, 'nombre' => 'K', 'precio' => 1000, 'costo' => 100, 'area_cocina' => 'sushi', 'activo' => true]);

        // dos pedidos a las 09:00 y uno a las 21:00 hora Bogota
        foreach ([9, 21] as $hora) {
            $p = app(PedidoService::class)->crearPedido(
                ['tipo' => 'mesa', 'sucursal_id' => $s->id],
                [['producto_id' => $prod->id, 'cantidad' => 1]],
                $admin
            );
            $p->update(['estado' => 'pagado', 'pagado_en' => now()->startOfDay()->addHours($hora)]);
        }
        app(PedidoService::class)->crearPedido(
            ['tipo' => 'mesa', 'sucursal_id' => $s->id],
            [['producto_id' => $prod->id, 'cantidad' => 1]],
            $admin
        )->update(['estado' => 'pagado', 'pagado_en' => now()->startOfDay()->addHours(9)]);

        $kpis = app(ReporteService::class)->kpisRealtime($s->id);

        $this->assertSame(3, $kpis['transacciones_dia']);
        $horas = array_map('intval', array_keys($kpis['picos_por_hora']));
        sort($horas);
        $this->assertSame([9, 21], array_values($horas));
    }
}
