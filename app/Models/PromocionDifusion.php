<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promocion_id',
    'user_id',
    'canal',
    'segmento',
    'total_destinatarios',
    'total_exitosos',
    'total_fallidos',
    'estado',
    'detalles',
    'iniciado_at',
    'completado_at',
])]
#[Table(name: 'promocion_difusiones')]
class PromocionDifusion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'total_destinatarios' => 'integer',
            'total_exitosos' => 'integer',
            'total_fallidos' => 'integer',
            'detalles' => 'array',
            'iniciado_at' => 'datetime',
            'completado_at' => 'datetime',
        ];
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
