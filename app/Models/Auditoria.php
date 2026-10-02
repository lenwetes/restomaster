<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'accion',
    'entidad',
    'entidad_id',
    'descripcion',
    'datos',
    'ip',
])]
#[Table(name: 'auditorias')]
class Auditoria extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'datos' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
