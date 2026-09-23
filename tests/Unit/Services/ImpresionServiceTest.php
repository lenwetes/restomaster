<?php

namespace Tests\Unit\Services;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\ConfiguracionService;
use App\Services\ImpresionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpresionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ImpresionService $service;

    private Sucursal $sucursal;

    private Mesa $mesa;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ImpresionService::class);

        $this->sucursal = Sucursal::create([
            'nombre' => 'SushiXpress Provenza',
            'codigo' => 'PRV-01',
            'activa' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'Mesa-5',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'ocupada',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Combos Especiales',
            'slug' => 'combos-especiales',
            'activo' => true,
        ]);

        $this->producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Combo Roll 20 Piezas',
            'slug' => 'combo-roll-20-piezas',
            'precio' => 60000.0,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $svc = app(ConfiguracionService::class);
        $svc->guardar('ticket_80mm', 'nombre_comercial', 'SUSHIXPRESS RESTAURANTE');
        $svc->guardar('ticket_80mm', 'nit', '901.999.888-7');
        $svc->guardar('ticket_80mm', 'lema', 'Sushi & Cocina Nikkei');
        $svc->guardar('ticket_80mm', 'pie_pagina', '¡Gracias por su visita!');
    }

    /**
     * Test de formateo de ticket de venta térmico 80mm con parámetros dinámicos de configuración.
     */
    public function test_formatear_ticket_venta_incluye_datos_de_configuracion_y_totales(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-TICK-80',
            'mesa_id' => $this->mesa->id,
            'sucursal_id' => $this->sucursal->id,
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'subtotal' => 60000,
            'total' => 66000,
            'propina' => 6000,
            'metodo_pago' => 'efectivo',
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 60000,
            'subtotal' => 60000,
        ]);

        $ticket = $this->service->formatearTicketVentaTexto($pedido);

        $this->assertStringContainsString('SUSHIXPRESS RESTAURANTE', $ticket);
        $this->assertStringContainsString('901.999.888-7', $ticket);
        $this->assertStringContainsString('Combo Roll 20 Piezas', $ticket);
        $this->assertStringContainsString('60,000', $ticket);
        $this->assertStringContainsString('¡Gracias por su visita!', $ticket);
        $this->assertStringNotContainsString('ありがとうございます', $ticket);
    }
}
