<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CrmConversacion extends Model
{
    use HasFactory;

    protected $table = 'crm_conversaciones';

    protected $fillable = [
        'ticket_codigo',
        'sucursal_id',
        'canal',
        'identificador_remoto',
        'session_token',
        'cliente_id',
        'user_id_asignado',
        'modo_atencion',
        'estado',
        'nombre_contacto',
        'ultimo_mensaje_texto',
        'ultimo_mensaje_at',
        'resumen_contexto',
        'no_leidos_staff',
        'no_leidos_cliente',
    ];

    protected static function booted(): void
    {
        static::creating(function (CrmConversacion $conversacion) {
            if (empty($conversacion->ticket_codigo)) {
                $prefijo = $conversacion->canal === 'whatsapp' ? 'WA' : 'WEB';
                $correlativo = str_pad((string) (static::max('id') + 1), 4, '0', STR_PAD_LEFT);
                $aleatorio = strtoupper(Str::random(3));
                $conversacion->ticket_codigo = "CHT-{$prefijo}-{$correlativo}{$aleatorio}";
            }
        });
    }

    protected $casts = [
        'ultimo_mensaje_at' => 'datetime',
        'no_leidos_staff' => 'integer',
        'no_leidos_cliente' => 'integer',
    ];

    public function mensajes(): HasMany
    {
        return $this->hasMany(CrmMensaje::class, 'crm_conversacion_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuarioAsignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_asignado');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', '!=', 'cerrada');
    }

    public function scopeRequierenHumano(Builder $query): Builder
    {
        return $query->where('estado', 'esperando_humano')
            ->orWhere('modo_atencion', 'humano');
    }

    public function scopePorCanal(Builder $query, string $canal): Builder
    {
        return $query->where('canal', $canal);
    }

    public function marcarLeidaPorStaff(): void
    {
        $this->update(['no_leidos_staff' => 0]);
    }

    public function cambiarModoAtencion(string $modo, ?int $userId = null): void
    {
        $datos = [
            'modo_atencion' => $modo,
            'estado' => $modo === 'humano' ? 'activa' : 'activa',
        ];

        if ($userId !== null) {
            $datos['user_id_asignado'] = $userId;
        }

        $this->update($datos);
    }
}
