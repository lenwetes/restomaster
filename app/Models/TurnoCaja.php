<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TurnoCaja extends Model
{
    use HasFactory;

    protected $table = 'turnos_caja';

    protected $fillable = [
        'caja_id',
        'user_id',
        'apertura_en',
        'cierre_en',
        'monto_inicial',
        'total_ventas_efectivo',
        'total_ventas_tarjeta',
        'total_ventas_transferencia',
        'total_egresos',
        'total_retiros',
        'monto_esperado_efectivo',
        'monto_real_efectivo',
        'diferencia',
        'estado',
        'notas_apertura',
        'notas_cierre',
        'cerrado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'apertura_en' => 'datetime',
            'cierre_en' => 'datetime',
            'monto_inicial' => 'decimal:2',
            'total_ventas_efectivo' => 'decimal:2',
            'total_ventas_tarjeta' => 'decimal:2',
            'total_ventas_transferencia' => 'decimal:2',
            'total_egresos' => 'decimal:2',
            'total_retiros' => 'decimal:2',
            'monto_esperado_efectivo' => 'decimal:2',
            'monto_real_efectivo' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por_user_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function scopeAbiertos($query)
    {
        return $query->where('estado', 'abierto');
    }

    public function getTotalVentasAttribute(): float
    {
        return (float) $this->total_ventas_efectivo + (float) $this->total_ventas_tarjeta + (float) $this->total_ventas_transferencia;
    }
}
