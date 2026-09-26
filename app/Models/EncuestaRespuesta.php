<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncuestaRespuesta extends Model
{
    use HasFactory;

    protected $table = 'encuesta_respuestas';

    protected $fillable = [
        'envio_id',
        'pregunta_indice',
        'tipo_respuesta',
        'valor_estrellas',
        'valor_texto',
        'valor_booleano',
    ];

    protected function casts(): array
    {
        return [
            'pregunta_indice' => 'integer',
            'valor_estrellas' => 'integer',
            'valor_booleano' => 'boolean',
        ];
    }

    public function envio(): BelongsTo
    {
        return $this->belongsTo(EncuestaEnvio::class, 'envio_id');
    }
}
