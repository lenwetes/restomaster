<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'titulo',
    'segmento_objetivo',
    'canal',
    'mensaje_template',
    'variables',
    'total_destinatarios',
    'total_enviados',
    'total_fallidos',
    'estado',
    'usuario_id',
])]
#[Table(name: 'crm_difusiones')]
class CrmDifusion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'total_destinatarios' => 'integer',
            'total_enviados' => 'integer',
            'total_fallidos' => 'integer',
        ];
    }

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
