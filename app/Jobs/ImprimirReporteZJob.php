<?php

namespace App\Jobs;

class ImprimirReporteZJob extends ImprimirTrabajoJob
{
    public function __construct(int $trabajoId)
    {
        parent::__construct($trabajoId, 'reporte_z');
    }
}
