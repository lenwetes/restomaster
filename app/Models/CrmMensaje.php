<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmMensaje extends Model
{
    use HasFactory;

    protected $table = 'crm_mensajes';

    protected $fillable = [
        'crm_conversacion_id',
        'emisor',
        'user_id',
        'contenido',
        'canal_origen',
        'estado_entrega',
        'wamid',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(CrmConversacion::class, 'crm_conversacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeDeCliente(Builder $query): Builder
    {
        return $query->where('emisor', 'cliente');
    }

    public function scopeDeBot(Builder $query): Builder
    {
        return $query->where('emisor', 'bot');
    }

    public function scopeDeStaff(Builder $query): Builder
    {
        return $query->where('emisor', 'staff');
    }
}
