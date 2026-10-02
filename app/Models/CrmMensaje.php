<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'crm_conversacion_id',
    'emisor',
    'user_id',
    'contenido',
    'canal_origen',
    'estado_entrega',
    'wamid',
    'metadata',
])]
#[Table(name: 'crm_mensajes')]
class CrmMensaje extends Model
{
    use HasFactory;

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(CrmConversacion::class, 'crm_conversacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    #[Scope]
    protected function deCliente(Builder $query): Builder
    {
        return $query->where('emisor', 'cliente');
    }

    #[Scope]
    protected function deBot(Builder $query): Builder
    {
        return $query->where('emisor', 'bot');
    }

    #[Scope]
    protected function deStaff(Builder $query): Builder
    {
        return $query->where('emisor', 'staff');
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
