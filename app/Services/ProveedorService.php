<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\CuentaPorPagar;
use App\Models\Insumo;
use App\Models\Proveedor;
use InvalidArgumentException;

class ProveedorService
{
    public function crear(array $datos): Proveedor
    {
        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del proveedor es obligatorio.');
        }
        if (Proveedor::where('nombre', $nombre)->exists()) {
            throw new InvalidArgumentException("Ya existe el proveedor {$nombre}.");
        }
        if (! empty($datos['nit']) && Proveedor::where('nit', $datos['nit'])->exists()) {
            throw new InvalidArgumentException("Ya existe un proveedor con NIT {$datos['nit']}.");
        }

        $permitidos = ['nombre', 'nit', 'telefono', 'email', 'direccion', 'contacto', 'dias_credito', 'activo'];
        $atributos = array_intersect_key($datos, array_flip($permitidos));
        $atributos['nombre'] = $nombre;

        $prov = Proveedor::create($atributos);
        app(AuditoriaService::class)->registrar(accion: 'proveedor.creado', entidad: 'proveedor', entidadId: $prov->id, descripcion: "Proveedor creado: {$prov->nombre}.", datos: ['nombre' => $prov->nombre]);

        return $prov->fresh();
    }

    public function actualizar(Proveedor $proveedor, array $datos): Proveedor
    {
        $nombre = trim($datos['nombre'] ?? $proveedor->nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del proveedor es obligatorio.');
        }
        if (Proveedor::where('nombre', $nombre)->where('id', '!=', $proveedor->id)->exists()) {
            throw new InvalidArgumentException("Ya existe el proveedor {$nombre}.");
        }
        $nit = $datos['nit'] ?? $proveedor->nit;
        if (! empty($nit) && Proveedor::where('nit', $nit)->where('id', '!=', $proveedor->id)->exists()) {
            throw new InvalidArgumentException("Ya existe un proveedor con NIT {$nit}.");
        }

        $permitidos = ['nombre', 'nit', 'telefono', 'email', 'direccion', 'contacto', 'dias_credito', 'activo'];
        $atributos = array_intersect_key($datos, array_flip($permitidos));
        $atributos['nombre'] = $nombre;

        $proveedor->fill($atributos);
        $proveedor->save();
        app(AuditoriaService::class)->registrar(accion: 'proveedor.actualizado', entidad: 'proveedor', entidadId: $proveedor->id, descripcion: "Proveedor actualizado: {$proveedor->nombre}.", datos: ['nombre' => $proveedor->nombre]);

        return $proveedor->fresh();
    }

    public function desactivar(Proveedor $proveedor): Proveedor
    {
        $proveedor->update(['activo' => false]);
        app(AuditoriaService::class)->registrar(accion: 'proveedor.desactivado', entidad: 'proveedor', entidadId: $proveedor->id, descripcion: "Proveedor desactivado: {$proveedor->nombre}.", datos: ['nombre' => $proveedor->nombre]);

        return $proveedor->fresh();
    }

    public function eliminar(Proveedor $proveedor): void
    {
        if ($proveedor->compras()->exists()) {
            throw new InvalidArgumentException('No se puede eliminar: tiene facturas registradas.', 422);
        }

        $id = $proveedor->id;
        $nombre = $proveedor->nombre;
        $proveedor->delete();
        app(AuditoriaService::class)->registrar(accion: 'proveedor.eliminado', entidad: 'proveedor', entidadId: $id, descripcion: "Proveedor eliminado: {$nombre}.", datos: ['nombre' => $nombre]);
    }

    public function vincularInsumo(Proveedor $proveedor, Insumo $insumo): Insumo
    {
        $insumo->update(['proveedor_id' => $proveedor->id]);

        return $insumo->fresh();
    }

    public function fichaResumen(Proveedor $proveedor, string $desde, string $hasta): array
    {
        // whereDate (no whereBetween): en SQLite los date se guardan como 'Y-m-d H:i:s'
        // y el string 'Y-m-d 00:00:00' queda fuera del rango ['Y-m-d','Y-m-d'].
        $agg = Compra::where('proveedor_id', $proveedor->id)->where('estado', 'registrada')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->selectRaw('COALESCE(SUM(subtotal), 0) AS total, COUNT(*) AS facturas')
            ->first();
        $total = (float) $agg->total;
        $facturas = (int) $agg->facturas;
        $global = (float) Compra::where('estado', 'registrada')->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)->sum('subtotal');
        $cxpSaldo = (float) CuentaPorPagar::where('estado', 'pendiente')
            ->whereHas('compra', fn ($q) => $q->where('proveedor_id', $proveedor->id))
            ->sum('saldo_pendiente');

        return [
            'total' => $total,
            'facturas' => $facturas,
            'ticket_promedio' => $facturas > 0 ? round($total / $facturas, 2) : 0.0,
            'participacion' => $global > 0 ? round($total / $global * 100, 2) : 0.0,
            'cxp_saldo' => $cxpSaldo,
        ];
    }

    public function comparadorInsumo(int $insumoId): array
    {
        $insumo = Insumo::findOrFail($insumoId);
        $referencia = $insumo->precio_referencia_mercado !== null ? (float) $insumo->precio_referencia_mercado : null;

        $ultimas = CompraLinea::join('compras as c', 'c.id', '=', 'compra_lineas.compra_id')
            ->where('compra_lineas.insumo_id', $insumoId)
            ->where('c.estado', 'registrada')
            ->selectRaw('c.proveedor_id, MAX(c.fecha) AS ultima_fecha')
            ->groupBy('c.proveedor_id')
            ->get();

        $filas = [];
        foreach ($ultimas as $u) {
            // Normalizar a Y-m-d: SQLite compara whereDate contra el valor crudo
            // y MAX(fecha) puede venir como 'Y-m-d H:i:s'.
            $ultimaFecha = substr((string) $u->ultima_fecha, 0, 10);
            $linea = CompraLinea::join('compras as c', 'c.id', '=', 'compra_lineas.compra_id')
                ->where('compra_lineas.insumo_id', $insumoId)
                ->where('c.proveedor_id', $u->proveedor_id)
                ->where('c.estado', 'registrada')
                ->whereDate('c.fecha', $ultimaFecha)
                ->orderByDesc('compra_lineas.id')
                ->select('compra_lineas.costo_unitario')
                ->first();
            $costo = (float) $linea->costo_unitario;
            $filas[] = [
                'proveedor_id' => (int) $u->proveedor_id,
                'proveedor' => Proveedor::where('id', $u->proveedor_id)->value('nombre'),
                'ultima_fecha' => $u->ultima_fecha,
                'ultimo_costo' => $costo,
                'delta_vs_referencia' => $referencia ? round(($costo - $referencia) / $referencia * 100, 2) : null,
            ];
        }

        return $filas;
    }
}
