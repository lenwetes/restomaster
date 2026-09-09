<?php

namespace App\Enums;

enum PedidoEstado: string
{
    case CREADO = 'creado';
    case EN_COCINA = 'en_cocina';
    case LISTO = 'listo';
    case ENTREGADO = 'entregado';
    case PAGADO = 'pagado';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::CREADO => 'Creado',
            self::EN_COCINA => 'En Cocina',
            self::LISTO => 'Listo para Servir',
            self::ENTREGADO => 'Entregado a Mesa',
            self::PAGADO => 'Pagado',
            self::CANCELADO => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREADO => 'gray',
            self::EN_COCINA => 'amber',
            self::LISTO => 'blue',
            self::ENTREGADO => 'purple',
            self::PAGADO => 'emerald',
            self::CANCELADO => 'red',
        };
    }
}
