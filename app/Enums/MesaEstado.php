<?php

namespace App\Enums;

enum MesaEstado: string
{
    case LIBRE = 'libre';
    case OCUPADA = 'ocupada';
    case POR_LIMPIAR = 'por_limpiar';
    case RESERVADA = 'reservada';

    public function label(): string
    {
        return match ($this) {
            self::LIBRE => 'Libre',
            self::OCUPADA => 'Ocupada',
            self::POR_LIMPIAR => 'Por limpiar',
            self::RESERVADA => 'Reservada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::LIBRE => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
            self::OCUPADA => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
            self::POR_LIMPIAR => 'bg-rose-500/10 text-rose-500 border-rose-500/20',
            self::RESERVADA => 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        };
    }

    public function transicionesValidas(): array
    {
        return match ($this) {
            self::LIBRE => [self::OCUPADA, self::RESERVADA],
            self::OCUPADA => [self::POR_LIMPIAR, self::LIBRE],
            self::POR_LIMPIAR => [self::LIBRE],
            self::RESERVADA => [self::LIBRE, self::OCUPADA],
        };
    }

    public function puedeTransicionarA(MesaEstado|string $nuevo): bool
    {
        $target = $nuevo instanceof self ? $nuevo : self::tryFrom((string) $nuevo);
        if (! $target) {
            return false;
        }

        return in_array($target, $this->transicionesValidas(), true);
    }
}
