<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'provider',
    'provider_id',
    'provider_token',
    'provider_refresh_token',
    'avatar_url',
    'nombre_proveedor',
    'email_proveedor',
])]
#[Table(name: 'cliente_social_accounts')]
class ClienteSocialAccount extends Model
{
    use HasFactory;

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
