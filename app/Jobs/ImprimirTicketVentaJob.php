<?php

namespace App\Jobs;

class ImprimirTicketVentaJob extends ImprimirTrabajoJob
{
    public function __construct(int $trabajoId)
    {
        parent::__construct($trabajoId, 'ticket_venta');
    }
}
