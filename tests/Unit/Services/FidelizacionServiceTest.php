<?php

namespace Tests\Unit\Services;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Services\FidelizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FidelizacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FidelizacionService $service;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FidelizacionService::class);

        $this->cliente = Cliente::create([
            'nombre' => 'Carlos Mendoza',
            'telefono' => '3001234567',
            'email' => 'carlos@example.com',
            'puntos_fidelidad' => 50,
            'activo' => true,
        ]);
    }

    /**
     * Test de cálculo de puntos según el monto consumido.
     */
    public function test_calcular_puntos_por_monto_genera_1_punto_por_cada_10k(): void
    {
        $this->assertEquals(0, $this->service->calcularPuntosPorMonto(9999.0));
        $this->assertEquals(1, $this->service->calcularPuntosPorMonto(10000.0));
        $this->assertEquals(5, $this->service->calcularPuntosPorMonto(58000.0));
    }

    /**
     * Test de acumulación de puntos al completar un pedido.
     */
    public function test_acumular_puntos_por_pedido_incrementa_saldo_del_cliente(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-FID-01',
            'cliente_id' => $this->cliente->id,
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'subtotal' => 80000,
            'total' => 80000,
        ]);

        $movimiento = $this->service->acumularPuntosPorPedido($pedido);

        $this->assertNotNull($movimiento);
        $this->assertEquals(8, $movimiento->puntos);
        $this->assertEquals('acumulacion', $movimiento->tipo);

        $this->cliente->refresh();
        $this->assertEquals(58, $this->cliente->puntos_fidelidad);
    }
}
