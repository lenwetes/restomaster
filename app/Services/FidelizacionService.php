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
            $clienteLocked = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();
            $saldoAnterior = $clienteLocked->puntos_fidelidad;
            $saldoNuevo = $saldoAnterior + $puntosAGanar;

            $movimiento = MovimientoPuntos::create([
                'cliente_id' => $clienteLocked->id,
                'pedido_id' => $pedido->id,
                'tipo' => 'acumulacion',
                'puntos' => $puntosAGanar,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'concepto' => "Consumo pedido #{$pedido->codigo}",
                'usuario_id' => $pedido->usuario_id,
            ]);

            $nuevoTotalGastado = (float) $clienteLocked->total_gastado + (float) $pedido->total;
            $visitas = $clienteLocked->visitas_count + 1;

            // Actualización de Tier automática (Ocasional -> Frecuente -> VIP)
            $nuevoTier = $clienteLocked->tier;
            if ($nuevoTotalGastado >= 2000000) {
                $nuevoTier = Cliente::TIER_VIP;
            } elseif ($visitas >= 2 && ($nuevoTier === Cliente::TIER_OCASIONAL || empty($nuevoTier))) {
                $nuevoTier = Cliente::TIER_FRECUENTE;
            }

            $clienteLocked->update([
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
     * Registra una visita y acumula puntos para el comensal.
     */
    public function registrarVisita(Cliente $cliente, Pedido $pedido): ?MovimientoPuntos
    {
        return $this->acumularPuntosPorPedido($pedido);
    }

    /**
     * Canjea puntos del cliente para aplicar un descuento directo al pedido.
     */
    public function canjearPuntos(Cliente $cliente, int $puntos, ?Pedido $pedido = null): MovimientoPuntos
    {
        if ($puntos <= 0) {
            throw new InvalidArgumentException('La cantidad de puntos a canjear debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($cliente, $puntos, $pedido) {
            $clienteLocked = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();

            if ($clienteLocked->puntos_fidelidad < $puntos) {
                throw new InvalidArgumentException("El cliente solo dispone de {$clienteLocked->puntos_fidelidad} puntos.");
            }

            $puntosACanjear = $puntos;
            $descuento = 0.0;

            if ($pedido) {
                $pedidoLocked = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
                if ($pedidoLocked->estado === 'pagado') {
                    throw new InvalidArgumentException('No se pueden canjear puntos en un pedido ya pagado.');
                }
                $remanente = max(0.0, (float) $pedidoLocked->subtotal - (float) ($pedidoLocked->descuento ?? 0));
                $descuentoCalculado = $this->calcularDescuentoPorPuntos($puntosACanjear);
                if ($descuentoCalculado > $remanente) {
                    $descuento = $remanente;
                    $puntosACanjear = (int) ceil($descuento / self::VALOR_PUNTO_COP);
                } else {
                    $descuento = $descuentoCalculado;
                }
            } else {
                $descuento = $this->calcularDescuentoPorPuntos($puntosACanjear);
            }

            $saldoAnterior = $clienteLocked->puntos_fidelidad;
            $saldoNuevo = max(0, $saldoAnterior - $puntosACanjear);

            $movimiento = MovimientoPuntos::create([
                'cliente_id' => $clienteLocked->id,
                'pedido_id' => $pedido?->id,
                'tipo' => 'canje',
                'puntos' => -$puntosACanjear,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'concepto' => $pedido ? "Canje de descuento en pedido #{$pedido->codigo}" : 'Canje de puntos',
                'usuario_id' => auth()->id() ?? $pedido?->usuario_id,
            ]);

            $clienteLocked->update(['puntos_fidelidad' => $saldoNuevo]);

            if ($pedido) {
                $pedidoLocked->update([
                    'cliente_id' => $clienteLocked->id,
                    'puntos_canjeados' => $puntosACanjear,
                    'descuento_puntos' => $descuento,
                ]);

                $pedidoLocked->recalcularTotales();
            }

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
