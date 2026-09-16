<?php

namespace App\Services;

use App\Models\CuentaPorPagar;
use App\Models\PagoCxp;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CuentasPorPagarService
{
    /**
     * Crea una cuenta por pagar con saldo completo pendiente.
     */
    public function crear(array $datos): CuentaPorPagar
    {
        $monto = (float) ($datos['monto_total'] ?? 0);

        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto total debe ser mayor a cero.');
        }

        return CuentaPorPagar::create([
            'proveedor_nombre' => $datos['proveedor_nombre'],
            'proveedor_nit' => $datos['proveedor_nit'] ?? null,
            'numero_factura' => $datos['numero_factura'] ?? null,
            'insumo_id' => $datos['insumo_id'] ?? null,
            'concepto' => $datos['concepto'],
            'monto_total' => $monto,
            'saldo_pendiente' => $monto,
            'fecha_emision' => $datos['fecha_emision'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
            'estado' => 'pendiente',
            'notas' => $datos['notas'] ?? null,
            'user_id' => $datos['user_id'] ?? auth()->id(),
        ]);
    }

    /**
     * Registra un pago (abono) sobre una cuenta y descuenta el saldo.
     */
    public function registrarPago(
        CuentaPorPagar $cuenta,
        float $monto,
        ?User $usuario = null,
        string $metodoPago = 'efectivo',
        ?string $concepto = null,
        ?string $comprobante = null,
        ?string $notas = null,
    ): PagoCxp {
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
        }

        $partesConcepto = array_filter([
            $concepto,
            $comprobante ? "Comprobante: {$comprobante}" : null,
            $notas ? "Notas: {$notas}" : null,
        ]);
        $conceptoFinal = ! empty($partesConcepto) ? implode(' | ', $partesConcepto) : null;

        return DB::transaction(function () use ($cuenta, $monto, $usuario, $metodoPago, $conceptoFinal) {
            $cuentaLocked = CuentaPorPagar::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();

            $saldo = (float) $cuentaLocked->saldo_pendiente;

            if ($monto > $saldo) {
                throw new InvalidArgumentException("El pago ({$monto}) no puede superar el saldo pendiente ({$saldo}).");
            }

            $nuevoSaldo = round($saldo - $monto, 2);
            $cuentaLocked->saldo_pendiente = $nuevoSaldo;
            if ($nuevoSaldo <= 0) {
                $cuentaLocked->estado = 'pagada';
            }
            $cuentaLocked->save();

            return PagoCxp::create([
                'cuenta_por_pagar_id' => $cuentaLocked->id,
                'user_id' => $usuario?->id ?? auth()->id(),
                'monto' => $monto,
                'metodo_pago' => $metodoPago,
                'fecha_pago' => now()->toDateString(),
                'concepto' => $conceptoFinal,
            ]);
        });
    }

    /**
     * Agrupa cuentas pendientes por proveedor (suma de saldos).
     */
    public function saldosPorProveedor(string $estado = 'pendiente'): Collection
    {
        return CuentaPorPagar::query()
            ->where('estado', $estado)
            ->selectRaw('proveedor_nombre, proveedor_nit, COUNT(*) AS cuentas, SUM(monto_total) AS monto_total, SUM(saldo_pendiente) AS saldo_pendiente')
            ->groupBy('proveedor_nombre', 'proveedor_nit')
            ->orderBy('proveedor_nombre')
            ->get();
    }
}
