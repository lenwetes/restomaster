<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\ImpresionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests RED — Remediación lote L3 (R8).
 *
 * Hallazgo: docs/auditoria/remediacion-seguridad-rendimiento-2026-09-15.md
 * Requisito: deben FALLAR en HEAD actual (el Reporte Z imprime $0.00) y
 * pasar tras mapear los campos reales de turnos_caja.
 */
class RemediacionReporteZTest extends TestCase
{
    use RefreshDatabase;

    public function test_r8_reporte_z_imprime_montos_reales_del_turno(): void
    {
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero', 'descripcion' => 'Cajero']);

        $sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $cajero = User::factory()->create([
            'name' => 'Cajero Principal',
            'email' => 'cajero@restomaster.com',
            'role_id' => $roleCajero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        // Turno con valores REALES de la tabla turnos_caja (schema vigente)
        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $cajero->id,
            'apertura_en' => now()->subHours(8),
            'cierre_en' => now(),
            'monto_inicial' => 200000,
            'total_ventas_efectivo' => 850000,
            'total_ventas_tarjeta' => 300000,
            'total_ventas_transferencia' => 150000,
            'total_egresos' => 50000,
            'total_retiros' => 100000,
            'monto_esperado_efectivo' => 900000,
            'monto_real_efectivo' => 905000,
            'diferencia' => 5000,
            'estado' => 'cerrado',
            'notas_cierre' => 'Cierre de prueba',
        ]);

        $texto = app(ImpresionService::class)->formatearReporteZTexto($turno);

        // Fondo inicial debe usar monto_inicial, no el inexistente monto_apertura
        $this->assertStringContainsString('$ 200,000.00', $texto, 'FONDO INICIAL imprimió 0.00 (campo monto_apertura inexistente).');

        // Total de ventas debe sumar efectivo + tarjeta + transferencia (1.300.000)
        $this->assertStringContainsString('$ 1,300,000.00', $texto, 'TOTAL VENTAS no sumó los tres medios de pago.');

        // Arqueo físico debe usar monto_real_efectivo, no el inexistente monto_cierre_real
        $this->assertStringContainsString('$ 905,000.00', $texto, 'ARQUEO FÍSICO imprimió vacío o 0.00 (campo monto_cierre_real inexistente).');

        // Diferencia entre arqueo y esperado
        $this->assertStringContainsString('$ 5,000.00', $texto, 'DIFERENCIA no aparece.');
    }

    public function test_r9_movimientos_ingreso_afectan_saldo_esperado_y_reporte_z(): void
    {
        $roleCajero = Role::create(['nombre' => 'Cajero Test', 'slug' => 'cajero_test', 'descripcion' => 'Cajero']);
        $sucursal = Sucursal::first() ?? Sucursal::create([
            'nombre' => 'RestoMaster Test',
            'codigo' => 'TST-01',
            'direccion' => 'Calle Test',
            'activa' => true,
        ]);

        $cajero = User::factory()->create([
            'role_id' => $roleCajero->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Ingresos Test',
            'codigo' => 'CAJ-ING',
            'activa' => true,
        ]);

        $cajaService = app(CajaService::class);
        $turno = $cajaService->abrirTurno($caja, $cajero, 100000);

        // Registrar un movimiento de ingreso extraordinario de 50.000
        $cajaService->registrarMovimiento(
            $turno,
            'ingreso',
            50000,
            'Ingreso extra base sencillo',
            'efectivo'
        );

        $turno->refresh();

        // total_ingresos debe ser 50.000 y monto esperado debe ser 150.000 (100.000 + 50.000)
        $this->assertEquals(50000.0, (float) $turno->total_ingresos);
        $this->assertEquals(150000.0, (float) $turno->monto_esperado_efectivo);

        // Reporte Z debe reflejar TOTAL INGRESOS: $ 50,000.00
        $texto = app(ImpresionService::class)->formatearReporteZTexto($turno);
        $this->assertStringContainsString('TOTAL INGRESOS (+):', $texto);
        $this->assertStringContainsString('$ 50,000.00', $texto);
        $this->assertStringContainsString('SALDO CALCULADO EN SISTEMA:', $texto);
        $this->assertStringContainsString('$ 150,000.00', $texto);
    }
}
