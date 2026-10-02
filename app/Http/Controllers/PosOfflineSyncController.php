<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosOfflineSyncController extends Controller
{
    public function __construct(
        protected PedidoService $pedidoService
    ) {}

    /**
     * Sincroniza en lote comandas generadas offline en IndexedDB.
     */
    public function sincronizar(Request $request): JsonResponse
    {
        $comandas = $request->input('comandas', []);
        if (empty($comandas) || ! is_array($comandas)) {
            return response()->json(['error' => 'No se recibieron comandas para sincronizar.'], 400);
        }

        $usuario = Auth::user() ?? User::where('activo', true)->first();
        $sincronizados = [];
        $fallidos = [];

        foreach ($comandas as $c) {
            $uuid = $c['uuid'] ?? null;
            if (! $uuid) {
                continue;
            }

            // 1. Idempotencia: Si ya fue sincronizada previamente
            $existente = Pedido::where('idempotencia_uuid', $uuid)->first();
            if ($existente) {
                $sincronizados[] = [
                    'uuid' => $uuid,
                    'pedido_id' => $existente->id,
                    'codigo' => $existente->codigo,
                    'estado' => 'ya_existia',
                ];

                continue;
            }

            try {
                $pedido = DB::transaction(function () use ($c, $uuid, $usuario) {
                    $mesaId = $c['mesa_id'] ?? null;
                    $mesa = $mesaId ? Mesa::find($mesaId) : null;
                    $sucursalId = $c['sucursal_id'] ?? $mesa?->sucursal_id ?? $usuario?->sucursal_id ?? 1;

                    $items = array_map(fn ($it) => [
                        'producto_id' => $it['producto_id'],
                        'nombre_producto' => $it['nombre'] ?? $it['nombre_producto'] ?? 'Plato',
                        'cantidad' => (int) ($it['cantidad'] ?? 1),
                        'precio_unitario' => (float) ($it['precio'] ?? $it['precio_unitario'] ?? 0),
                        'subtotal' => (float) (($it['cantidad'] ?? 1) * ($it['precio'] ?? 0)),
                        'notas' => $it['notas'] ?? null,
                        'area_cocina' => $it['area_cocina'] ?? 'cocina',
                        'estado_cocina' => 'pendiente',
                    ], $c['items'] ?? []);

                    $pedidoData = [
                        'tipo' => 'mesa',
                        'estado' => 'en_cocina',
                        'sucursal_id' => $sucursalId,
                        'mesa_id' => $mesaId,
                        'cliente_id' => $c['cliente_id'] ?? null,
                        'nombre_cliente' => $c['nombre_cliente'] ?? null,
                        'mesero_id' => $usuario?->id,
                        'subtotal' => (float) ($c['subtotal'] ?? 0),
                        'total' => (float) ($c['total'] ?? 0),
                        'idempotencia_uuid' => $uuid,
                        'canal_origen' => 'pos_offline',
                    ];

                    $nuevoPedido = $this->pedidoService->crearPedido($pedidoData, $items, $usuario);

                    // Actualizar estado de mesa a ocupada
                    if ($mesa) {
                        $mesa->update([
                            'estado' => 'ocupada',
                            'mesero_id' => $usuario?->id,
                        ]);
                    }

                    return $nuevoPedido;
                });

                $sincronizados[] = [
                    'uuid' => $uuid,
                    'pedido_id' => $pedido->id,
                    'codigo' => $pedido->codigo,
                    'estado' => 'creado',
                ];
            } catch (\Throwable $e) {
                Log::error("Fallo al sincronizar comanda offline {$uuid}: {$e->getMessage()}");
                $fallidos[] = [
                    'uuid' => $uuid,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'sincronizados' => $sincronizados,
            'fallidos' => $fallidos,
            'total_procesados' => count($sincronizados),
        ]);
    }
}
