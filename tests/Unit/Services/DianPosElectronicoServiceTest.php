<?php

namespace Tests\Unit\Services;

use App\Models\FacturaElectronica;
use Tests\TestCase;

class DianPosElectronicoServiceTest extends TestCase
{
    public function test_calculo_cufe_sha384_oficial_dian(): void
    {
        $numFac = 'POS-10001';
        $fecFac = '2026-10-01';
        $horFac = '18:30:00';
        $valFac = 68000.00;
        $valImp = 5037.04;
        $nitEmisor = '901234567';
        $nitAdquirente = '222222222222';
        $claveTecnica = 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c';
        $tipoAmbiente = '2';

        $cufe = FacturaElectronica::calcularCufe(
            numFac: $numFac,
            fecFac: $fecFac,
            horFac: $horFac,
            valFac: $valFac,
            valImp: $valImp,
            nitEmisor: $nitEmisor,
            nitAdquirente: $nitAdquirente,
            claveTecnica: $claveTecnica,
            tipoAmbiente: $tipoAmbiente
        );

        // El CUFE DIAN debe ser un hash SHA-384 hexadecimal de 96 caracteres
        $this->assertEquals(96, strlen($cufe));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{96}$/', $cufe);

        // Idempotencia: el mismo input debe producir exactamente el mismo hash
        $cufe2 = FacturaElectronica::calcularCufe(
            numFac: $numFac,
            fecFac: $fecFac,
            horFac: $horFac,
            valFac: $valFac,
            valImp: $valImp,
            nitEmisor: $nitEmisor,
            nitAdquirente: $nitAdquirente,
            claveTecnica: $claveTecnica,
            tipoAmbiente: $tipoAmbiente
        );
        $this->assertEquals($cufe, $cufe2);
    }

    public function test_construccion_cadena_qr_oficial_dian(): void
    {
        $cadena = FacturaElectronica::construirCadenaQr(
            numFac: 'POS-10001',
            fecFac: '2026-10-01',
            horFac: '18:30:00',
            valFac: 68000.00,
            valImp: 5037.04,
            nitEmisor: '901234567-8',
            nitAdquirente: '222222222222',
            cufe: str_repeat('a', 96)
        );

        $this->assertStringContainsString('NumFac: POS-10001', $cadena);
        $this->assertStringContainsString('FecFac: 2026-10-01', $cadena);
        $this->assertStringContainsString('ValFac: 68000.00', $cadena);
        $this->assertStringContainsString('ValIva: 5037.04', $cadena);
        $this->assertStringContainsString('CUFE: '.str_repeat('a', 96), $cadena);
    }
}
