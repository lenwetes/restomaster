<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PagoProcesadoPorCaja implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public int $meseroId,
        public string $mesa,
        public float $total,
        public string $metodoPago,
        public string $ticketUrl,
        public string $mensaje
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('mesero.'.$this->meseroId);
    }

    public function broadcastAs(): string
    {
        return 'pago.procesado';
    }
}
