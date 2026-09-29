<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SolicitudCobroEnviada implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $pedidoId;

    public string $codigo;

    public int $sucursalId;

    public int $meseroId;

    public string $meseroNombre;

    public ?string $mesa;

    public float $total;

    public function __construct(Pedido $pedido)
    {
        $this->pedidoId = $pedido->id;
        $this->codigo = $pedido->codigo;
        $this->sucursalId = (int) $pedido->sucursal_id;
        $this->meseroId = (int) ($pedido->mesero_id ?? $pedido->usuario_id);
        $this->meseroNombre = $pedido->mesero?->name ?? $pedido->usuario?->name ?? 'Mesero';
        $this->mesa = $pedido->mesa ? 'Mesa '.$pedido->mesa->numero : null;
        $this->total = (float) $pedido->total;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('caja.'.$this->sucursalId);
    }

    public function broadcastAs(): string
    {
        return 'solicitud.cobro';
    }
}
