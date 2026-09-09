<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FidelizacionService
{
    public const VALOR_PUNTO_COP = 10.0; // 1 punto = $10 COP de descuento
    public const CONSUMO_POR_PUNTO = 10000.0; // Cada $10.000 COP genera 1 punto

    /**
     * Calcula cuántos puntos genera un pedido pagado.
     */
    public function calcularPuntosPorMonto(float $monto): int
    {
        return (int) floor($monto / self::CONSUMO_POR_PUNTO);
    }

    /**
     * Calcula el valor en dinero de un paquete de puntos.
     */
    public function calcularDescuentoPorPuntos(int $puntos): float
    {
        return (float) ($puntos * self::VALOR_PUNTO_COP);
    }

    /**
     * Acumula puntos para el cliente al pagar un pedido.
     */
    public function acumularPuntosPorPedido(Pedido $pedido): ?MovimientoPuntos
    {
        if (! $pedido->cliente_id) {
            return null;
        }

        $cliente = $pedido->cliente;
        if (! $cliente || ! $cliente->activo) {
            return null;
        }

        $puntosAGanar = $this->calcularPuntosPorMonto((float) $pedido->total);
        if ($puntosAGanar <= 0) {
            return null;
        }

        return DB::transaction(function () use ($pedido, $cliente, $puntosAGanar) {
            $saldoAnterior = $cliente->puntos_fidelidad;
            $saldoNuevo = $saldoAnterior + $puntosAGanar;

            $movimiento = MovimientoPuntos::create([
                'cliente_id' => $cliente->id,
                'pedido_id' => $pedido->id,
                'tipo' => 'acumulacion',
                'puntos' => $puntosAGanar,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'concepto' => "Consumo pedido #{$pedido->codigo}",
                'usuario_id' => $pedido->usuario_id,
            ]);

            $nuevoTotalGastado = (float) $cliente->total_gastado + (float) $pedido->total;
            $visitas = $cliente->visitas_count + 1;

            // Actualización de Tier automática
            $nuevoTier = $cliente->tier;
            if ($nuevoTotalGastado >= 5000000) {
                $nuevoTier = 'black';
            } elseif ($nuevoTotalGastado >= 2000000) {
                $nuevoTier = 'vip';
            } elseif ($nuevoTotalGastado >= 800000) {
                $nuevoTier = 'gold';
            }

            $cliente->update([
                'puntos_fidelidad' => $saldoNuevo,
                'total_gastado' => $nuevoTotalGastado,
                'visitas_count' => $visitas,
                'tier' => $nuevoTier,
            ]);

            $pedido->update(['puntos_ganados' => $puntosAGanar]);

            return $movimiento;
        });
    }

    /**
     * Canjea puntos del cliente para aplicar un descuento directo al pedido.
     */
    public function canjearPuntos(Cliente $cliente, int $puntos, Pedido $pedido): MovimientoPuntos
    {
        if ($puntos <= 0) {
            throw new InvalidArgumentException('La cantidad de puntos a canjear debe ser mayor a cero.');
        }

        if ($cliente->puntos_fidelidad < $puntos) {
            throw new InvalidArgumentException("El cliente solo dispone de {$cliente->puntos_fidelidad} puntos.");
        }

        $descuento = $this->calcularDescuentoPorPuntos($puntos);
        if ($descuento > (float) $pedido->subtotal) {
            $descuento = (float) $pedido->subtotal;
            $puntos = (int) ceil($descuento / self::VALOR_PUNTO_COP);
        }

        return DB::transaction(function () use ($cliente, $puntos, $descuento, $pedido) {
            $saldoAnterior = $cliente->puntos_fidelidad;
            $saldoNuevo = $saldoAnterior - $puntos;

            $movimiento = MovimientoPuntos::create([
                'cliente_id' => $cliente->id,
                'pedido_id' => $pedido->id,
                'tipo' => 'canje',
                'puntos' => -$puntos,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'concepto' => "Canje de descuento en pedido #{$pedido->codigo}",
                'usuario_id' => auth()->id() ?? $pedido->usuario_id,
            ]);

            $cliente->update(['puntos_fidelidad' => $saldoNuevo]);

            $pedido->update([
                'cliente_id' => $cliente->id,
                'puntos_canjeados' => $puntos,
                'descuento_puntos' => $descuento,
            ]);

            $pedido->recalcularTotales();

            return $movimiento;
        });
    }

    /**
     * Realiza un ajuste manual de puntos (positivo o negativo) con motivo auditado.
     */
    public function ajustarPuntos(Cliente $cliente, int $puntos, string $motivo, ?User $usuario = null): MovimientoPuntos
    {
        if ($puntos === 0) {
            throw new InvalidArgumentException('El ajuste de puntos debe ser distinto de cero.');
        }

        $saldoAnterior = $cliente->puntos_fidelidad;
        $saldoNuevo = max(0, $saldoAnterior + $puntos);
        $puntosEfectivos = $saldoNuevo - $saldoAnterior;

        return DB::transaction(function () use ($cliente, $puntosEfectivos, $saldoAnterior, $saldoNuevo, $motivo, $usuario) {
            $movimiento = MovimientoPuntos::create([
                'cliente_id' => $cliente->id,
                'tipo' => 'ajuste',
                'puntos' => $puntosEfectivos,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'concepto' => $motivo,
                'usuario_id' => $usuario?->id ?? auth()->id(),
            ]);

            $cliente->update(['puntos_fidelidad' => $saldoNuevo]);

            return $movimiento;
        });
    }
}
