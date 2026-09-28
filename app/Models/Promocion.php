<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Promocion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'promociones';

    protected $fillable = [
        'sucursal_id',
        'titulo',
        'slug',
        'subtitulo',
        'descripcion',
        'terminos_condiciones',
        'tipo_beneficio',
        'descuento_porcentaje',
        'precio_promocional',
        'precio_original',
        'imagen_url',
        'fecha_inicio',
        'fecha_fin',
        'dias_semana',
        'aplica_salon',
        'aplica_delivery',
        'mostrar_en_portada',
        'activo',
        'orden',
        'cupo_maximo',
        'veces_canjeada',
        'total_notificados_whatsapp',
        'total_notificados_email',
        'ultimo_lanzamiento_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'descuento_porcentaje' => 'decimal:2',
            'precio_promocional' => 'decimal:2',
            'precio_original' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'dias_semana' => 'array',
            'aplica_salon' => 'boolean',
            'aplica_delivery' => 'boolean',
            'mostrar_en_portada' => 'boolean',
            'activo' => 'boolean',
            'orden' => 'integer',
            'cupo_maximo' => 'integer',
            'veces_canjeada' => 'integer',
            'total_notificados_whatsapp' => 'integer',
            'total_notificados_email' => 'integer',
            'ultimo_lanzamiento_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canjes(): HasMany
    {
        return $this->hasMany(PromocionCanje::class);
    }

    public function difusiones(): HasMany
    {
        return $this->hasMany(PromocionDifusion::class);
    }

    /**
     * Scope para promociones activas y en período de vigencia.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = Carbon::today();

        return $query->where('activo', true)
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $hoy);
            })
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy);
            });
    }

    /**
     * Scope para promociones destacadas en la portada web.
     */
    public function scopeEnPortada(Builder $query): Builder
    {
        return $query->vigentes()
            ->where('mostrar_en_portada', true)
            ->orderBy('orden')
            ->orderByDesc('id');
    }

    /**
     * Determina si la promoción está vigente hoy.
     */
    public function esVigente(): bool
    {
        if (! $this->activo) {
            return false;
        }

        $hoy = Carbon::today();

        if ($this->fecha_inicio && $hoy->lt($this->fecha_inicio)) {
            return false;
        }

        if ($this->fecha_fin && $hoy->gt($this->fecha_fin)) {
            return false;
        }

        return true;
    }
}
