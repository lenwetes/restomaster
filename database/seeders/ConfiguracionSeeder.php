<?php

namespace Database\Seeders;

use App\Services\ConfiguracionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(ConfiguracionService::class);
        $defaults = [
            'general' => [
                'razon_social' => 'SushiXpress S.A.S.',
                'nit' => '',
                'direccion' => '',
                'telefono' => '',
                'regimen' => 'Común',
            ],
            'dian' => [
                'envio_activo' => false,
                'ambiente' => 'habilitacion',
                'tipo_documento' => '01',
                'resolucion_numero' => '',
                'resolucion_fecha' => null,
                'prefijo' => 'MP',
                'desde' => null,
                'hasta' => null,
                'vigente' => false,
            ],
            'reservas' => [
                'webhook_token' => Str::random(48),
                'webhook_activo' => false,
            ],
            'impresion' => [
                'pie_ticket' => '¡Gracias por preferir SushiXpress!',
            ],
        ];

        foreach ($defaults as $grupo => $claves) {
            foreach ($claves as $clave => $valor) {
                $svc->obtener($grupo, $clave, 'no-existe') === 'no-existe'
                    ? $svc->guardar($grupo, $clave, $valor)
                    : null;
            }
        }
    }
}