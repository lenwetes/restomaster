<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\CuentaPorPagar;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompraService
{
    public function registrarFactura(array $cabecera, array $lineas, ?User $usuario = null): Compra
    {
        return DB::transaction(function () use ($cabecera, $lineas, $usuario) {
            $proveedor = Proveedor::find($cabecera['proveedor_id'] ?? null);
            if (! $proveedor || ! $proveedor->activo) {
                throw new InvalidArgumentException('Proveedor inválido o inactivo.');
            }
            if (empty($lineas)) {
                throw new InvalidArgumentException('La factura requiere al menos una línea.');
            }
            if (Compra::where('proveedor_id', $proveedor->id)->where('numero_factura', $cabecera['numero_factura'] ?? '')->exists()) {
                throw new InvalidArgumentException('Factura ya registrada para este proveedor.');
            }

            $subtotal = 0;
            foreach ($lineas as $l) {
                Insumo::findOrFail($l['insumo_id'] ?? null);
                if ((float) ($l['cantidad'] ?? 0) <= 0) {
                    throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
                }
                if ((float) ($l['costo_unitario'] ?? 0) < 0) {
                    throw new InvalidArgumentException('El costo no puede ser negativo.');
                }
                $subtotal = round($subtotal + (float) $l['cantidad'] * (float) $l['costo_unitario'], 2);
            }

            $compra = Compra::create([
                'proveedor_id' => $proveedor->id,
                'numero_factura' => $cabecera['numero_factura'],
                'fecha' => $cabecera['fecha'],
                'subtotal' => $subtotal,
                'forma_pago' => $cabecera['forma_pago'] ?? 'contado',
                'estado' => 'registrada',
                'user_id' => $usuario?->id ?? auth()->id(),
            ]);

            foreach ($lineas as $l) {
                CompraLinea::create(['compra_id' => $compra->id, 'insumo_id' => $l['insumo_id'], 'cantidad' => $l['cantidad'], 'costo_unitario' => $l['costo_unitario'], 'subtotal' => round((float) $l['cantidad'] * (float) $l['costo_unitario'], 2)]);
                app(InventarioService::class)->registrarCompra((int) $l['insumo_id'], (float) $l['cantidad'], (float) $l['costo_unitario'], $proveedor->nombre, $compra->numero_factura, $usuario?->id);
            }

            if (($cabecera['forma_pago'] ?? 'contado') === 'credito') {
                $cxp = app(CuentasPorPagarService::class)->crear([
                    'proveedor_nombre' => $proveedor->nombre,
                    'proveedor_nit' => $proveedor->nit,
                    'numero_factura' => $compra->numero_factura,
                    'concepto' => "Compra factura {$compra->numero_factura} (".count($lineas).' líneas)',
                    'monto_total' => $subtotal,
                    'fecha_emision' => Carbon::parse($compra->fecha)->toDateString(),
                    'fecha_vencimiento' => Carbon::parse($compra->fecha)->addDays((int) $proveedor->dias_credito)->toDateString(),
                    'compra_id' => $compra->id,
                    'user_id' => $usuario?->id,
                ]);
                $cxp->update(['compra_id' => $compra->id]);
            }

            app(AuditoriaService::class)->registrar(accion: 'compra.registrada', entidad: 'compra', entidadId: $compra->id, descripcion: "Factura {$compra->numero_factura} registrada a {$proveedor->nombre}.", datos: ['compra_id' => $compra->id, 'subtotal' => $subtotal]);

            return $compra->fresh(['lineas', 'proveedor']);
        });
    }

    public function anularFactura(Compra $compra, ?User $usuario = null): Compra
    {
        return DB::transaction(function () use ($compra, $usuario) {
            $compra = Compra::where('id', $compra->id)->lockForUpdate()->firstOrFail();
            abort_if($compra->estado !== 'registrada', 400, 'Solo se anulan facturas registradas.');

            $cxp = CuentaPorPagar::where('compra_id', $compra->id)->first();
            if ($cxp && $cxp->pagos()->exists()) {
                throw new DomainException('La factura tiene pagos aplicados y no puede anularse.');
            }

            /** @var CompraLinea $linea */
            foreach ($compra->lineas as $linea) {
                $insumo = Insumo::where('id', $linea->insumo_id)->lockForUpdate()->firstOrFail();
                $stock = (float) $insumo->stock_actual;
                $qty = (float) $linea->cantidad;
                if ($qty > $stock) {
                    throw new DomainException("Stock insuficiente de {$insumo->nombre} para reversar ({$qty} > {$stock}).");
                }
                $restante = round($stock - $qty, 3);
                $avg = (float) $insumo->costo_unitario;
                $nuevoProm = $restante > 0 ? round(($stock * $avg - $qty * (float) $linea->costo_unitario) / $restante, 2) : $avg;
                $insumo->update(['stock_actual' => $restante, 'costo_unitario' => $nuevoProm]);

                MovimientoInventario::create([
                    'insumo_id' => $insumo->id,
                    'tipo' => 'devolucion_compra',
                    'cantidad' => $qty,
                    'saldo_anterior' => $stock,
                    'saldo_posterior' => $restante,
                    'costo_unitario' => $linea->costo_unitario,
                    'costo_total' => round($qty * (float) $linea->costo_unitario, 2),
                    'user_id' => $usuario?->id ?? auth()->id(),
                    'motivo' => "Anulación factura {$compra->numero_factura}",
                    'referencia_documento' => $compra->numero_factura,
                ]);
            }

            if ($cxp) {
                $cxp->delete();
            }
            $compra->update(['estado' => 'anulada']);
            app(AuditoriaService::class)->registrar(accion: 'compra.anulada', entidad: 'compra', entidadId: $compra->id, descripcion: "Factura {$compra->numero_factura} anulada.", datos: ['compra_id' => $compra->id]);

            return $compra->fresh(['lineas']);
        });
    }

    public function gastoPorProveedor(string $desde, string $hasta): Collection
    {
        // whereDate (no whereBetween): ver nota en ProveedorService::fichaResumen.
        return Compra::where('estado', 'registrada')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->selectRaw('proveedor_id, COUNT(*) AS facturas, SUM(subtotal) AS total')
            ->groupBy('proveedor_id')
            ->with('proveedor:id,nombre')
            ->orderByDesc('total')
            ->get();
    }
}
