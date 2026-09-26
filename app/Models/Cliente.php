<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    public const TIER_OCASIONAL = 'ocasional';

    public const TIER_FRECUENTE = 'frecuente';

    public const TIER_VIP = 'vip';

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
        'acepta_tratamiento_datos',
        'fecha_autorizacion_datos',
        'canal_autorizacion_datos',
        'autoriza_whatsapp',
        'autoriza_email',
        'auth_token',
        'auth_token_expires_at',
        'avatar_url',
        'proveedor_auth',
        'rating_promedio',
        'encuestas_respondidas',
    ];

    protected $casts = [
        'puntos_fidelidad' => 'integer',
        'total_gastado' => 'decimal:2',
        'visitas_count' => 'integer',
        'activo' => 'boolean',
        'acepta_tratamiento_datos' => 'boolean',
        'fecha_autorizacion_datos' => 'datetime',
        'autoriza_whatsapp' => 'boolean',
        'autoriza_email' => 'boolean',
        'auth_token_expires_at' => 'datetime',
        'rating_promedio' => 'decimal:2',
        'encuestas_respondidas' => 'integer',
    ];

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(ClienteSocialAccount::class, 'cliente_id');
    }

    public function encuestaEnvios(): HasMany
    {
        return $this->hasMany(EncuestaEnvio::class, 'cliente_id');
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(DireccionCliente::class, 'cliente_id');
    }

    public function direccionPredeterminada(): HasOne
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

    public function isOcasional(): bool
    {
        return strtolower($this->tier ?? '') === self::TIER_OCASIONAL || empty($this->tier);
    }

    public function isFrecuente(): bool
    {
        return in_array(strtolower($this->tier ?? ''), [self::TIER_FRECUENTE, 'regular'], true);
    }

    public function isVip(): bool
    {
        return in_array(strtolower($this->tier ?? ''), [self::TIER_VIP, 'black', 'imperial', 'gold', 'oro'], true);
    }

    public function esVip(): bool
    {
        return $this->isVip();
    }

    public function tieneHabeasData(): bool
    {
        return (bool) $this->acepta_tratamiento_datos;
    }

    public function badgeTier(): array
    {
        $t = strtolower($this->tier ?? '');

        if ($this->isVip()) {
            return [
                'label' => 'VIP Club',
                'color' => 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30',
                'icono' => 'stars',
            ];
        }

        if ($this->isFrecuente()) {
            return [
                'label' => 'Frecuente',
                'color' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30',
                'icono' => 'repeat',
            ];
        }

        return [
            'label' => 'Ocasional',
            'color' => 'bg-stone-500/15 text-stone-700 dark:text-stone-400 border border-stone-500/30',
            'icono' => 'person',
        ];
    }

    public function scopeOcasionales(Builder $query): Builder
    {
        return $query->where('tier', self::TIER_OCASIONAL);
    }

    public function scopeFrecuentes(Builder $query): Builder
    {
        return $query->whereIn('tier', [self::TIER_FRECUENTE, 'regular']);
    }

    public function scopeVip(Builder $query): Builder
    {
        return $query->whereIn('tier', [self::TIER_VIP, 'black', 'imperial', 'gold', 'oro']);
    }
}
