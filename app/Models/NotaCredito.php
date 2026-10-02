<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'numero_nc',
    'pedido_id',
    'pedido_devolucion_id',
    'motivo',
    'descripcion',
    'autorizado_por',
    'sucursal_id',
    'monto',
])]
#[Table(name: 'notas_credito')]
class NotaCredito extends Model
{
    use HasFactory;

    public const MOTIVOS = [
        'error_cargo',
        'producto_defectuoso',
        'cambio_pedido',
        'otro',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(PedidoDevolucion::class, 'pedido_devolucion_id');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function estaUtilizada(): bool
    {
        return $this->pedido_devolucion_id !== null;
    }

    /**
     * Emite una Nota de Crédito con número único NC-{anio}-{sucursal}-{seq}.
     */
    public static function emitir(Pedido $pedido, string $motivo, User $autorizadoPor, float $monto, ?string $descripcion = null): self
    {
        if (! in_array($motivo, self::MOTIVOS, true)) {
            throw new \InvalidArgumentException('Motivo de Nota de Crédito no válido.');
        }

        if ($monto <= 0) {
            throw new \InvalidArgumentException('El monto de la Nota de Crédito debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($pedido, $motivo, $autorizadoPor, $monto, $descripcion) {
            $anio = (int) now()->year;
            $sucursalId = (int) ($pedido->sucursal_id ?? $autorizadoPor->sucursal_id ?? 0);

            $ultimo = self::where('sucursal_id', $sucursalId)
                ->where('numero_nc', 'like', "NC-{$anio}-{$sucursalId}-%")
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $seq = 1;
            if ($ultimo && preg_match('/-(\d+)$/', $ultimo->numero_nc, $m)) {
                $seq = ((int) $m[1]) + 1;
            }

            return self::create([
                'numero_nc' => sprintf('NC-%d-%d-%04d', $anio, $sucursalId, $seq),
                'pedido_id' => $pedido->id,
                'pedido_devolucion_id' => null,
                'motivo' => $motivo,
                'descripcion' => $descripcion,
                'autorizado_por' => $autorizadoPor->id,
                'sucursal_id' => $sucursalId,
                'monto' => round($monto, 2),
            ]);
        });
    }
}
