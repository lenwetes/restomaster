<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nombre',
    'evento_disparador',
    'canal',
    'plantilla_whatsapp_id',
    'plantilla_email_id',
    'delay_minutos',
    'activa',
    'condiciones',
    'total_disparos',
])]
#[Table(name: 'crm_automatizaciones')]
class CrmAutomatizacion extends Model
{
    use HasFactory;

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

    #[Scope]
    protected function activas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    #[Scope]
    protected function evento(Builder $query, string $evento): Builder
    {
        return $query->where('evento_disparador', $evento);
    }

    protected function casts(): array
    {
        return [
            'delay_minutos' => 'integer',
            'activa' => 'boolean',
            'condiciones' => 'array',
            'total_disparos' => 'integer',
        ];
    }
}
