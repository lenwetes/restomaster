<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nombre',
    'codigo',
    'canal',
    'categoria',
    'asunto',
    'contenido',
    'whatsapp_template_name',
    'whatsapp_language_code',
    'activa',
])]
#[Table(name: 'crm_plantillas')]
class CrmPlantilla extends Model
{
    use HasFactory;

    public function automatizacionesWhatsapp(): HasMany
    {
        return $this->hasMany(CrmAutomatizacion::class, 'plantilla_whatsapp_id');
    }

    public function automatizacionesEmail(): HasMany
    {
        return $this->hasMany(CrmAutomatizacion::class, 'plantilla_email_id');
    }

    #[Scope]
    protected function activas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    #[Scope]
    protected function canal(Builder $query, string $canal): Builder
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

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }
}
