<?php

namespace App\Enums;

enum PedidoEstado: string
{
    case SOLICITADO_QR = 'solicitado_qr';
    case CREADO = 'creado';
    case EN_COCINA = 'en_cocina';
    case EN_PROCESO = 'en_proceso';
    case LISTO = 'listo';
    case ENTREGADO = 'entregado';
    case PAGADO = 'pagado';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::SOLICITADO_QR => 'Solicitado QR',
            self::CREADO => 'Creado',
            self::EN_COCINA => 'En Cocina',
            self::EN_PROCESO => 'En Preparación',
            self::LISTO => 'Listo para Servir',
            self::ENTREGADO => 'Entregado a Mesa',
            self::PAGADO => 'Pagado',
            self::CANCELADO => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SOLICITADO_QR => 'amber',
            self::CREADO => 'gray',
            self::EN_COCINA => 'amber',
            self::EN_PROCESO => 'orange',
            self::LISTO => 'blue',
            self::ENTREGADO => 'purple',
            self::PAGADO => 'emerald',
            self::CANCELADO => 'red',
        };
    }
}
