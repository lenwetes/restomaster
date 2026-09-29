<?php

namespace App\Livewire\Concerns;

use App\Models\Promocion;
use App\Models\PromocionDifusion;

trait RegistraDifusionSocial
{
    public const CANALES_SOCIAL_VALIDOS = [
        'social_whatsapp',
        'social_facebook',
        'social_instagram',
        'social_nativo',
    ];

    public function registrarDifusion(int $promocionId, string $canal): void
    {
        if (! in_array($canal, self::CANALES_SOCIAL_VALIDOS, true)) {
            $this->addError('canal', 'Canal no válido.');

            return;
        }

        $promocion = Promocion::find($promocionId);

        if (! $promocion) {
            $this->addError('promocionId', 'La promoción no existe.');

            return;
        }

        PromocionDifusion::create([
            'promocion_id' => $promocion->id,
            'user_id' => auth()->id(),
            'canal' => $canal,
            'segmento' => 'publico_web',
            'total_destinatarios' => 1,
            'total_exitosos' => 1,
            'total_fallidos' => 0,
            'estado' => 'completado',
            'detalles' => [
                'url' => route('promociones.detalle', $promocion->slug),
                'origen' => 'compartir_social',
            ],
            'iniciado_at' => now(),
            'completado_at' => now(),
        ]);
    }
}
