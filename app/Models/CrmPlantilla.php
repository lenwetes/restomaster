<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPlantilla extends Model
{
    use HasFactory;

    protected $table = 'crm_plantillas';

    protected $fillable = [
        'nombre',
        'codigo',
        'canal',
        'categoria',
        'asunto',
        'contenido',
        'whatsapp_template_name',
        'whatsapp_language_code',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function automatizacionesWhatsapp(): HasMany
    {
        return $this->hasMany(CrmAutomatizacion::class, 'plantilla_whatsapp_id');
    }

    public function automatizacionesEmail(): HasMany
    {
        return $this->hasMany(CrmAutomatizacion::class, 'plantilla_email_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopeCanal(Builder $query, string $canal): Builder
    {
        return $query->where('canal', $canal);
    }

    /**
     * Reemplaza variables dinámicas en el contenido.
     * Ejemplo: {nombre}, {restaurante}, {url_encuesta}, {puntos_ganados}, etc.
     */
    public function renderizar(array $variables = []): string
    {
        $contenido = $this->contenido;
        foreach ($variables as $key => $val) {
            $contenido = str_replace('{'.$key.'}', (string) $val, $contenido);
        }

        return $contenido;
    }
}
