<?php

namespace App\Services;

use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\User;

class NotificacionService
{
    /**
     * Obtener resumen unificado de notificaciones operativas en tiempo real,
     * filtradas estrictamente según el rol del usuario autenticado.
     */
    public function obtenerResumen(?User $usuario = null): array
    {
        $usuario = $usuario ?? auth()->user();

        return $this->consultarResumen($usuario);
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
            'pedidos_qr' => $pedidosQr,
            'platos_listos' => $platosListos,
            'stock_critico' => $stockCritico,
            'reservas_hoy' => $reservasHoy,
            'rol_consultado' => $rol,
        ];
    }
}
