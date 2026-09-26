<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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
        'email_driver',
        'email_smtp_host',
        'email_smtp_port',
        'email_smtp_username',
        'email_smtp_password',
        'email_smtp_encryption',
        'email_correo_pruebas',
        'horario_envio_inicio',
        'horario_envio_fin',
        'delay_encuesta_minutos',
        'winback_dias_inactividad',
        'ia_activo',
        'ia_plantilla_privilegio_id',
        'ia_proveedor',
        'ia_modelo',
        'ia_api_key',
        'ia_limite_mensajes_por_cliente_dia',
        'ia_mensaje_apagado',
    ];

    protected $casts = [
        'email_activo' => 'boolean',
        'email_smtp_port' => 'integer',
        'delay_encuesta_minutos' => 'integer',
        'winback_dias_inactividad' => 'integer',
        'ia_activo' => 'boolean',
        'ia_limite_mensajes_por_cliente_dia' => 'integer',
        'ia_plantilla_privilegio_id' => 'integer',
    ];

    /**
     * Accesor tolerante a fallos para WhatsApp Access Token (evita 500 por payload inválido o texto plano heredado).
     */
    public function getWhatsappAccessTokenAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setWhatsappAccessTokenAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['whatsapp_access_token'] = null;

            return;
        }

        try {
            Crypt::decryptString($value);
            $this->attributes['whatsapp_access_token'] = $value;
        } catch (\Throwable) {
            $this->attributes['whatsapp_access_token'] = Crypt::encryptString($value);
        }
    }

    /**
     * Accesor tolerante a fallos para API Key de IA.
     */
    public function getIaApiKeyAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setIaApiKeyAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['ia_api_key'] = null;

            return;
        }

        try {
            Crypt::decryptString($value);
            $this->attributes['ia_api_key'] = $value;
        } catch (\Throwable) {
            $this->attributes['ia_api_key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Accesor tolerante a fallos para contraseña SMTP.
     */
    public function getEmailSmtpPasswordAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setEmailSmtpPasswordAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['email_smtp_password'] = null;

            return;
        }

        try {
            Crypt::decryptString($value);
            $this->attributes['email_smtp_password'] = $value;
        } catch (\Throwable) {
            $this->attributes['email_smtp_password'] = Crypt::encryptString($value);
        }
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function plantillaIa(): BelongsTo
    {
        return $this->belongsTo(CrmIaPlantillaPrivilegio::class, 'ia_plantilla_privilegio_id');
    }

    /**
     * Aplica la configuración de correo dinámicamente si no está en modo .env estándar.
     */
    public function aplicarConfiguracionMailer(): void
    {
        if ($this->email_driver === 'log') {
            config(['mail.default' => 'log']);

            return;
        }

        if ($this->email_driver === 'smtp' && ! empty($this->email_smtp_host)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $this->email_smtp_host,
                'mail.mailers.smtp.port' => $this->email_smtp_port ?: 587,
                'mail.mailers.smtp.username' => $this->email_smtp_username,
                'mail.mailers.smtp.password' => $this->email_smtp_password,
                'mail.mailers.smtp.encryption' => $this->email_smtp_encryption ?: 'tls',
            ]);
        }

        if (! empty($this->email_remitente_correo)) {
            config([
                'mail.from.address' => $this->email_remitente_correo,
                'mail.from.name' => $this->email_remitente_nombre ?: config('app.name'),
            ]);
        }
    }

    /**
     * Determina si el asistente de IA está activo manualmente (Kill-Switch).
     */
    public function iaActiva(): bool
    {
        return (bool) $this->ia_activo;
    }

    /**
     * Obtiene la API Key desencriptada de la IA con fallback a variables de entorno.
     */
    public function obtenerApiKeyIa(): ?string
    {
        if (! empty($this->ia_api_key)) {
            return $this->ia_api_key;
        }

        return match ($this->ia_proveedor) {
            'openai' => config('services.openai.key', env('OPENAI_API_KEY')),
            default => config('services.gemini.key', env('GEMINI_API_KEY')),
        };
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
                'ia_activo' => false,
                'ia_proveedor' => 'gemini',
                'ia_modelo' => 'gemini-2.5-flash',
                'ia_limite_mensajes_por_cliente_dia' => 15,
                'ia_mensaje_apagado' => 'En este momento nuestro asistente virtual está en pausa. Para reservas o consultas urgentes, por favor comunícate a nuestra línea de atención telefónica.',
            ]
        );
    }
}
