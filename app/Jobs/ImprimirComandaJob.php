<?php

namespace App\Jobs;

class ImprimirComandaJob extends ImprimirTrabajoJob
{
    public function __construct(int $trabajoId)
    {
        parent::__construct($trabajoId, 'comanda');
    }
}
