<?php

namespace Database\Seeders;

use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Zona;
use App\Services\RotacionMeseroService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MeseroPruebaSeeder extends Seeder
{
    /**
     * Carga 10 meseros con nombres colombianos, teléfonos reales y
     * configura la auto-rotación de turnos por zona y asignación de mesas.
     */
    public function run(): void
    {
        $sucursal = Sucursal::first() ?? Sucursal::create([
            'nombre' => 'RestoMaster Gastro-Lounge',
            'direccion' => 'Zona G, Carrera 5 # 69-26, Bogotá',
            'telefono' => '+57 300 987 6543',
            'activo' => true,
        ]);

        $meseroRole = Role::firstOrCreate(
            ['slug' => 'mesero'],
            ['nombre' => 'Mesero']
        );

        $rawPassword = env('DEMO_USERS_PASSWORD') ?: 'password';
        $hashedPassword = Hash::make($rawPassword);

        // 10 Meseros con perfiles colombianos representativos
        $meserosData = [
            [
                'name' => 'Carlos Andrés Restrepo Gómez',
                'email' => 'carlos.restrepo@restomaster.com',
                'telefono' => '+57 310 456 7890',
                'zona_slug' => 'salon',
                'orden' => 1,
            ],
            [
                'name' => 'Valentina Morales Jaramillo',
                'email' => 'valentina.morales@restomaster.com',
                'telefono' => '+57 312 876 5432',
                'zona_slug' => 'salon',
                'orden' => 2,
            ],
            [
                'name' => 'Mateo Echeverry Londoño',
                'email' => 'mateo.echeverry@restomaster.com',
                'telefono' => '+57 315 234 5678',
                'zona_slug' => 'salon',
                'orden' => 3,
            ],
            [
                'name' => 'Alejandro Serna Henao',
                'email' => 'alejandro.serna@restomaster.com',
                'telefono' => '+57 311 654 9870',
                'zona_slug' => 'salon',
                'orden' => 4,
            ],
            [
                'name' => 'Daniela Sofía Ospina Caicedo',
                'email' => 'daniela.ospina@restomaster.com',
                'telefono' => '+57 320 987 1234',
                'zona_slug' => 'terraza',
                'orden' => 1,
            ],
            [
                'name' => 'Juan Camilo Benítez Montoya',
                'email' => 'juan.benitez@restomaster.com',
                'telefono' => '+57 301 543 8901',
                'zona_slug' => 'terraza',
                'orden' => 2,
            ],
            [
                'name' => 'Camila Andrea Pardo Cárdenas',
                'email' => 'camila.pardo@restomaster.com',
                'telefono' => '+57 318 654 3210',
                'zona_slug' => 'barra',
                'orden' => 1,
            ],
            [
                'name' => 'Sebastián Duque Quintero',
                'email' => 'sebastian.duque@restomaster.com',
                'telefono' => '+57 300 789 0123',
                'zona_slug' => 'barra',
                'orden' => 2,
            ],
            [
                'name' => 'Mariana Botero Zuluaga',
                'email' => 'mariana.botero@restomaster.com',
                'telefono' => '+57 314 321 0987',
                'zona_slug' => 'vip',
                'orden' => 1,
            ],
            [
                'name' => 'Santiago Villa Arango',
                'email' => 'santiago.villa@restomaster.com',
                'telefono' => '+57 316 789 4321',
                'zona_slug' => 'vip',
                'orden' => 2,
            ],
        ];

        $meserosPorZona = [];

        foreach ($meserosData as $m) {
            $user = User::updateOrCreate(
                ['email' => $m['email']],
                [
                    'name' => $m['name'],
                    'telefono' => $m['telefono'],
                    'role_id' => $meseroRole->id,
                    'sucursal_id' => $sucursal->id,
                    'activo' => true,
                    'password' => $hashedPassword,
                    'email_verified_at' => now(),
                ]
            );

            $meserosPorZona[$m['zona_slug']][] = $user->id;
        }

        // Configurar Zonas si no existen
        $zonasConfig = [
            'salon' => ['nombre' => 'Salón Principal', 'color' => 'terracota', 'icono' => 'mesa', 'orden' => 1],
            'barra' => ['nombre' => 'Barra / Bar', 'color' => 'lavanda', 'icono' => 'barra', 'orden' => 2],
            'terraza' => ['nombre' => 'Terraza', 'color' => 'salvia', 'icono' => 'terraza', 'orden' => 3],
            'vip' => ['nombre' => 'Área VIP', 'color' => 'indigo', 'icono' => 'vip', 'orden' => 4],
        ];

        $rotacionService = app(RotacionMeseroService::class);

        foreach ($zonasConfig as $slug => $zData) {
            $zona = Zona::firstOrCreate(
                ['sucursal_id' => $sucursal->id, 'slug' => $slug],
                $zData + ['activa' => true]
            );

            $idsMeserosZona = $meserosPorZona[$slug] ?? [];
            if (! empty($idsMeserosZona)) {
                $rotacionService->configurarRotacion(
                    $zona,
                    $idsMeserosZona,
                    'automatico',
                    $sucursal->id
                );
            }
        }

        // Configurar modo de rotación de la sucursal en round_robin
        $rotacionService->guardarModoRotacion($sucursal->id, 'round_robin');

        // Asignar meseros a mesas existentes para reflejar estado operativo en tiempo real
        $mesas = Mesa::where('sucursal_id', $sucursal->id)->get();
        if ($mesas->isNotEmpty()) {
            foreach ($mesas as $mesa) {
                $slugZona = strtolower(trim($mesa->zona));
                $ids = $meserosPorZona[$slugZona] ?? $meserosPorZona['salon'] ?? [];
                if (! empty($ids)) {
                    // Asignar de forma determinista para variedad de visualización
                    $meseroAsignadoId = $ids[$mesa->id % count($ids)];
                    $mesa->update(['mesero_id' => $meseroAsignadoId]);
                }
            }
        }

        // Vincular pedidos de prueba a meseros si no tienen uno asignado
        $pedidosSinMesero = Pedido::where('sucursal_id', $sucursal->id)->whereNull('mesero_id')->get();
        $primerMeseroId = $meserosPorZona['salon'][0] ?? null;
        if ($primerMeseroId && $pedidosSinMesero->isNotEmpty()) {
            foreach ($pedidosSinMesero as $idx => $ped) {
                $todosIds = array_merge(...array_values($meserosPorZona));
                $mId = $todosIds[$idx % count($todosIds)] ?? $primerMeseroId;
                $ped->update(['mesero_id' => $mId]);
            }
        }

        if ($this->command) {
            $this->command->info('✓ 10 meseros colombianos creados con auto-rotación y asignación de mesas configurada.');
        }
    }
}
