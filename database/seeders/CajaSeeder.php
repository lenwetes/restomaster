<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Database\Seeder;

class CajaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sucursal = Sucursal::first() ?? Sucursal::create([
            'nombre' => 'Matriz Central',
            'codigo' => 'MAT-01',
            'direccion' => 'Av. Gastronómica 123',
            'telefono' => '55-1234-5678',
            'activa' => true,
        ]);

        $cajaPrincipal = Caja::firstOrCreate(
            ['codigo' => 'CAJ-01'],
            [
                'sucursal_id' => $sucursal->id,
                'nombre' => 'Caja Principal #01 - Salón',
                'activa' => true,
            ]
        );

        Caja::firstOrCreate(
            ['codigo' => 'CAJ-02'],
            [
                'sucursal_id' => $sucursal->id,
                'nombre' => 'Caja Barra #02',
                'activa' => true,
            ]
        );

        // Open an initial shift if none is open
        if (!$cajaPrincipal->turnoActivo()) {
            $cajero = User::whereHas('role', fn($q) => $q->where('slug', 'cajero'))->first()
                ?? User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->first();

            if ($cajero) {
                $cajaService = app(CajaService::class);
                $turno = $cajaService->abrirTurno($cajaPrincipal, $cajero, 150000.00, 'Fondo inicial base de apertura');

                // Add sample authorized petty expenses for realism
                $cajaService->registrarMovimiento(
                    $turno,
                    'egreso',
                    35000.00,
                    'Compra hielo y limones para barra',
                    'efectivo',
                    'FAC-9812',
                    'Gerente Turno'
                );

                $cajaService->registrarMovimiento(
                    $turno,
                    'egreso',
                    30000.00,
                    'Rollos de papel térmico 80mm para POS',
                    'efectivo',
                    'FAC-9815',
                    'Administrador'
                );
            }
        }
    }
}
