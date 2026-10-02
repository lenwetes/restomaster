<?php

namespace App\Events;

use App\Models\ProgramacionSemanal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HorarioSemanalPublicado
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ProgramacionSemanal $programacion,
        public int $publicadoPorId
    ) {}
}
