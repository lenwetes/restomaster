<?php

namespace App\Events;

use App\Models\Cliente;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClienteElegibleVip
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Cliente $cliente,
        public float $consumoVentana
    ) {}
}
