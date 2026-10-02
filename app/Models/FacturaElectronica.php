<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_id',
    'sucursal_id',
    'tipo_documento',
    'prefijo',
    'consecutivo',
    'numero_factura',
    'cufe',
    'qr_cadena',
    'qr_imagen_url',
    'estado',
    'total',
    'impuesto',
    'cliente_nit',
    'cliente_nombre',
    'proveedor_tecnologico',
    'respuesta_proveedor',
    'error_mensaje',
    'emitida_en',
])]
#[Table(name: 'facturas_electronicas')]
class FacturaElectronica extends Model
{
    use HasFactory;

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Calcula el CUFE según el estándar DIAN para documentos electrónicos (SHA-384).
     *
     * Fórmula DIAN:
     * CUFE = SHA384(NumFac + FecFac + HorFac + ValFac + CodImp1 + ValImp1 + CodImp2 + ValImp2 + ... + NitOBL + NumAdq + ClaveTecnica + TipoAmbiente)
     */
    public static function calcularCufe(
        string $numFac,
        string $fecFac,
        string $horFac,
        float $valFac,
        float $valImp,
        string $nitEmisor,
        string $nitAdquirente,
        string $claveTecnica,
        string $tipoAmbiente = '1'
    ): string {
        $valFacStr = number_format($valFac, 2, '.', '');
        $valImpStr = number_format($valImp, 2, '.', '');

        $cadena = $numFac.
            $fecFac.
            $horFac.
            $valFacStr.
            '01'. // Código Impuesto IVA/INC
            $valImpStr.
            '04'. // Impuesto Consumo 0 si aplica
            '0.00'.
            $valFacStr. // Total
            $nitEmisor.
            $nitAdquirente.
            $claveTecnica.
            $tipoAmbiente;

        return hash('sha384', $cadena);
    }

    /**
     * Genera la cadena para el código QR reglamentario DIAN.
     */
    public static function construirCadenaQr(
        string $numFac,
        string $fecFac,
        string $horFac,
        float $valFac,
        float $valImp,
        string $nitEmisor,
        string $nitAdquirente,
        string $cufe,
        string $urlValidacion = 'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='
    ): string {
        $valFacStr = number_format($valFac, 2, '.', '');
        $valImpStr = number_format($valImp, 2, '.', '');

        return implode("\n", [
            "NumFac: {$numFac}",
            "FecFac: {$fecFac}",
            "HorFac: {$horFac}",
            "NitFac: {$nitEmisor}",
            "DocAdq: {$nitAdquirente}",
            "ValFac: {$valFacStr}",
            "ValIva: {$valImpStr}",
            'ValOtro: 0.00',
            "ValTolFac: {$valFacStr}",
            "CUFE: {$cufe}",
            "QRCode: {$urlValidacion}{$cufe}",
        ]);
    }

    protected function casts(): array
    {
        return [
            'consecutivo' => 'integer',
            'total' => 'decimal:2',  // decimal exacto — nunca float en campos DIAN
            'impuesto' => 'decimal:2',
            'respuesta_proveedor' => 'array',
            'emitida_en' => 'datetime',
        ];
    }
}
