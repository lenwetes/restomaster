<?php

namespace App\Services;

use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Receta;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    /**
     * Descuenta insumos correspondientes a la receta de un ItemPedido.
     * Es idempotente: solo descuenta si inventario_descontado es false.
     */
    public function descontarPorItemPedido(ItemPedido $item): bool
    {
        if ($item->inventario_descontado) {
            return false;
        }

        $producto = $item->producto()->with('recetas.insumo')->first();
        if (! $producto || $producto->recetas->isEmpty()) {
            $item->update(['inventario_descontado' => true]);

            return false;
        }

        return DB::transaction(function () use ($item, $producto) {
            foreach ($producto->recetas as $receta) {
                $insumo = Insumo::where('id', $receta->insumo_id)->lockForUpdate()->first();
                if (! $insumo) {
                    continue;
                }

                // Cálculo con merma estimada: cantidad * (1 + merma/100) * unidades_ordenadas
                $factorMerma = 1 + ((float) $receta->merma_esperada_pct / 100);
                $cantidadConsumo = round((float) $receta->cantidad * $factorMerma * (int) $item->cantidad, 3);

                $saldoAnterior = (float) $insumo->stock_actual;
                $saldoPosterior = max(0, round($saldoAnterior - $cantidadConsumo, 3));

                $insumo->update(['stock_actual' => $saldoPosterior]);

                MovimientoInventario::create([
                    'insumo_id' => $insumo->id,
                    'tipo' => 'consumo_venta',
                    'cantidad' => $cantidadConsumo,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_posterior' => $saldoPosterior,
                    'costo_unitario' => $insumo->costo_unitario,
                    'costo_total' => round($cantidadConsumo * (float) $insumo->costo_unitario, 2),
                    'pedido_id' => $item->pedido_id,
                    'user_id' => auth()->id() ?? $item->pedido->usuario_id,
                    'motivo' => "Consumo COC-01 #{$item->pedido_id}: {$item->cantidad}x {$item->nombre_producto}",
                    'referencia_documento' => "KDS-ITM-{$item->id}",
                ]);
            }

            $item->update(['inventario_descontado' => true]);

            return true;
        });
    }

    /**
     * Descuenta todos los ítems pendientes de descuento en un pedido.
     */
    public function descontarPorPedido(Pedido $pedido): int
    {
        $descontados = 0;
        foreach ($pedido->items as $item) {
            if (! $item->inventario_descontado) {
                if ($this->descontarPorItemPedido($item)) {
                    $descontados++;
                }
            }
        }

        return $descontados;
    }

    /**
     * Registra una compra / reabastecimiento a proveedor con actualización de costo promedio ponderado.
     */
    public function registrarCompra(
        int $insumoId,
        float $cantidad,
        float $costoUnitario,
        ?string $proveedor = null,
        ?string $factura = null,
        ?int $userId = null
    ): MovimientoInventario {
        return DB::transaction(function () use ($insumoId, $cantidad, $costoUnitario, $proveedor, $factura, $userId) {
            $insumo = Insumo::where('id', $insumoId)->lockForUpdate()->firstOrFail();

            $saldoAnterior = (float) $insumo->stock_actual;
            $saldoPosterior = round($saldoAnterior + $cantidad, 3);

            // Método de Costo Promedio Ponderado NIIF / DIAN
            $valorAnterior = $saldoAnterior * (float) $insumo->costo_unitario;
            $valorNuevo = $cantidad * $costoUnitario;
            $nuevoCostoPromedio = $saldoPosterior > 0 ? round(($valorAnterior + $valorNuevo) / $saldoPosterior, 2) : $costoUnitario;

            $insumo->update([
                'stock_actual' => $saldoPosterior,
                'costo_unitario' => $nuevoCostoPromedio,
                'proveedor_nombre' => $proveedor ?? $insumo->proveedor_nombre,
            ]);

            return MovimientoInventario::create([
                'insumo_id' => $insumo->id,
                'tipo' => 'compra',
                'cantidad' => $cantidad,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'costo_unitario' => $costoUnitario,
                'costo_total' => round($cantidad * $costoUnitario, 2),
                'user_id' => $userId ?? auth()->id(),
                'motivo' => "Recepción factura {$factura} · Proveedor: ".($proveedor ?? $insumo->proveedor_nombre ?? 'General'),
                'referencia_documento' => $factura ?? 'FAC-COMPRA-'.date('YmdHis'),
            ]);
        });
    }

    /**
     * Registra una merma operativa (rotura, vencimiento, mala preparación, etc.).
     */
    public function registrarMerma(
        int $insumoId,
        float $cantidad,
        string $motivo,
        ?int $userId = null,
        ?string $referencia = null
    ): MovimientoInventario {
        return DB::transaction(function () use ($insumoId, $cantidad, $motivo, $userId, $referencia) {
            $insumo = Insumo::where('id', $insumoId)->lockForUpdate()->firstOrFail();

            $saldoAnterior = (float) $insumo->stock_actual;
            $saldoPosterior = max(0, round($saldoAnterior - $cantidad, 3));

            $insumo->update(['stock_actual' => $saldoPosterior]);

            return MovimientoInventario::create([
                'insumo_id' => $insumo->id,
                'tipo' => 'merma',
                'cantidad' => $cantidad,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'costo_unitario' => $insumo->costo_unitario,
                'costo_total' => round($cantidad * (float) $insumo->costo_unitario, 2),
                'user_id' => $userId ?? auth()->id(),
                'motivo' => $motivo,
                'referencia_documento' => $referencia ?? 'MER-'.strtoupper(uniqid()),
            ]);
        });
    }

    /**
     * Ajuste manual por conteo físico de inventario.
     */
    public function registrarAjuste(
        int $insumoId,
        float $nuevoStock,
        string $motivo,
        ?int $userId = null
    ): MovimientoInventario {
        return DB::transaction(function () use ($insumoId, $nuevoStock, $motivo, $userId) {
            $insumo = Insumo::where('id', $insumoId)->lockForUpdate()->firstOrFail();

            $saldoAnterior = (float) $insumo->stock_actual;
            $diferencia = round($nuevoStock - $saldoAnterior, 3);
            $tipo = $diferencia >= 0 ? 'ajuste_positivo' : 'ajuste_negativo';

            $insumo->update(['stock_actual' => $nuevoStock]);

            return MovimientoInventario::create([
                'insumo_id' => $insumo->id,
                'tipo' => $tipo,
                'cantidad' => abs($diferencia),
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $nuevoStock,
                'costo_unitario' => $insumo->costo_unitario,
                'costo_total' => round(abs($diferencia) * (float) $insumo->costo_unitario, 2),
                'user_id' => $userId ?? auth()->id(),
                'motivo' => $motivo,
                'referencia_documento' => 'AJUSTE-'.date('YmdHis'),
            ]);
        });
    }

    /**
     * Obtiene métricas Bento de inventario para la vista INV-01.
     */
    public function obtenerKpis(): array
    {
        $insumos = Insumo::where('activo', true)->get();

        $valorTotalStock = $insumos->sum(fn ($i) => (float) $i->stock_actual * (float) $i->costo_unitario);
        $insumosCriticos = $insumos->filter(fn ($i) => (float) $i->stock_actual <= (float) $i->stock_minimo);
        $porAgotar = $insumos->filter(fn ($i) => (float) $i->stock_actual > (float) $i->stock_minimo && (float) $i->stock_actual <= ((float) $i->stock_minimo * 1.5));
        $optimo = $insumos->filter(fn ($i) => (float) $i->stock_actual > ((float) $i->stock_minimo * 1.5));

        $mermasHoy = MovimientoInventario::where('tipo', 'merma')
            ->whereDate('created_at', today())
            ->get();
        $totalMermasHoy = $mermasHoy->sum('costo_total');
        $incidentesMermas = $mermasHoy->count();

        $consumosHoy = MovimientoInventario::where('tipo', 'consumo_venta')
            ->whereDate('created_at', today())
            ->get();
        $totalConsumoHoy = $consumosHoy->sum('costo_total');

        return [
            'valor_total_stock' => round($valorTotalStock, 2),
            'total_insumos' => $insumos->count(),
            'criticos_count' => $insumosCriticos->count(),
            'por_agotar_count' => $porAgotar->count(),
            'optimo_count' => $optimo->count(),
            'insumos_criticos' => $insumosCriticos,
            'total_mermas_hoy' => round($totalMermasHoy, 2),
            'incidentes_mermas' => $incidentesMermas,
            'total_consumo_hoy' => round($totalConsumoHoy, 2),
        ];
    }
}
