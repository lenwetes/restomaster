<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'token_hash',
    'canal',
    'enviada_por',
    'expira_at',
    'usada_at',
    'estado',
    'motivo_rechazo',
    'revisada_por',
])]
#[Table(name: 'vip_invitaciones')]
class VipInvitacion extends Model
{
    use HasFactory;

    public const ESTADO_VIGENTE = 'vigente';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_EXPIRADA = 'expirada';

    public const ESTADO_CANCELADA = 'cancelada';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function enviadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviada_por');
    }

    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    public function esVigente(): bool
    {
        return $this->estado === self::ESTADO_VIGENTE && $this->expira_at->isFuture() && is_null($this->usada_at);
    }

    protected function casts(): array
    {
        return [
            'expira_at' => 'datetime',
            'usada_at' => 'datetime',
        ];
    }
}
