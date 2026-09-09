<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Str;

class ConfiguracionService
{
    public function obtener(string $grupo, string $clave, mixed $default = null): mixed
    {
        $config = Configuracion::where('grupo', $grupo)->where('clave', $clave)->first();

        return $config?->valor ?? $default;
    }

    public function guardar(string $grupo, string $clave, mixed $valor): void
    {
        Configuracion::updateOrCreate(
            ['grupo' => $grupo, 'clave' => $clave],
            ['valor' => $valor],
        );
    }

    public function regenerarWebhookToken(): string
    {
        $token = Str::random(48);

        $this->guardar('reservas', 'webhook_token', $token);

        return $token;
    }
}