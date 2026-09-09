<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsientoContable extends Model
{
    use HasFactory;

    protected $table = 'asientos_contables';

    protected $fillable = [
        'fecha',
        'tipo',
        'cuenta',
        'concepto',
        'monto',
        'referencia_tipo',
        'referencia_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
