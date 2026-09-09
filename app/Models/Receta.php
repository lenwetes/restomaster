<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receta extends Model
{
    use HasFactory;

    protected $table = 'recetas';

    protected $fillable = [
        'producto_id',
        'insumo_id',
        'cantidad',
        'merma_esperada_pct',
        'notas',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'merma_esperada_pct' => 'decimal:2',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    /**
     * Costo teórico del insumo en esta receta (cantidad * costo_unitario * (1 + merma/100))
     */
    public function getCostoTeoricoAttribute(): float
    {
        $costoBase = (float) $this->cantidad * (float) ($this->insumo->costo_unitario ?? 0);
        $factorMerma = 1 + ((float) $this->merma_esperada_pct / 100);
        return round($costoBase * $factorMerma, 2);
    }
}
