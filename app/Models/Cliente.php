<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'telefono',
        'email',
        'documento',
        'tier',
        'puntos_fidelidad',
        'total_gastado',
        'visitas_count',
        'alergias',
        'preferencias',
        'notas',
        'activo',
    ];

    protected $casts = [
        'puntos_fidelidad' => 'integer',
        'total_gastado' => 'decimal:2',
        'visitas_count' => 'integer',
        'activo' => 'boolean',
    ];

    public function direcciones(): HasMany
    {
        return $this->hasMany(DireccionCliente::class, 'cliente_id');
    }

    public function direccionPredeterminada()
    {
        return $this->hasOne(DireccionCliente::class, 'cliente_id')->where('es_predeterminada', true);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'cliente_id');
    }

    public function movimientosPuntos(): HasMany
    {
        return $this->hasMany(MovimientoPuntos::class, 'cliente_id');
    }

    public function esVip(): bool
    {
        return in_array(strtolower($this->tier), ['vip', 'black', 'imperial']);
    }

    public function badgeTier(): array
    {
        return match (strtolower($this->tier)) {
            'black', 'imperial' => ['label' => 'Imperial VIP', 'color' => 'bg-primary-container text-on-primary-container border-primary'],
            'vip' => ['label' => 'VIP Club', 'color' => 'bg-primary-fixed text-on-primary-fixed border-primary-fixed-dim'],
            'gold' => ['label' => 'Gourmet Gold', 'color' => 'bg-tertiary-fixed text-on-tertiary-fixed border-tertiary'],
            default => ['label' => 'Comensal Regular', 'color' => 'bg-surface-container-high text-on-surface border-outline-variant/30'],
        };
    }
}
