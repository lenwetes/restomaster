<?php

namespace App\Services;

use App\Jobs\ImprimirComandaJob;
use App\Jobs\ImprimirReporteZJob;
use App\Jobs\ImprimirTicketVentaJob;
use App\Models\Impresora;
use App\Models\Pedido;
use App\Models\TrabajoImpresion;
use App\Models\TurnoCaja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImpresionService
{
    public const ANCHO_80MM = 48; // Columnas estándar para 80mm térmico

    /**
     * Despacha comandas de cocina segmentadas por estación gastronómica.
     *
     * @return TrabajoImpresion[]
     */
    public function despacharComandaCocina(Pedido $pedido, ?User $usuario = null): array
    {
        $pedido->loadMissing(['items.producto', 'mesa']);
        $itemsPorArea = $pedido->items->groupBy(function ($item) {
            return $item->area_cocina ?: 'sushi';
        });

        $trabajosCreados = [];

        foreach ($itemsPorArea as $area => $items) {
            // Buscar impresoras asignadas a esta área o general
            $impresoras = Impresora::activas()->porArea($area)->get();

            if ($impresoras->isEmpty()) {
                // Fallback: buscar cualquier impresora activa de caja o crear simulador
                $impresoraFallback = Impresora::activas()->first() ?? Impresora::create([
                    'nombre' => 'Térmica Virtual (Simulador)',
                    'tipo_conexion' => 'virtual_simulador',
                    'area' => 'todas',
                    'ancho_columnas' => self::ANCHO_80MM,
                    'copias' => 1,
                    'activa' => true,
                ]);
                $impresoras = collect([$impresoraFallback]);
            }

            $textoComanda = $this->formatearComandaTexto($pedido, $area, $items);
            $rawComanda = $this->convertirEscPos($textoComanda, true);

            foreach ($impresoras as $impresora) {
                $trabajo = TrabajoImpresion::create([
                    'tipo' => 'comanda_cocina',
                    'pedido_id' => $pedido->id,
                    'impresora_id' => $impresora->id,
                    'area' => $area,
                    'contenido_texto' => $textoComanda,
                    'contenido_raw' => $rawComanda,
                    'estado' => 'pendiente',
                    'usuario_id' => $usuario?->id ?? auth()->id() ?? $pedido->usuario_id,
                ]);

                // Despachar a la cola asíncrona
                ImprimirComandaJob::dispatch($trabajo->id);
                $trabajosCreados[] = $trabajo;
            }
        }

        return $trabajosCreados;
    }

    /**
     * Despacha el ticket térmico fiscal de venta al cobrar.
     */
    public function despacharTicketVenta(Pedido $pedido, ?User $usuario = null): TrabajoImpresion
    {
        $pedido->loadMissing(['items', 'cliente', 'usuario', 'mesa']);

        $impresora = Impresora::activas()->whereIn('area', ['caja_principal', 'todas'])->first()
            ?? Impresora::activas()->first()
            ?? Impresora::create([
                'nombre' => 'Térmica Caja Principal (Simulador)',
                'tipo_conexion' => 'virtual_simulador',
                'area' => 'caja_principal',
                'ancho_columnas' => self::ANCHO_80MM,
                'copias' => 1,
                'activa' => true,
            ]);

        $textoTicket = $this->formatearTicketVentaTexto($pedido);
        $rawTicket = $this->convertirEscPos($textoTicket, true);

        $trabajo = TrabajoImpresion::create([
            'tipo' => 'ticket_venta',
            'pedido_id' => $pedido->id,
            'impresora_id' => $impresora->id,
            'area' => 'caja',
            'contenido_texto' => $textoTicket,
            'contenido_raw' => $rawTicket,
            'estado' => 'pendiente',
            'usuario_id' => $usuario?->id ?? auth()->id() ?? $pedido->usuario_id,
        ]);

        ImprimirTicketVentaJob::dispatch($trabajo->id);

        return $trabajo;
    }

    /**
     * Despacha el Reporte Z de arqueo y cierre de caja.
     */
    public function despacharReporteZ(TurnoCaja $turno, ?User $usuario = null): TrabajoImpresion
    {
        $turno->loadMissing(['caja.sucursal', 'usuario', 'movimientos']);

        $impresora = Impresora::activas()->whereIn('area', ['caja_principal', 'todas'])->first()
            ?? Impresora::activas()->first()
            ?? Impresora::create([
                'nombre' => 'Térmica Caja (Simulador)',
                'tipo_conexion' => 'virtual_simulador',
                'area' => 'caja_principal',
                'ancho_columnas' => self::ANCHO_80MM,
                'copias' => 1,
                'activa' => true,
            ]);

        $textoReporte = $this->formatearReporteZTexto($turno);
        $rawReporte = $this->convertirEscPos($textoReporte, true);

        $trabajo = TrabajoImpresion::create([
            'tipo' => 'reporte_z',
            'turno_caja_id' => $turno->id,
            'impresora_id' => $impresora->id,
            'area' => 'caja',
            'contenido_texto' => $textoReporte,
            'contenido_raw' => $rawReporte,
            'estado' => 'pendiente',
            'usuario_id' => $usuario?->id ?? auth()->id() ?? $turno->usuario_id,
        ]);

        ImprimirReporteZJob::dispatch($trabajo->id);

        return $trabajo;
    }

    /**
     * Procesa un trabajo de impresión (ejecutado dentro del Job de la cola).
     */
    public function procesarTrabajo(TrabajoImpresion $trabajo): bool
    {
        $impresora = $trabajo->impresora;
        $trabajo->increment('intentos');

        // 1. Si es virtual o simulador
        if ($impresora->tipo_conexion === 'virtual_simulador') {
            $trabajo->update([
                'estado' => $trabajo->veces_reimpreso > 0 ? 'reimpreso' : 'enviado',
                'impreso_en' => now(),
                'error_mensaje' => null,
            ]);

            return true;
        }

        // 2. Si es controlador local del sistema operativo (USB / Windows Spooler / CUPS)
        if (in_array($impresora->tipo_conexion, ['usb_local', 'driver_sistema', 'usb_compartida'], true)) {
            return $this->imprimirEnDriverLocal($trabajo, $impresora);
        }

        // 3. Si es controlador de impresión del navegador web
        if ($impresora->tipo_conexion === 'driver_navegador') {
            $trabajo->update([
                'estado' => $trabajo->veces_reimpreso > 0 ? 'reimpreso' : 'enviado',
                'impreso_en' => now(),
                'error_mensaje' => null,
            ]);

            return true;
        }

        if ($impresora->tipo_conexion === 'red_ip' && ! empty($impresora->ip_address)) {
            $errno = 0;
            $errstr = '';
            $socket = false;
            try {
                set_error_handler(static fn () => true);
                $socket = fsockopen($impresora->ip_address, $impresora->puerto, $errno, $errstr, 2.0);
            } finally {
                restore_error_handler();
            }

            if ($socket) {
                try {
                    fwrite($socket, $trabajo->contenido_raw ?: $trabajo->contenido_texto);
                    fflush($socket);
                    fclose($socket);

                    $trabajo->update([
                        'estado' => $trabajo->veces_reimpreso > 0 ? 'reimpreso' : 'enviado',
                        'impreso_en' => now(),
                        'error_mensaje' => null,
                    ]);

                    return true;
                } catch (Throwable $e) {
                    fclose($socket);
                    $trabajo->update([
                        'estado' => 'error',
                        'error_mensaje' => "Error durante el envío de datos: {$e->getMessage()}",
                    ]);

                    return false;
                }
            } else {
                // Impresora inalcanzable
                $mensajeError = "No se pudo conectar a {$impresora->ip_address}:{$impresora->puerto} ({$errstr} - Código {$errno})";
                Log::warning("[Spooler] {$mensajeError} para Trabajo #{$trabajo->id}");

                $trabajo->update([
                    'estado' => 'error',
                    'error_mensaje' => $mensajeError,
                ]);

                return false;
            }
        }

        // Tipo USB u otro fallback genérico
        $trabajo->update([
            'estado' => $trabajo->veces_reimpreso > 0 ? 'reimpreso' : 'enviado',
            'impreso_en' => now(),
            'error_mensaje' => null,
        ]);

        return true;
    }

    /**
     * Envía el trabajo directamente al controlador local de la impresora en el sistema operativo (Windows Spooler o CUPS).
     */
    public function imprimirEnDriverLocal(TrabajoImpresion $trabajo, Impresora $impresora): bool
    {
        $driver = trim($impresora->driver_nombre ?: $impresora->ip_address ?: '');
        if (empty($driver)) {
            $trabajo->update([
                'estado' => 'error',
                'error_mensaje' => 'Nombre de controlador local no especificado en la configuración de la impresora.',
            ]);

            return false;
        }

        $contenido = $trabajo->contenido_texto ?: $trabajo->contenido_raw;
        if (empty($contenido)) {
            $trabajo->update([
                'estado' => 'error',
                'error_mensaje' => 'El trabajo de impresión no contiene texto para imprimir.',
            ]);

            return false;
        }

        $tempPath = storage_path('app/temp_ticket_'.$trabajo->id.'_'.uniqid().'.txt');

        try {
            file_put_contents($tempPath, $contenido);

            if (PHP_OS_FAMILY === 'Windows') {
                $safeDriver = str_replace("'", "''", $driver);
                $safePath = str_replace("'", "''", $tempPath);
                $cmd = "powershell.exe -NoProfile -NonInteractive -Command \"Get-Content -LiteralPath '{$safePath}' -Raw -Encoding UTF8 | Out-Printer -Name '{$safeDriver}'\"";

                $output = [];
                $exitCode = 0;
                exec($cmd, $output, $exitCode);

                if ($exitCode === 0) {
                    $trabajo->update([
                        'estado' => $trabajo->veces_reimpreso > 0 ? 'reimpreso' : 'enviado',
                        'impreso_en' => now(),
                        'error_mensaje' => null,
                    ]);

                    return true;
                }

                $err = implode(' ', $output) ?: "Fallo al enviar a Windows Spooler '{$driver}' (código {$exitCode})";
                $trabajo->update([
                    'estado' => 'error',
                    'error_mensaje' => $err,
                ]);

                return false;
            } else {
                // Linux / CUPS
                $output = [];
                $exitCode = 0;
                exec('lp -d '.escapeshellarg($driver).' '.escapeshellarg($tempPath).' 2>&1', $output, $exitCode);

                if ($exitCode === 0) {
                    $trabajo->update([
                        'estado' => $trabajo->veces_reimpreso > 0 ? 'reimpreso' : 'enviado',
                        'impreso_en' => now(),
                        'error_mensaje' => null,
                    ]);

                    return true;
                }

                $err = implode(' ', $output) ?: "Fallo al enviar a cola CUPS '{$driver}'";
                $trabajo->update([
                    'estado' => 'error',
                    'error_mensaje' => $err,
                ]);

                return false;
            }
        } catch (Throwable $e) {
            $trabajo->update([
                'estado' => 'error',
                'error_mensaje' => "Excepción al procesar impresión local: {$e->getMessage()}",
            ]);

            return false;
        } finally {
            if (file_exists($tempPath)) {
                try {
                    unlink($tempPath);
                } catch (Throwable) {
                    // Archivo temporal ya liberado o eliminado
                }
            }
        }
    }

    /**
     * Obtiene la lista de impresoras instaladas localmente en el sistema operativo.
     *
     * @return string[]
     */
    public function obtenerImpresorasInstaladasSO(): array
    {
        $impresoras = [];

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $comando = 'powershell.exe -NoProfile -NonInteractive -Command "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; Get-Printer | Select-Object -ExpandProperty Name"';
                $output = [];
                $exitCode = 0;
                exec($comando, $output, $exitCode);

                if ($exitCode === 0 && ! empty($output)) {
                    foreach ($output as $linea) {
                        $nombre = trim($linea);
                        if (! empty($nombre)) {
                            $impresoras[] = $nombre;
                        }
                    }
                }
            } else {
                $output = [];
                exec("lpstat -a 2>/dev/null | awk '{print $1}'", $output);
                foreach ($output as $linea) {
                    $nombre = trim($linea);
                    if (! empty($nombre)) {
                        $impresoras[] = $nombre;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('[ImpresionService] Error listando impresoras del SO: '.$e->getMessage());
        }

        if (empty($impresoras)) {
            $impresoras = ['POS-80', 'EPSON TM-T20', 'Generic / Text Only'];
        }

        return array_values(array_unique($impresoras));
    }

    /**
     * Encola un trabajo de impresión genérico (usado por el panel de configuración o pruebas).
     */
    public function encolarTrabajo(Impresora $impresora, string $tipo, string $codigo, string $contenido, int $copias = 1): TrabajoImpresion
    {
        $trabajo = TrabajoImpresion::create([
            'tipo' => $tipo,
            'impresora_id' => $impresora->id,
            'area' => $impresora->area,
            'contenido_texto' => $contenido,
            'contenido_raw' => $this->convertirEscPos($contenido, true),
            'estado' => 'pendiente',
            'usuario_id' => auth()->id(),
        ]);

        ImprimirTicketVentaJob::dispatch($trabajo->id);

        return $trabajo;
    }

    /**
     * Reimprime un trabajo previo con registro en la auditoría del restaurante.
     */
    public function reimprimir(TrabajoImpresion $trabajo, User $usuario, ?string $motivo = null): TrabajoImpresion
    {
        return DB::transaction(function () use ($trabajo, $usuario, $motivo) {
            $trabajo->increment('veces_reimpreso');
            $trabajo->update([
                'estado' => 'pendiente',
                'reimpreso_por_id' => $usuario->id,
            ]);

            // Registrar en la tabla de auditorías central
            app(AuditoriaService::class)->registrar(
                usuario: $usuario,
                accion: 'impresion.reimpreso',
                entidad: 'trabajo_impresion',
                entidadId: $trabajo->id,
                descripcion: "Reimpresión de {$trabajo->tipo} #{$trabajo->id} solicitada por ".($usuario?->name ?? 'Sistema').'. Motivo: '.($motivo ?: 'Reimpresión operativa'),
                datos: [
                    'trabajo_id' => $trabajo->id,
                    'tipo' => $trabajo->tipo,
                    'pedido_id' => $trabajo->pedido_id,
                    'impresora' => $trabajo->impresora->nombre,
                    'veces_reimpreso' => $trabajo->veces_reimpreso,
                ],
            );

            // Re-encolar el job correspondiente
            match ($trabajo->tipo) {
                'comanda_cocina' => ImprimirComandaJob::dispatch($trabajo->id),
                'reporte_z' => ImprimirReporteZJob::dispatch($trabajo->id),
                default => ImprimirTicketVentaJob::dispatch($trabajo->id),
            };

            return $trabajo->fresh();
        });
    }

    /**
     * Envía un ticket de diagnóstico y prueba a una impresora.
     */
    public function probarImpresora(Impresora $impresora, ?User $usuario = null): TrabajoImpresion
    {
        $fecha = Carbon::now()->format('d/m/Y H:i:s');
        $ancho = $impresora->ancho_columnas ?: self::ANCHO_80MM;

        $t = '';
        $t .= $this->centrar('RESTOMASTER ENTERPRISE', $ancho)."\n";
        $t .= $this->centrar('TEST DE CONEXIÓN ESC/POS', $ancho)."\n";
        $t .= $this->lineaSeparadora($ancho, '=')."\n";
        $t .= $this->alinearDosColumnas('Impresora:', $impresora->nombre, $ancho)."\n";
        $t .= $this->alinearDosColumnas('Área / Estación:', strtoupper($impresora->area), $ancho)."\n";
        $t .= $this->alinearDosColumnas('Conexión:', $impresora->tipo_conexion, $ancho)."\n";
        if ($impresora->driver_nombre) {
            $t .= $this->alinearDosColumnas('Controlador SO:', $impresora->driver_nombre, $ancho)."\n";
        }
        if ($impresora->ip_address) {
            $t .= $this->alinearDosColumnas('Dirección IP:', "{$impresora->ip_address}:{$impresora->puerto}", $ancho)."\n";
        }
        $t .= $this->alinearDosColumnas('Fecha y Hora:', $fecha, $ancho)."\n";
        $t .= $this->lineaSeparadora($ancho, '-')."\n";
        $t .= $this->centrar('¡IMPRESORA EN LÍNEA Y OPERATIVA!', $ancho)."\n";
        $t .= $this->centrar('Design System: Aura Gastro Expressive OS', $ancho)."\n";
        $t .= $this->lineaSeparadora($ancho, '=')."\n\n\n";

        $trabajo = TrabajoImpresion::create([
            'tipo' => 'prueba',
            'impresora_id' => $impresora->id,
            'area' => $impresora->area,
            'contenido_texto' => $t,
            'contenido_raw' => $this->convertirEscPos($t, true),
            'estado' => 'pendiente',
            'usuario_id' => $usuario?->id ?? auth()->id(),
        ]);

        ImprimirTicketVentaJob::dispatch($trabajo->id);

        return $trabajo;
    }

    // ==========================================
    // FORMATEADORES DE TEXTO (80mm Térmico)
    // ==========================================

    public function formatearComandaTexto(Pedido $pedido, string $area, $items = null): string
    {
        $items = $items ?? $pedido->items;
        $ancho = self::ANCHO_80MM;
        $fecha = Carbon::now()->format('d/m/Y H:i');

        $destino = $pedido->mesa_id ? 'MESA: '.($pedido->mesa->nombre ?? "#{$pedido->mesa->numero}") : "DELIVERY #{$pedido->codigo}";
        if ($pedido->tipo === 'mostrador') {
            $destino = 'MOSTRADOR / LLEVAR';
        }

        $salida = '';
        $salida .= $this->centrar('RESTOMASTER · KDS COCINA', $ancho)."\n";
        $salida .= $this->centrar('COMANDA ÁREA: '.strtoupper($area), $ancho)."\n";
        $salida .= $this->lineaSeparadora($ancho, '=')."\n";
        $salida .= $this->alinearDosColumnas("ORDEN: #{$pedido->codigo}", $destino, $ancho)."\n";
        $salida .= $this->alinearDosColumnas("FECHA: {$fecha}", 'MESERO: '.($pedido->usuario->name ?? 'POS'), $ancho)."\n";
        $salida .= $this->lineaSeparadora($ancho, '-')."\n";
        $salida .= sprintf("%-5s %-32s\n", 'CANT', 'PLATO / INSTRUCCIÓN');
        $salida .= $this->lineaSeparadora($ancho, '-')."\n";

        foreach ($items as $item) {
            $cantStr = " [{$item->cantidad}x]";
            $nombrePlato = mb_strtoupper($item->nombre_producto);
            $salida .= sprintf("%-7s %-40s\n", $cantStr, $nombrePlato);

            if (! empty($item->notas)) {
                $salida .= '   >> NOTA: '.mb_strtoupper($item->notas)."\n";
            }
        }

        $salida .= $this->lineaSeparadora($ancho, '=')."\n";
        $salida .= $this->centrar('*** ORDEN EN COLA DE PREPARACIÓN ***', $ancho)."\n\n\n";

        return $salida;
    }

    public function formatearTicketVentaTexto(Pedido $pedido): string
    {
        $ancho = self::ANCHO_80MM;
        $fecha = $pedido->pagado_en ? Carbon::parse($pedido->pagado_en)->format('d/m/Y H:i') : Carbon::now()->format('d/m/Y H:i');

        $salida = '';
        $salida .= $this->centrar('RESTOMASTER COLOMBIA S.A.S.', $ancho)."\n";
        $salida .= $this->centrar('NIT 901.458.789-2 · RÉGIMEN SIMPLE', $ancho)."\n";
        $salida .= $this->centrar('Calle 10 # 36-24, El Poblado, Medellín', $ancho)."\n";
        $salida .= $this->centrar('Tel: +57 (604) 448-9000', $ancho)."\n";
        $salida .= $this->centrar('Resolución DIAN No. 18764022 de 2026', $ancho)."\n";
        $salida .= $this->centrar('Rango POS: SX-0001 a SX-99999', $ancho)."\n";
        $salida .= $this->lineaSeparadora($ancho, '=')."\n";
        $salida .= $this->alinearDosColumnas('FACTURA ELECTRÓNICA POS:', "#{$pedido->codigo}", $ancho)."\n";
        $salida .= $this->alinearDosColumnas('FECHA:', $fecha, $ancho)."\n";
        $salida .= $this->alinearDosColumnas('CAJERO:', $pedido->usuario->name ?? 'Caja Central', $ancho)."\n";
        if ($pedido->mesero) {
            $salida .= $this->alinearDosColumnas('MESERO:', $pedido->mesero->name, $ancho)."\n";
        }

        if ($pedido->cliente) {
            $nombreCli = $pedido->cliente->nombre ?: ($pedido->nombre_cliente ?: 'Consumidor Final');
            $docTel = $pedido->cliente->documento ?: ($pedido->cliente->telefono ?: 'Consumidor Final');
            $salida .= $this->alinearDosColumnas('CLIENTE:', $nombreCli, $ancho)."\n";
            $salida .= $this->alinearDosColumnas('DOC / TEL:', $docTel, $ancho)."\n";
            $salida .= $this->alinearDosColumnas('PUNTOS CLUB:', "{$pedido->cliente->puntos_fidelidad} pts ({$pedido->cliente->tier})", $ancho)."\n";
        } elseif (! empty($pedido->nombre_cliente)) {
            $salida .= $this->alinearDosColumnas('CLIENTE:', $pedido->nombre_cliente, $ancho)."\n";
            $salida .= $this->alinearDosColumnas('DOC / TEL:', 'Consumidor Final', $ancho)."\n";
        }

        $salida .= $this->lineaSeparadora($ancho, '-')."\n";
        $salida .= sprintf("%-4s %-26s %8s %8s\n", 'CNT', 'PRODUCTO', 'VR.UNI', 'TOTAL');
        $salida .= $this->lineaSeparadora($ancho, '-')."\n";

        foreach ($pedido->items as $item) {
            $nombre = mb_substr($item->nombre_producto, 0, 25);
            $salida .= sprintf("%-4s %-26s %8s %8s\n",
                $item->cantidad,
                $nombre,
                number_format($item->precio_unitario, 0),
                number_format($item->subtotal, 0)
            );
        }

        $salida .= $this->lineaSeparadora($ancho, '-')."\n";
        $salida .= $this->alinearDosColumnas('SUBTOTAL:', '$ '.number_format($pedido->subtotal, 0), $ancho)."\n";

        if ((float) $pedido->descuento > 0) {
            $salida .= $this->alinearDosColumnas('DESCUENTO COMERCIAL:', '-$ '.number_format($pedido->descuento, 0), $ancho)."\n";
        }

        if ((float) $pedido->descuento_puntos > 0) {
            $salida .= $this->alinearDosColumnas("CANJE PUNTOS ({$pedido->puntos_canjeados} pts):", '-$ '.number_format($pedido->descuento_puntos, 0), $ancho)."\n";
        }

        if ((float) $pedido->costo_envio > 0) {
            $salida .= $this->alinearDosColumnas('TARIFA DOMICILIO:', '$ '.number_format($pedido->costo_envio, 0), $ancho)."\n";
        }

        if ((float) ($pedido->propina ?? 0) > 0) {
            $porcentajeStr = (float) $pedido->porcentaje_propina > 0 ? ' ('.round((float) $pedido->porcentaje_propina).'%)' : '';
            $salida .= $this->alinearDosColumnas("PROPINA VOLUNTARIA{$porcentajeStr}:", '$ '.number_format($pedido->propina, 0), $ancho)."\n";
        }

        $totalFinal = (float) $pedido->total + (float) ($pedido->propina ?? 0);
        $salida .= $this->lineaSeparadora($ancho, '=')."\n";
        $salida .= $this->alinearDosColumnas('TOTAL A PAGAR:', '$ '.number_format($totalFinal, 0).' COP', $ancho)."\n";
        $salida .= $this->lineaSeparadora($ancho, '=')."\n";

        $metodo = strtoupper($pedido->metodo_pago ?: 'EFECTIVO');
        $salida .= $this->alinearDosColumnas('MÉTODO DE PAGO:', $metodo, $ancho)."\n";
        if ((float) $pedido->monto_pagado > 0) {
            $salida .= $this->alinearDosColumnas('PAGÓ CON:', '$ '.number_format($pedido->monto_pagado, 0), $ancho)."\n";
            $salida .= $this->alinearDosColumnas('CAMBIO / VUELTAS:', '$ '.number_format($pedido->cambio, 0), $ancho)."\n";
        }

        if ($pedido->puntos_ganados > 0) {
            $salida .= $this->lineaSeparadora($ancho, '-')."\n";
            $salida .= $this->centrar("¡ACUMULASTE +{$pedido->puntos_ganados} PUNTOS GOURMET!", $ancho)."\n";
        }

        $salida .= $this->lineaSeparadora($ancho, '=')."\n";
        $salida .= $this->centrar('GRACIAS POR PREFERIR RESTOMASTER', $ancho)."\n";
        $salida .= $this->centrar('Propina voluntaria no incluida', $ancho)."\n";
        $salida .= $this->centrar('Conserve este recibo para reclamos', $ancho)."\n\n\n";

        return $salida;
    }

    public function formatearReporteZTexto(TurnoCaja $turno): string
    {
        $ancho = self::ANCHO_80MM;
        $apertura = $turno->apertura_en ? Carbon::parse($turno->apertura_en)->format('d/m/Y H:i') : 'N/A';
        $cierre = $turno->cierre_en ? Carbon::parse($turno->cierre_en)->format('d/m/Y H:i') : Carbon::now()->format('d/m/Y H:i');

        $salida = '';
        $salida .= $this->centrar('RESTOMASTER · CONTROL FISCAL', $ancho)."\n";
        $salida .= $this->centrar('REPORTE Z — CIERRE DIARIO DE CAJA', $ancho)."\n";
        $salida .= $this->lineaSeparadora($ancho, '=')."\n";
        $salida .= $this->alinearDosColumnas('TURNO ID:', "#{$turno->id}", $ancho)."\n";
        $salida .= $this->alinearDosColumnas('CAJA:', $turno->caja->nombre ?? 'Caja Central', $ancho)."\n";
        $salida .= $this->alinearDosColumnas('CAJERO:', $turno->usuario->name ?? 'N/A', $ancho)."\n";
        $salida .= $this->alinearDosColumnas('APERTURA:', $apertura, $ancho)."\n";
        $salida .= $this->alinearDosColumnas('CIERRE:', $cierre, $ancho)."\n";
        $salida .= $this->lineaSeparadora($ancho, '-')."\n";

        $montoInicial = (float) ($turno->monto_inicial ?? 0);
        $totalVentas = (float) ($turno->total_ventas_efectivo ?? 0)
            + (float) ($turno->total_ventas_tarjeta ?? 0)
            + (float) ($turno->total_ventas_transferencia ?? 0);
        $totalIngresos = (float) ($turno->total_ingresos ?? 0);
        $totalEgresos = (float) ($turno->total_egresos ?? 0);
        $totalRetiros = (float) ($turno->total_retiros ?? 0);

        $salida .= $this->alinearDosColumnas('FONDO INICIAL:', '$ '.number_format($montoInicial, 2), $ancho)."\n";
        $salida .= $this->alinearDosColumnas('TOTAL VENTAS (+):', '$ '.number_format($totalVentas, 2), $ancho)."\n";
        $salida .= $this->alinearDosColumnas('TOTAL INGRESOS (+):', '$ '.number_format($totalIngresos, 2), $ancho)."\n";
        $salida .= $this->alinearDosColumnas('TOTAL EGRESOS (-):', '$ '.number_format($totalEgresos, 2), $ancho)."\n";
        $salida .= $this->alinearDosColumnas('TOTAL RETIROS (-):', '$ '.number_format($totalRetiros, 2), $ancho)."\n";
        $salida .= $this->lineaSeparadora($ancho, '-')."\n";

        $saldoEsperado = $montoInicial + (float) ($turno->total_ventas_efectivo ?? 0) + $totalIngresos - $totalEgresos - $totalRetiros;
        $salida .= $this->alinearDosColumnas('SALDO CALCULADO EN SISTEMA:', '$ '.number_format($saldoEsperado, 2), $ancho)."\n";

        if ($turno->monto_real_efectivo !== null) {
            $salida .= $this->alinearDosColumnas('ARQUEO FÍSICO CONTADO:', '$ '.number_format((float) $turno->monto_real_efectivo, 2), $ancho)."\n";
            $salida .= $this->alinearDosColumnas('DIFERENCIA:', '$ '.number_format((float) $turno->diferencia, 2), $ancho)."\n";
        }

        $salida .= $this->lineaSeparadora($ancho, '=')."\n";
        $salida .= $this->centrar('*** FIN DEL REPORTE FISCAL ***', $ancho)."\n\n\n";

        return $salida;
    }

    // ==========================================
    // HELPERS ESC/POS Y TIPOGRAFÍA TÉRMICA
    // ==========================================

    public function centrar(?string $texto, int $ancho = self::ANCHO_80MM): string
    {
        $texto = (string) ($texto ?? '');
        $longitud = mb_strlen($texto);
        if ($longitud >= $ancho) {
            return $texto;
        }
        $espaciosIzq = (int) floor(($ancho - $longitud) / 2);

        return str_repeat(' ', $espaciosIzq).$texto;
    }

    public function alinearDosColumnas(string $izq, ?string $der, int $ancho = self::ANCHO_80MM): string
    {
        $der = (string) ($der ?? '');
        $lenIzq = mb_strlen($izq);
        $lenDer = mb_strlen($der);
        $espacioDisponible = max(1, $ancho - $lenIzq - $lenDer);

        return $izq.str_repeat(' ', $espacioDisponible).$der;
    }

    public function lineaSeparadora(int $ancho = self::ANCHO_80MM, string $char = '-'): string
    {
        return str_repeat($char, $ancho);
    }

    /**
     * Convierte texto plano a secuencia binaria ESC/POS con inicialización y corte.
     */
    public function convertirEscPos(string $texto, bool $cortarPapel = true): string
    {
        $escInit = "\x1B\x40"; // ESC @: Inicializar impresora
        $gsCut = $cortarPapel ? "\x1D\x56\x42\x00" : ''; // GS V B 0: Corte parcial de papel

        // Normalizar saltos de línea a CRLF para impresoras térmicas
        $textoNormalizado = str_replace(["\r\n", "\r", "\n"], "\r\n", $texto);

        return $escInit.$textoNormalizado."\r\n\r\n\r\n".$gsCut;
    }
}
