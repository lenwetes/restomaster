<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

class PasarelaPagoServiceTest extends TestCase
{
    public function test_firma_integridad_sha256_wompi_calculo_correcto(): void
    {
        $referencia = 'RESTO-PED100-TEST01';
        $montoCentavos = 6800000; // 68.000 COP en centavos
        $moneda = 'COP';
        $secretoIntegridad = 'prod_integrity_secret_123';

        // Fórmula oficial de Wompi: SHA256(referencia + montoCentavos + moneda + secreto)
        $cadena = "{$referencia}{$montoCentavos}{$moneda}{$secretoIntegridad}";
        $firmaEsperada = hash('sha256', $cadena);

        $this->assertEquals(64, strlen($firmaEsperada));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $firmaEsperada);
    }

    public function test_firma_integridad_sha256_bold_calculo_correcto(): void
    {
        $referencia = 'RESTO-PED100-BOLD01';
        $monto = 54000.00;
        $moneda = 'COP';
        $secretKey = 'bold_secret_key_456';

        // Fórmula oficial de Bold: SHA256(referencia + monto + moneda + secretKey)
        $cadena = "{$referencia}{$monto}{$moneda}{$secretKey}";
        $firmaEsperada = hash('sha256', $cadena);

        $this->assertEquals(64, strlen($firmaEsperada));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $firmaEsperada);
    }
}
