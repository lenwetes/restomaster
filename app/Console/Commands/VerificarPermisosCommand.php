<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Description('Lista usuarios con permisos explícitos y sus grants/denies (dry-run de revisión).')]
#[Signature('permisos:verificar')]
class VerificarPermisosCommand extends Command
{
    public function handle(): int
    {
        $filas = DB::table('permission_user as pu')
            ->join('users as u', 'u.id', '=', 'pu.user_id')
            ->select('u.name', 'u.email', 'pu.permission', 'pu.tipo')
            ->orderBy('u.email')
            ->orderBy('pu.permission')
            ->get();

        if ($filas->isEmpty()) {
            $this->info('Sin permisos explícitos: todo el sistema corre con lógica legacy por rol.');

            return self::SUCCESS;
        }

        foreach ($filas->groupBy('email') as $email => $grupo) {
            $this->line("{$grupo->first()->name} <{$email}>:");
            foreach ($grupo as $fila) {
                $marca = $fila->tipo === 'grant' ? '＋' : '－';
                $this->line("  {$marca} {$fila->permission}");
            }
        }

        return self::SUCCESS;
    }
}
