<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'tipo', 'titulo', 'cuerpo', 'datos', 'leida', 'leida_en', 'created_at'])]
class NotificacionUsuario extends Model
{
    use HasFactory;

    protected $table = 'notificaciones_usuario';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'leida' => 'boolean',
            'leida_en' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marcarComoLeida(): void
    {
        $this->update([
            'leida' => true,
            'leida_en' => now(),
        ]);
    }
}
