<?php

namespace App\Events;

use App\Models\Mesa;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesaActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $mesaId;

    public string $numero;

    public string $estado;

    public ?string $zona;

    public ?int $meseroId;

    public int $sucursalId;

    public function __construct(Mesa $mesa)
    {
        $this->mesaId = $mesa->id;
        $this->numero = (string) $mesa->numero;
        $this->estado = (string) $mesa->estado;
        $this->zona = $mesa->zona;
        $this->meseroId = $mesa->mesero_id;
        $this->sucursalId = $mesa->sucursal_id ?? 1;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("sucursal.{$this->sucursalId}")];
    }

    public function broadcastAs(): string
    {
        return 'mesa.actualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'mesa_id' => $this->mesaId,
            'numero' => $this->numero,
            'estado' => $this->estado,
            'zona' => $this->zona,
            'mesero_id' => $this->meseroId,
        ];
    }
}
