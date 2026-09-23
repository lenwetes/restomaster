<?php

namespace Tests\Unit\Services;

use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CajaServiceTest extends TestCase
{
    use RefreshDatabase;

    private CajaService $service;

    private Sucursal $sucursal;

    private User $cajero;

    private Caja $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CajaService::class);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Principal',
            'codigo' => 'SUC-01',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $roleCajero = Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero']);

        $this->cajero = User::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'role_id' => $roleCajero->id,
        ]);

        $this->caja = $this->service->crearCaja([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Barra 1',
            'codigo' => 'CAJA-01',
            'activa' => true,
        ], $this->cajero);
    }

    public function test_crear_y_actualizar_caja(): void
    {
        $this->assertInstanceOf(Caja::class, $this->caja);
        $this->assertSame('CAJA-01', $this->caja->codigo);

        $actualizada = $this->service->actualizarCaja($this->caja, [
            'nombre' => 'Caja Principal Modificada',
            'codigo' => 'CAJA-01-M',
            'activa' => true,
        ], $this->cajero);

        $this->assertSame('Caja Principal Modificada', $actualizada->fresh()->nombre);
        $this->assertSame('CAJA-01-M', $actualizada->fresh()->codigo);
    }

    public function test_alternar_estado_caja(): void
    {
        $this->assertTrue($this->caja->activa);
        $this->service->alternarEstadoCaja($this->caja, $this->cajero);
        $this->assertFalse($this->caja->fresh()->activa);

        $this->service->alternarEstadoCaja($this->caja, $this->cajero);
        $this->assertTrue($this->caja->fresh()->activa);
    }

    public function test_abrir_y_cerrar_turno_con_arqueo(): void
    {
        $turno = $this->service->abrirTurno(
            caja: $this->caja,
            cajero: $this->cajero,
            fondoInicial: 200000.0,
            notas: 'Apertura turno mañana'
        );

        $this->assertInstanceOf(TurnoCaja::class, $turno);
        $this->assertSame('abierto', $turno->estado);
        $this->assertEquals(200000.0, (float) $turno->monto_inicial);

        // Registrar un movimiento de egreso manual
        $mov = $this->service->registrarMovimiento(
            turno: $turno,
            tipo: 'egreso',
            monto: 25000.0,
            concepto: 'Compra de hielo de emergencia',
            metodoPago: 'efectivo',
            autorizadoPor: 'Gerente Turno',
            user: $this->cajero
        );

        $this->assertSame('egreso', $mov->tipo);
        $this->assertEquals(25000.0, (float) $mov->monto);

        // Cierre de turno con arqueo ciego
        $turnoFinalizado = $this->service->cerrarTurno(
            turno: $turno,
            montoRealEfectivo: 175000.0,
            cerradoPor: $this->cajero,
            notasCierre: 'Cierre sin novedades'
        );

        $this->assertSame('cerrado', $turnoFinalizado->fresh()->estado);
        $this->assertNotNull($turnoFinalizado->fresh()->cierre_en);
    }
}
