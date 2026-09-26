<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'crm_configuraciones';

    protected $fillable = [
        'sucursal_id',
        'whatsapp_proveedor',
        'whatsapp_phone_number_id',
        'whatsapp_waba_id',
        'whatsapp_access_token',
        'whatsapp_webhook_secret',
        'whatsapp_telefono_pruebas',
        'email_activo',
        'email_remitente_nombre',
        'email_remitente_correo',
        'horario_envio_inicio',
        'horario_envio_fin',
        'delay_encuesta_minutos',
        'winback_dias_inactividad',
    ];

    protected $casts = [
        'email_activo' => 'boolean',
        'delay_encuesta_minutos' => 'integer',
        'winback_dias_inactividad' => 'integer',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Obtiene la configuración activa (singleton por defecto si no se especifica sucursal).
     */
    public static function activa(?int $sucursalId = null): self
    {
        return static::firstOrCreate(
            ['sucursal_id' => $sucursalId],
            [
                'whatsapp_proveedor' => 'meta_cloud',
                'whatsapp_phone_number_id' => config('services.whatsapp.phone_number_id', ''),
                'whatsapp_waba_id' => config('services.whatsapp.waba_id', ''),
                'whatsapp_access_token' => config('services.whatsapp.token', ''),
                'whatsapp_webhook_secret' => config('services.whatsapp.webhook_verify_token', 'restomaster_crm_webhook'),
                'whatsapp_telefono_pruebas' => '+573001234567',
                'email_activo' => true,
                'email_remitente_nombre' => 'RestoMaster Experiencia',
                'email_remitente_correo' => 'experiencia@restomaster.com',
                'horario_envio_inicio' => '10:00',
                'horario_envio_fin' => '22:00',
                'delay_encuesta_minutos' => 20,
                'winback_dias_inactividad' => 45,
            ]
        );
    }
}
