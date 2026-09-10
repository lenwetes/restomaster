<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    /**
     * Generar el código SVG de un QR a partir de un texto o URL.
     */
    public function generarSvg(string $texto, int $tamano = 300): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($tamano, 1),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($texto);
    }

    /**
     * Generar URI en base64 para incrustar directamente en tags <img>.
     */
    public function generarDataUri(string $texto, int $tamano = 300): string
    {
        $svg = $this->generarSvg($texto, $tamano);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Obtener la URL pública oficial para el menú QR de una mesa.
     */
    public function urlParaMesa(string|int $numeroMesa): string
    {
        return url('/m/'.$numeroMesa);
    }
}
