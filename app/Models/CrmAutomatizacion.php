<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmAutomatizacion extends Model
{
    use HasFactory;

    protected $table = 'crm_automatizaciones';

    protected $fillable = [
        'nombre',
        'evento_disparador',
        'canal',
        'plantilla_whatsapp_id',
        'plantilla_email_id',
        'delay_minutos',
        'activa',
        'condiciones',
        'total_disparos',
    ];

    protected $casts = [
        'delay_minutos' => 'integer',
        'activa' => 'boolean',
        'condiciones' => 'array',
        'total_disparos' => 'integer',
    ];

    public function plantillaWhatsapp(): BelongsTo
    {
        return $this->belongsTo(CrmPlantilla::class, 'plantilla_whatsapp_id');
    }

    public function plantillaEmail(): BelongsTo
    {
        return $this->belongsTo(CrmPlantilla::class, 'plantilla_email_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CrmMensajeLog::class, 'automatizacion_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopeEvento(Builder $query, string $evento): Builder
    {
        return $query->where('evento_disparador', $evento);
    }
}
