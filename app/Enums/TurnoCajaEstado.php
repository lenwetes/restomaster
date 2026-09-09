<?php

namespace App\Enums;

enum TurnoCajaEstado: string
{
    case ABIERTO = 'abierto';
    case CERRADO = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::ABIERTO => 'Turno Abierto',
            self::CERRADO => 'Turno Cerrado',
        };
    }
}
