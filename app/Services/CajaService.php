<?php

namespace App\Services;

use App\Models\AsientoContable;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CajaService
{
    /**
     * Registrar una nueva caja física o terminal de cobro.
     */
    public function crearCaja(array $datos, ?User $usuario = null): Caja
    {
        $sucursalId = $datos['sucursal_id'] ?? Sucursal::value('id') ?? 1;

        $caja = Caja::create([
            'sucursal_id' => $sucursalId,
            'nombre' => trim($datos['nombre']),
            'codigo' => strtoupper(trim($datos['codigo'])),
            'activa' => $datos['activa'] ?? true,
        ]);

        if ($usuario) {
            app(AuditoriaService::class)->registrar(
                usuario: $usuario,
                accion: 'cajas.creada',
                entidad: 'caja',
                entidadId: $caja->id,
                descripcion: "Terminal de caja {$caja->nombre} ({$caja->codigo}) creada en el sistema.",
                datos: $caja->toArray()
            );
        }

        return $caja;
    }

    /**
     * Open a new shift for a cash register.
     */
    public function abrirTurno(Caja $caja, User $cajero, float $fondoInicial, ?string $notas = null): TurnoCaja
    {
        return DB::transaction(function () use ($caja, $cajero, $fondoInicial, $notas) {
            $turnoExistente = TurnoCaja::where('caja_id', $caja->id)
                ->where('estado', 'abierto')
                ->first();

            if ($turnoExistente) {
                throw new InvalidArgumentException("La caja {$caja->nombre} ya tiene un turno abierto (#{$turnoExistente->id}).");
            }

            $turno = TurnoCaja::create([
                'caja_id' => $caja->id,
                'user_id' => $cajero->id,
                'apertura_en' => now(),
                'monto_inicial' => $fondoInicial,
                'monto_esperado_efectivo' => $fondoInicial,
                'estado' => 'abierto',
                'notas_apertura' => $notas,
            ]);

            // Asiento contable de fondo inicial
            if ($fondoInicial > 0) {
                AsientoContable::create([
                    'fecha' => now()->toDateString(),
                    'tipo' => 'ingreso',
                    'cuenta' => 'caja_general',
                    'concepto' => "Fondo inicial de apertura turno #{$turno->id} ({$caja->nombre})",
                    'monto' => $fondoInicial,
                    'referencia_tipo' => 'apertura_turno',
                    'referencia_id' => $turno->id,
                    'user_id' => $cajero->id,
                ]);
            }

            return $turno;
        });
    }

    /**
     * Register a cash movement (expense, deposit, withdrawal).
     */
    public function registrarMovimiento(
        TurnoCaja $turno,
        string $tipo, // ingreso, egreso, retiro
        float $monto,
        string $concepto,
        string $metodoPago = 'efectivo',
        ?string $comprobante = null,
        ?string $autorizadoPor = null,
        ?User $user = null
    ): MovimientoCaja {
        if (! in_array($tipo, ['ingreso', 'egreso', 'retiro'], true)) {
            throw new InvalidArgumentException("Tipo de movimiento inválido: {$tipo}. Permitidos: ingreso, egreso, retiro.");
        }

        if (in_array($tipo, ['egreso', 'retiro'], true)) {
            if (empty(trim((string) $autorizadoPor))) {
                throw new InvalidArgumentException("Los movimientos de {$tipo} requieren autorización explícita.");
            }
            if ($user && strcasecmp(trim((string) $autorizadoPor), trim((string) $user->name)) === 0 && ! in_array($user->role?->slug, ['admin', 'gerente'])) {
                throw new InvalidArgumentException("Un cajero no puede auto-autorizarse un {$tipo}. Requiere autorización de un superior.");
            }
        }

        return DB::transaction(function () use ($turno, $tipo, $monto, $concepto, $metodoPago, $comprobante, $autorizadoPor, $user) {
            if ($turno->estado !== 'abierto') {
                throw new InvalidArgumentException('No se pueden registrar movimientos en un turno cerrado o cancelado.');
            }

            if ($monto <= 0) {
                throw new InvalidArgumentException('El monto del movimiento debe ser mayor a cero.');
            }

            $userId = $user ? $user->id : $turno->user_id;

            $movimiento = MovimientoCaja::create([
                'turno_caja_id' => $turno->id,
                'user_id' => $userId,
                'tipo' => $tipo,
                'concepto' => $concepto,
                'monto' => $monto,
                'metodo_pago' => $metodoPago,
                'numero_comprobante' => $comprobante,
                'autorizado_por' => $autorizadoPor,
            ]);

            // Actualizar acumuladores del turno
            if ($tipo === 'egreso') {
                $turno->total_egresos = (float) $turno->total_egresos + $monto;
            } elseif ($tipo === 'retiro') {
                $turno->total_retiros = (float) $turno->total_retiros + $monto;
            }

            $this->recalcularEsperado($turno);
            $turno->save();

            // Asiento contable correspondiente
            $cuenta = match ($tipo) {
                'egreso' => 'gastos_operativos',
                'retiro' => 'retiros_banco',
                'ingreso' => 'ingresos_extraordinarios',
                default => 'caja_general',
            };

            AsientoContable::create([
                'fecha' => now()->toDateString(),
                'tipo' => in_array($tipo, ['egreso', 'retiro']) ? 'gasto' : 'ingreso',
                'cuenta' => $cuenta,
                'concepto' => "Movimiento {$tipo}: {$concepto} (Turno #{$turno->id})",
                'monto' => $monto,
                'referencia_tipo' => 'movimiento_caja',
                'referencia_id' => $movimiento->id,
                'user_id' => $userId,
            ]);

            return $movimiento;
        });
    }

    /**
     * Link an order checkout payment to the shift and update sales and accounting.
     */
    public function vincularCobroPedido(TurnoCaja $turno, Pedido $pedido): void
    {
        DB::transaction(function () use ($turno, $pedido) {
            $pedido->update(['turno_caja_id' => $turno->id]);

            $metodo = strtolower($pedido->metodo_pago ?? 'efectivo');

            if ($metodo === 'efectivo') {
                $turno->total_ventas_efectivo = (float) $turno->total_ventas_efectivo + (float) $pedido->total;
            } elseif (in_array($metodo, ['tarjeta', 'tarjeta_credito', 'tarjeta_debito'])) {
                $turno->total_ventas_tarjeta = (float) $turno->total_ventas_tarjeta + (float) $pedido->total;
            } else {
                $turno->total_ventas_transferencia = (float) $turno->total_ventas_transferencia + (float) $pedido->total;
            }

            $this->recalcularEsperado($turno);
            $turno->save();

            // Registro automático en contabilidad
            AsientoContable::create([
                'fecha' => now()->toDateString(),
                'tipo' => 'ingreso',
                'cuenta' => 'ventas_restaurante',
                'concepto' => "Venta POS {$pedido->codigo} ({$metodo}) - Turno #{$turno->id}",
                'monto' => (float) $pedido->total,
                'referencia_tipo' => 'pedido',
                'referencia_id' => $pedido->id,
                'user_id' => $pedido->usuario_id ?? $turno->user_id,
            ]);
        });
    }

    /**
     * Recalculate the expected cash in the drawer.
     */
    public function recalcularEsperado(TurnoCaja $turno): void
    {
        $montoEsperado = (float) $turno->monto_inicial
            + (float) $turno->total_ventas_efectivo
            - (float) $turno->total_egresos
            - (float) $turno->total_retiros;

        $turno->monto_esperado_efectivo = max(0.0, $montoEsperado);
    }

    /**
     * Perform blind cash count, calculate differences, and close shift.
     */
    public function cerrarTurno(TurnoCaja $turno, float $montoRealEfectivo, User $cerradoPor, ?string $notasCierre = null): TurnoCaja
    {
        return DB::transaction(function () use ($turno, $montoRealEfectivo, $cerradoPor, $notasCierre) {
            if ($turno->estado !== 'abierto') {
                throw new InvalidArgumentException('El turno ya está cerrado o cancelado.');
            }

            $this->recalcularEsperado($turno);

            // Diferencia: Sobrante (+) o Faltante (-)
            $diferencia = $montoRealEfectivo - (float) $turno->monto_esperado_efectivo;

            $turno->update([
                'cierre_en' => now(),
                'monto_real_efectivo' => $montoRealEfectivo,
                'diferencia' => $diferencia,
                'estado' => 'cerrado',
                'cerrado_por_user_id' => $cerradoPor->id,
                'notas_cierre' => $notasCierre,
            ]);

            // Si hay descuadre contable, registrar ajuste
            if (abs($diferencia) > 0.01) {
                AsientoContable::create([
                    'fecha' => now()->toDateString(),
                    'tipo' => $diferencia > 0 ? 'ingreso' : 'gasto',
                    'cuenta' => $diferencia > 0 ? 'sobrante_caja' : 'faltante_caja',
                    'concepto' => "Ajuste por arqueo de caja turno #{$turno->id}: ".($diferencia > 0 ? 'Sobrante' : 'Faltante'),
                    'monto' => abs($diferencia),
                    'referencia_tipo' => 'arqueo_caja',
                    'referencia_id' => $turno->id,
                    'user_id' => $cerradoPor->id,
                ]);
            }

            // Despachar Reporte Z térmico a la impresora de caja
            app(ImpresionService::class)->despacharReporteZ($turno, $cerradoPor);

            return $turno;
        });
    }

    /**
     * Generate the complete Reporte Z (Fiscal summary) for a shift.
     */
    public function generarReporteZ(TurnoCaja $turno): array
    {
        $pedidos = $turno->pedidos()->where('estado', 'pagado')->get();

        $subtotal = $pedidos->sum('subtotal');
        $descuentos = $pedidos->sum('descuento');
        $totalVentas = $pedidos->sum('total');

        return [
            'turno_id' => $turno->id,
            'caja_nombre' => $turno->caja->nombre,
            'caja_codigo' => $turno->caja->codigo,
            'sucursal' => $turno->caja->sucursal->nombre,
            'cajero' => $turno->cajero->name,
            'cerrado_por' => $turno->cerradoPor?->name ?? 'N/A',
            'apertura' => $turno->apertura_en->format('d/m/Y H:i'),
            'cierre' => $turno->cierre_en?->format('d/m/Y H:i') ?? 'En curso',
            'fondo_inicial' => (float) $turno->monto_inicial,
            'total_transacciones' => $pedidos->count(),
            'subtotal_ventas' => (float) $subtotal,
            'total_descuentos' => (float) $descuentos,
            'total_ventas' => (float) $totalVentas,
            'desglose_pagos' => [
                'efectivo' => (float) $turno->total_ventas_efectivo,
                'tarjeta' => (float) $turno->total_ventas_tarjeta,
                'transferencia' => (float) $turno->total_ventas_transferencia,
            ],
            'total_egresos' => (float) $turno->total_egresos,
            'total_retiros' => (float) $turno->total_retiros,
            'monto_esperado' => (float) $turno->monto_esperado_efectivo,
            'monto_real' => (float) ($turno->monto_real_efectivo ?? 0.0),
            'diferencia' => (float) $turno->diferencia,
            'estado_cuadre' => abs((float) $turno->diferencia) < 0.01 ? 'CUADRADA' : ((float) $turno->diferencia > 0 ? 'SOBRANTE' : 'FALTANTE'),
            'movimientos' => $turno->movimientos()->get(),
        ];
    }
}
