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
                'razon_social' => 'RestoMaster Colombia S.A.S.',
                'nit' => '901.458.789-3',
                'direccion' => 'Cra 35 # 8A-12, El Poblado',
                'telefono' => '+57 300 123 4567',
                'ciudad' => 'Medellín, Colombia',
                'regimen' => 'Común',
                'moneda' => 'COP',
                'simbolo_moneda' => '$',
                'impuesto_porcentaje' => 8.0,
                'costo_envio_base' => 8000.0,
            ],
            'dian' => [
                'envio_activo' => false,
                'ambiente' => 'habilitacion',
                'tipo_documento' => '01',
                'resolucion_numero' => '1876400001234',
                'resolucion_fecha' => '2026-01-15',
                'prefijo' => 'MP',
                'desde' => '1',
                'hasta' => '50000',
                'vigente' => true,
            ],
            'ticket_80mm' => $svc->valoresPorDefectoTicket80mm(),
            'reservas' => [
                'webhook_token' => Str::random(48),
                'webhook_activo' => false,
            ],
            'database_external' => [
                'host' => '127.0.0.1',
                'port' => 5432,
                'database' => 'restomaster',
                'username' => 'postgres',
                'password' => '',
                'sslmode' => 'prefer',
                'activo' => false,
            ],
            'impresion' => [
                'pie_ticket' => '¡Gracias por preferir RestoMaster!',
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
