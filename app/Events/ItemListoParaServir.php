<?php

namespace App\Events;

use App\Models\ItemPedido;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemListoParaServir implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $itemId;

    public int $pedidoId;

    public string $pedidoCodigo;

    public string $nombreProducto;

    public int $cantidad;

    public ?string $mesaNumero;

    public ?string $mesaZona;

    public ?int $meseroId;

    public string $listoEn;

    public ?string $notas;

    public function __construct(ItemPedido $item)
    {
        $pedido = $item->pedido ?? $item->load('pedido')->pedido;
        $mesa = $pedido?->mesa;

        $this->itemId = $item->id;
        $this->pedidoId = $item->pedido_id;
        $this->pedidoCodigo = $pedido?->codigo ?? '';
        $this->nombreProducto = $item->nombre_producto ?? $item->producto?->nombre ?? 'Producto';
        $this->cantidad = $item->cantidad;
        $this->mesaNumero = $mesa?->numero;
        $this->mesaZona = $mesa?->zona ?? $mesa?->nombre_sala ?? null;
        $this->meseroId = $pedido?->mesero_id;
        $this->listoEn = now()->toIso8601String();
        $this->notas = $item->notas;
    }

    public function broadcastOn(): array
    {
        if ($this->meseroId) {
            return [new PrivateChannel("mesero.{$this->meseroId}")];
        }

        return [new Channel('cocina.general')];
    }

    public function broadcastAs(): string
    {
        return 'item.listo';
    }

    public function broadcastWith(): array
    {
        return [
            'item_id' => $this->itemId,
            'pedido_id' => $this->pedidoId,
            'pedido_codigo' => $this->pedidoCodigo,
            'nombre_producto' => $this->nombreProducto,
            'cantidad' => $this->cantidad,
            'mesa_numero' => $this->mesaNumero,
            'mesa_zona' => $this->mesaZona,
            'mesero_id' => $this->meseroId,
            'listo_en' => $this->listoEn,
            'notas' => $this->notas,
        ];
    }
}
