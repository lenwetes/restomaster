<?php

namespace App\Services;

use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class NotificacionService
{
    /**
     * Obtener resumen unificado de notificaciones operativas en tiempo real,
     * filtradas estrictamente según el rol del usuario autenticado.
     */
    public function obtenerResumen(?User $usuario = null): array
    {
        $usuario = $usuario ?? auth()->user();
        $clave = 'notif.resumen.'.($usuario?->id ?? 'anon');

        $data = Cache::remember($clave, now()->addSeconds(15), function () use ($usuario) {
            return $this->consultarResumen($usuario);
        });

        return [
            'total' => $data['total'] ?? 0,
            'pedidos_qr' => new Collection($data['pedidos_qr'] ?? []),
            'platos_listos' => new Collection($data['platos_listos'] ?? []),
            'stock_critico' => new Collection($data['stock_critico'] ?? []),
            'reservas_hoy' => new Collection($data['reservas_hoy'] ?? []),
            'rol_consultado' => $data['rol_consultado'] ?? null,
        ];
    }

    protected function consultarResumen(?User $usuario = null): array
    {
        $rol = $usuario?->role?->slug ?? 'admin';

        $puedeVerPedidosQr = in_array($rol, ['mesero', 'cajero', 'gerente', 'admin'], true);
        $puedeVerPlatosListos = in_array($rol, ['mesero', 'cajero', 'gerente', 'admin'], true);
        $puedeVerStockCritico = in_array($rol, ['cocina', 'barra', 'gerente', 'admin'], true);
        $puedeVerReservas = in_array($rol, ['cajero', 'gerente', 'admin'], true);

        // 1. Pedidos QR pendientes de ser tomados/atendidos por un mesero
        $pedidosQr = $puedeVerPedidosQr ? Pedido::query()
            ->where('canal_origen', 'qr_mesa')
            ->where('estado', 'solicitado_qr')
            ->whereNull('usuario_id')
            ->with(['mesa', 'items'])
            ->latest()
            ->limit(10)
            ->get() : collect();

        // 2. Platos terminados en cocina/barra pendientes de ser servidos a la mesa
        $platosListos = $puedeVerPlatosListos ? ItemPedido::query()
            ->where('estado_cocina', 'listo')
            ->whereHas('pedido', function ($q) {
                $q->whereNotIn('estado', ['pagado', 'cancelado']);
            })
            ->with(['pedido.mesa', 'producto'])
            ->latest('listo_en')
            ->limit(10)
            ->get() : collect();

        // 3. Alertas de inventario con stock crítico (solo cocina/barra/gerencia)
        $stockCritico = $puedeVerStockCritico ? Insumo::query()
            ->where('activo', true)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->limit(5)
            ->get() : collect();

        // 4. Reservas solicitadas o confirmadas del día de hoy (caja/anfitrión/gerencia)
        $reservasHoy = $puedeVerReservas ? Reserva::query()
            ->whereDate('fecha', today())
            ->whereIn('estado', ['solicitada', 'confirmada'])
            ->orderBy('hora_llegada')
            ->limit(5)
            ->get() : collect();

        $total = $pedidosQr->count() + $platosListos->count() + $stockCritico->count() + $reservasHoy->count();

        return [
            'total' => $total,
            'pedidos_qr' => $pedidosQr->map(fn ($p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'mesa_numero' => $p->mesa?->numero,
                'mesa_zona' => $p->mesa?->zona,
                'usuario_id' => $p->usuario_id,
                'nombre_cliente' => $p->nombre_cliente,
                'total' => (float) $p->total,
                'items_count' => $p->items->count(),
            ])->values()->all(),
            'platos_listos' => $platosListos->map(fn ($i) => [
                'id' => $i->id,
                'pedido_id' => $i->pedido_id,
                'listo_en' => $i->listo_en?->toIso8601String(),
                'mesa_numero' => $i->pedido?->mesa?->numero,
                'pedido_tipo' => $i->pedido?->tipo,
                'pedido_codigo' => $i->pedido?->codigo,
                'cantidad' => $i->cantidad,
                'nombre_producto' => $i->nombre_producto ?? $i->producto?->nombre,
            ])->values()->all(),
            'stock_critico' => $stockCritico->map(fn ($i) => [
                'id' => $i->id,
                'nombre' => $i->nombre,
                'stock_actual' => $i->stock_actual,
                'stock_minimo' => $i->stock_minimo,
                'unidad_medida' => $i->unidad_medida,
            ])->values()->all(),
            'reservas_hoy' => $reservasHoy->map(fn ($r) => [
                'id' => $r->id,
                'nombre_contacto' => $r->nombre_contacto,
                'hora_llegada' => $r->hora_llegada,
                'personas' => $r->personas,
                'estado' => $r->estado,
            ])->values()->all(),
            'rol_consultado' => $rol,
        ];
    }
}
