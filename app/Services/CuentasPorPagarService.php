<?php

namespace App\Services;

use App\Models\CuentaPorPagar;
use App\Models\PagoCxp;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
    ): PagoCxp {
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
        }

        $saldo = (float) $cuenta->saldo_pendiente;

        if ($monto > $saldo) {
            throw new InvalidArgumentException("El pago ({$monto}) no puede superar el saldo pendiente ({$saldo}).");
        }

        $cuenta->saldo_pendiente = $saldo - $monto;
        if ($cuenta->saldo_pendiente == 0) {
            $cuenta->estado = 'pagada';
        }
        $cuenta->save();

        return PagoCxp::create([
            'cuenta_por_pagar_id' => $cuenta->id,
            'user_id' => $usuario?->id ?? auth()->id(),
            'monto' => $monto,
            'metodo_pago' => $metodoPago,
            'fecha_pago' => now()->toDateString(),
            'concepto' => $concepto,
        ]);
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
