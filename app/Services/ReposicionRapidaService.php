<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\Insumo;
use App\Models\MovimientoCaja;
use App\Models\Proveedor;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReposicionRapidaService
{
    public function __construct(
        protected InventarioService $inventarioService,
        protected CompraService $compraService,
        protected AuditoriaService $auditoriaService
    ) {}

    /**
     * Prepara y retorna la información consolidada para el modal de reposición de un insumo.
     */
    public function prepararDatosInsumo(int $insumoId): array
    {
        $insumo = Insumo::with('proveedor')->findOrFail($insumoId);

        $stockActual = (float) $insumo->stock_actual;
        $stockMinimo = (float) $insumo->stock_minimo;
        $capacidadMax = (float) $insumo->capacidad_maxima;

        // Sugerencia de reposición óptima
        $sugerido = $capacidadMax > $stockMinimo
            ? max(1.0, round($capacidadMax - $stockActual, 2))
            : max(1.0, round(($stockMinimo * 1.5) - $stockActual, 2));

        $costoUnitario = (float) $insumo->costo_unitario > 0
            ? (float) $insumo->costo_unitario
            : (float) $insumo->precio_referencia_mercado;

        $proveedores = Proveedor::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'nit', 'telefono', 'email', 'contacto']);

        $turnoAbierto = TurnoCaja::whereNull('cierre_en')
            ->latest('apertura_en')
            ->first();

        $proveedoresList = $proveedores->map(fn ($p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'nit' => $p->nit,
            'telefono' => $p->telefono,
            'email' => $p->email,
            'contacto' => $p->contacto,
        ])->values()->all();

        return [
            'insumo_id' => $insumo->id,
            'insumo_nombre' => $insumo->nombre,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
            'unidad_medida' => $insumo->unidad_medida,
            'cantidad_sugerida' => $sugerido,
            'costo_unitario_estimado' => $costoUnitario,
            'proveedor_habitual_id' => $insumo->proveedor?->id,
            'proveedor_habitual_nombre' => $insumo->proveedor?->nombre,
            'proveedores_disponibles' => $proveedoresList,
            'hay_turno_caja_abierto' => $turnoAbierto !== null,
            'turno_caja_id' => $turnoAbierto?->id,
        ];
    }

    /**
     * Crea un proveedor rápidamente y lo asocia de forma inmediata al insumo.
     */
    public function crearProveedorRapido(int $insumoId, array $datos): Proveedor
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del proveedor es requerido.');
        }

        return DB::transaction(function () use ($insumoId, $datos, $nombre) {
            $insumo = Insumo::lockForUpdate()->findOrFail($insumoId);

            $proveedor = Proveedor::create([
                'nombre' => $nombre,
                'nit' => trim((string) ($datos['nit'] ?? '')) ?: null,
                'telefono' => trim((string) ($datos['telefono'] ?? '')) ?: null,
                'email' => trim((string) ($datos['email'] ?? '')) ?: null,
                'contacto' => trim((string) ($datos['contacto'] ?? '')) ?: null,
                'direccion' => trim((string) ($datos['direccion'] ?? '')) ?: 'Medellín, Antioquia',
                'dias_credito' => (int) ($datos['dias_credito'] ?? 0),
                'activo' => true,
            ]);

            $insumo->update([
                'proveedor_id' => $proveedor->id,
                'proveedor_nombre' => $proveedor->nombre,
                'proveedor_telefono' => $proveedor->telefono,
                'proveedor_nit' => $proveedor->nit,
            ]);

            $this->auditoriaService->registrar(
                accion: 'proveedor.creado_rapido',
                entidad: 'proveedor',
                entidadId: $proveedor->id,
                descripcion: "Proveedor rápido '{$proveedor->nombre}' creado y asociado al insumo '{$insumo->nombre}'.",
                datos: ['insumo_id' => $insumo->id]
            );

            return $proveedor;
        });
    }

    /**
     * Genera el payload de enlace y mensaje para WhatsApp y Correo para un pedido de insumo.
     */
    public function generarEnlacesPedido(Insumo $insumo, Proveedor $proveedor, float $cantidad): array
    {
        $nombreContacto = $proveedor->contacto ?: $proveedor->nombre;
        $textoMensaje = "Hola {$nombreContacto}, cordial saludo de RestoMaster. "
            ."Necesitamos solicitar el siguiente pedido de urgencia:\n"
            ."• *{$cantidad} {$insumo->unidad_medida}* de *{$insumo->nombre}*\n\n"
            .'¿Nos podrías confirmar disponibilidad y tiempo estimado de entrega? ¡Muchas gracias!';

        // Normalizar teléfono (remover símbolos no numéricos)
        $telefonoRaw = preg_replace('/[^0-9]/', '', (string) $proveedor->telefono);
        if ($telefonoRaw !== '' && strlen($telefonoRaw) === 10 && str_starts_with($telefonoRaw, '3')) {
            $telefonoLimpio = '57'.$telefonoRaw;
        } else {
            $telefonoLimpio = $telefonoRaw;
        }

        $urlWhatsApp = $telefonoLimpio !== ''
            ? 'https://wa.me/'.$telefonoLimpio.'?text='.rawurlencode($textoMensaje)
            : null;

        $asuntoEmail = "Orden de Pedido Urgente — RestoMaster: {$insumo->nombre}";
        $urlEmail = $proveedor->email
            ? 'mailto:'.$proveedor->email.'?subject='.rawurlencode($asuntoEmail).'&body='.rawurlencode($textoMensaje)
            : null;

        return [
            'texto' => $textoMensaje,
            'telefono' => $telefonoLimpio,
            'email' => $proveedor->email,
            'url_whatsapp' => $urlWhatsApp,
            'url_email' => $urlEmail,
        ];
    }

    /**
     * Registra una orden de pedido al proveedor en estado 'solicitada' para trazabilidad.
     */
    public function registrarOrdenPedidoPendiente(
        Insumo $insumo,
        int $proveedorId,
        float $cantidad,
        ?float $costoEstimado = null,
        ?User $usuario = null
    ): Compra {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad solicitada debe ser mayor a cero.');
        }

        $proveedor = Proveedor::findOrFail($proveedorId);
        $costo = $costoEstimado !== null && $costoEstimado >= 0
            ? $costoEstimado
            : (float) $insumo->costo_unitario;

        $subtotal = round($cantidad * $costo, 2);
        $numeroFactura = 'ORD-'.date('Ymd-His');

        return DB::transaction(function () use ($insumo, $proveedor, $cantidad, $costo, $subtotal, $numeroFactura, $usuario) {
            $compra = Compra::create([
                'proveedor_id' => $proveedor->id,
                'numero_factura' => $numeroFactura,
                'fecha' => today(),
                'subtotal' => $subtotal,
                'forma_pago' => 'contado',
                'estado' => 'solicitada',
                'user_id' => $usuario?->id ?? auth()->id() ?? 1,
            ]);

            CompraLinea::create([
                'compra_id' => $compra->id,
                'insumo_id' => $insumo->id,
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
                'subtotal' => $subtotal,
            ]);

            $this->auditoriaService->registrar(
                accion: 'orden_pedido.creada',
                entidad: 'compra',
                entidadId: $compra->id,
                descripcion: "Orden de pedido {$numeroFactura} solicitada a {$proveedor->nombre} por {$cantidad} {$insumo->unidad_medida} de {$insumo->nombre}.",
                datos: ['insumo_id' => $insumo->id, 'cantidad' => $cantidad, 'subtotal' => $subtotal]
            );

            return $compra;
        });
    }

    /**
     * Ingresa de forma inmediata el insumo físico al stock real y Kardex.
     */
    public function ingresarStockDirecto(
        int $insumoId,
        float $cantidad,
        float $costoUnitario,
        string $fuentePago = 'externo',
        ?int $proveedorId = null,
        ?string $numeroDocumento = null,
        ?User $usuario = null
    ): array {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad ingresada debe ser mayor a cero.');
        }

        if ($costoUnitario < 0) {
            throw new InvalidArgumentException('El costo unitario no puede ser negativo.');
        }

        return DB::transaction(function () use (
            $insumoId,
            $cantidad,
            $costoUnitario,
            $fuentePago,
            $proveedorId,
            $numeroDocumento,
            $usuario
        ) {
            $insumo = Insumo::lockForUpdate()->findOrFail($insumoId);
            $userId = $usuario?->id ?? auth()->id() ?? 1;
            $proveedor = $proveedorId ? Proveedor::find($proveedorId) : $insumo->proveedor;
            $nombreProveedor = $proveedor?->nombre ?? 'Compra Directa Independiente';

            $doc = $numeroDocumento && trim($numeroDocumento) !== ''
                ? trim($numeroDocumento)
                : 'REP-'.date('YmdHis');

            // 1. Si la fuente es crédito y hay proveedor formal, registramos vía CompraService
            if ($fuentePago === 'credito' && $proveedor) {
                $compra = $this->compraService->registrarFactura(
                    [
                        'proveedor_id' => $proveedor->id,
                        'numero_factura' => $doc,
                        'fecha' => today()->toDateString(),
                        'forma_pago' => 'credito',
                    ],
                    [
                        [
                            'insumo_id' => $insumo->id,
                            'cantidad' => $cantidad,
                            'costo_unitario' => $costoUnitario,
                        ],
                    ],
                    $usuario
                );

                $movimiento = $insumo->movimientos()->first();
            } else {
                // 2. Registro directo de compra / recepción física en inventario
                $movimiento = $this->inventarioService->registrarCompra(
                    insumoId: $insumo->id,
                    cantidad: $cantidad,
                    costoUnitario: $costoUnitario,
                    proveedor: $nombreProveedor,
                    factura: $doc,
                    userId: $userId
                );

                // Si se asoció proveedor y el insumo no lo tenía, vincularlo
                if ($proveedor && ! $insumo->proveedor_id) {
                    $insumo->update([
                        'proveedor_id' => $proveedor->id,
                        'proveedor_nombre' => $proveedor->nombre,
                        'proveedor_telefono' => $proveedor->telefono,
                        'proveedor_nit' => $proveedor->nit,
                    ]);
                }
            }

            // 3. Manejo de afectación a Caja Menor
            $montoTotal = round($cantidad * $costoUnitario, 2);
            $egresoCajaRegistrado = false;

            if ($fuentePago === 'caja_menor' && $montoTotal > 0) {
                $turnoAbierto = TurnoCaja::whereNull('cierre_en')
                    ->latest('apertura_en')
                    ->first();

                if ($turnoAbierto) {
                    MovimientoCaja::create([
                        'turno_caja_id' => $turnoAbierto->id,
                        'user_id' => $userId,
                        'tipo' => 'egreso',
                        'concepto' => "Compra urgente reposición: {$cantidad} {$insumo->unidad_medida} de {$insumo->nombre}",
                        'monto' => $montoTotal,
                        'metodo_pago' => 'efectivo',
                        'numero_comprobante' => $doc,
                    ]);

                    $turnoAbierto->increment('total_egresos', $montoTotal);
                    $egresoCajaRegistrado = true;
                }
            }

            $insumo->refresh();

            $this->auditoriaService->registrar(
                accion: 'inventario.reposicion_rapida_ingresada',
                entidad: 'insumo',
                entidadId: $insumo->id,
                descripcion: "Ingreso rápido de {$cantidad} {$insumo->unidad_medida} de {$insumo->nombre}. Fuente: {$fuentePago}. Stock resultante: {$insumo->stock_actual}.",
                datos: [
                    'insumo_id' => $insumo->id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'monto_total' => $montoTotal,
                    'fuente_pago' => $fuentePago,
                    'egreso_caja' => $egresoCajaRegistrado,
                ]
            );

            return [
                'insumo' => $insumo,
                'movimiento' => $movimiento,
                'cantidad_ingresada' => $cantidad,
                'stock_actual' => (float) $insumo->stock_actual,
                'egreso_caja_registrado' => $egresoCajaRegistrado,
                'mensaje' => "Se ingresaron exitosamente {$cantidad} {$insumo->unidad_medida} de {$insumo->nombre} al inventario.",
            ];
        });
    }
}
