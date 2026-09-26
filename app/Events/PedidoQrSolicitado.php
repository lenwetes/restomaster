<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoQrSolicitado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $pedidoId;

    public string $codigo;

    public ?string $mesaNumero;

    public ?string $mesaZona;

    public int $sucursalId;

    public string $nombreCliente;

    public int $itemsCount;

    public string $solicitadoEn;

    public function __construct(Pedido $pedido)
    {
        $pedido->loadMissing(['items', 'mesa']);

        $this->pedidoId = $pedido->id;
        $this->codigo = $pedido->codigo;
        $this->mesaNumero = $pedido->mesa?->numero;
        $this->mesaZona = $pedido->mesa?->zona ?? $pedido->mesa?->nombre_sala ?? null;
        $this->sucursalId = $pedido->sucursal_id ?? 1;
        $this->nombreCliente = $pedido->nombre_cliente ?? 'Comensal';
        $this->itemsCount = $pedido->items->count();
        $this->solicitadoEn = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("sucursal.{$this->sucursalId}")];
    }

    public function broadcastAs(): string
    {
        return 'pedido.qr';
    }

    public function broadcastWith(): array
    {
        return [
            'pedido_id' => $this->pedidoId,
            'codigo' => $this->codigo,
            'mesa_numero' => $this->mesaNumero,
            'mesa_zona' => $this->mesaZona,
            'nombre_cliente' => $this->nombreCliente,
            'items_count' => $this->itemsCount,
            'solicitado_en' => $this->solicitadoEn,
        ];
    }
}
