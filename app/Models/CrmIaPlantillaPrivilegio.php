<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmIaPlantillaPrivilegio extends Model
{
    use HasFactory;

    protected $table = 'crm_ia_plantillas_privilegios';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'slug',
        'descripcion',
        'es_sistema',
        'permitir_menu',
        'permitir_precios',
        'permitir_alergenos',
        'permitir_verificar_mesas',
        'permitir_crear_reservas',
        'max_personas_reserva',
        'permitir_cancelar_reservas',
        'permitir_promociones',
        'permitir_puntos_vip',
        'directivas_sistema',
        'tono_conducta',
        'prompt_personalidad',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
        'permitir_menu' => 'boolean',
        'permitir_precios' => 'boolean',
        'permitir_alergenos' => 'boolean',
        'permitir_verificar_mesas' => 'boolean',
        'permitir_crear_reservas' => 'boolean',
        'max_personas_reserva' => 'integer',
        'permitir_cancelar_reservas' => 'boolean',
        'permitir_promociones' => 'boolean',
        'permitir_puntos_vip' => 'boolean',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function configuraciones(): HasMany
    {
        return $this->hasMany(CrmConfiguracion::class, 'ia_plantilla_privilegio_id');
    }

    /**
     * Plantilla recomendada por defecto (Hostess Completa).
     */
    public static function defaultHostess(): ?self
    {
        return static::where('slug', 'hostess-completa')->first();
    }
}
