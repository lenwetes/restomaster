<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteSocialAccount extends Model
{
    use HasFactory;

    protected $table = 'cliente_social_accounts';

    protected $fillable = [
        'cliente_id',
        'provider',
        'provider_id',
        'provider_token',
        'provider_refresh_token',
        'avatar_url',
        'nombre_proveedor',
        'email_proveedor',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
