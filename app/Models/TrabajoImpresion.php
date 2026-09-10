<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabajoImpresion extends Model
{
    use HasFactory;

    protected $table = 'trabajos_impresion';

    protected $fillable = [
        'tipo',
        'pedido_id',
        'turno_caja_id',
        'impresora_id',
        'area',
        'contenido_texto',
        'contenido_raw',
        'estado',
        'intentos',
        'error_mensaje',
        'impreso_en',
        'usuario_id',
        'reimpreso_por_id',
        'veces_reimpreso',
    ];

    protected $casts = [
        'impreso_en' => 'datetime',
        'intentos' => 'integer',
        'veces_reimpreso' => 'integer',
    ];

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class, 'impresora_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function turnoCaja(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function reimpresoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reimpreso_por_id');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeFallidos(Builder $query): Builder
    {
        return $query->where('estado', 'error');
    }

    public function scopeHoy(Builder $query): Builder
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    public function badgeEstado(): array
    {
        return match ($this->estado) {
            'enviado' => ['bg' => 'bg-secondary-container/40', 'text' => 'text-secondary', 'border' => 'border-secondary/30', 'label' => 'Impreso OK'],
            'pendiente' => ['bg' => 'bg-tertiary-container/30', 'text' => 'text-tertiary', 'border' => 'border-tertiary/30', 'label' => 'En Cola'],
            'error' => ['bg' => 'bg-error-container/30', 'text' => 'text-error', 'border' => 'border-error/30', 'label' => 'Falló'],
            'reimpreso' => ['bg' => 'bg-primary/15', 'text' => 'text-primary', 'border' => 'border-primary/30', 'label' => 'Reimpreso'],
            'cancelado' => ['bg' => 'bg-surface-container', 'text' => 'text-on-surface-variant', 'border' => 'border-outline-variant/30', 'label' => 'Cancelado'],
            default => ['bg' => 'bg-surface-container', 'text' => 'text-on-surface-variant', 'border' => 'border-outline-variant/30', 'label' => ucfirst($this->estado)],
        };
    }
}
