<?php

namespace App\Console\Commands;

use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LimpiezaInicioDiaCommand extends Command
{
    protected $signature = 'limpieza:inicio-dia {--force : Ejecuta el borrado. Sin el flag solo muestra el conteo (dry-run)}';

    protected $description = 'Deja el día en cero (SOLO desarrollo): elimina pedidos+items y las mesas no-libres o asignadas. Prohibido en producción.';

    public function handle(): int
    {
        abort_if(app()->isProduction(), 403, 'limpieza:inicio-dia prohibido en producción.');

        $mesaIds = Mesa::where('estado', '!=', 'libre')->orWhereNotNull('mesero_id')->pluck('id');
        $conteo = [
            'pedidos' => Pedido::count(),
            'pedidos_activos' => Pedido::activos()->count(),
            'items' => ItemPedido::count(),
            'mesas_objetivo' => $mesaIds->count(),
            'vínculos_reserva' => DB::table('reserva_mesa')->whereIn('mesa_id', $mesaIds)->count(),
        ];

        if (! $this->option('force')) {
            $this->info('DRY-RUN — no se borró nada. Usa --force para ejecutar.');
            foreach ($conteo as $clave => $valor) {
                $this->line("  {$clave}: {$valor}");
            }

            return self::SUCCESS;
        }

        DB::transaction(function () use ($mesaIds, &$borrados) {
            $borrados = [
                'items' => ItemPedido::query()->delete(),
                'pedidos' => Pedido::query()->delete(),
                'vínculos_reserva' => DB::table('reserva_mesa')->whereIn('mesa_id', $mesaIds)->delete(),
                'mesas' => Mesa::whereIn('id', $mesaIds)->delete(),
            ];
        });

        Cache::forget('pos.terminal.mesas');

        $this->info('Limpieza completada. Día en cero:');
        foreach ($borrados as $clave => $valor) {
            $this->line("  {$clave} eliminados: {$valor}");
        }

        return self::SUCCESS;
    }
}
