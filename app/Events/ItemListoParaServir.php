<?php

namespace App\Events;

use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemListoParaServir implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

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
        /** @var Pedido|null $pedido */
        $pedido = $item->relationLoaded('pedido') ? $item->pedido : $item->pedido()->first();
        /** @var Mesa|null $mesa */
        $mesa = $pedido?->mesa;

        $this->itemId = $item->id;
        $this->pedidoId = $item->pedido_id;
        $this->pedidoCodigo = $pedido ? $pedido->codigo : '';
        $this->nombreProducto = $item->nombre_producto ?? ($item->producto ? $item->producto->nombre : 'Producto');
        $this->cantidad = $item->cantidad;
        $this->mesaNumero = $mesa ? $mesa->numero : null;
        $this->mesaZona = $mesa ? $mesa->zona : null;
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
