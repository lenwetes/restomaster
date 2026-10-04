<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'flujo_id',
    'usuario_id',
    'accion',
    'antes',
    'despues',
    'ip',
])]
#[Table(name: 'automatizacion_auditoria')]
class AutomatizacionAuditoria extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
        ];
    }

    public function flujo(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionFlujo::class, 'flujo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
