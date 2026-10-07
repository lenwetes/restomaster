<?php

use App\Models\Cliente;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Services\CajaService;
use App\Services\ClienteService;
use App\Services\DianPosElectronicoService;
use App\Services\ImpresionService;
use App\Services\PedidoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class () extends Component {
    public bool $mostrarModal = false;
    public ?int $pedidoId = null;
    public ?Pedido $pedido = null;

    // Totales
    public float $subtotal = 0.0;
    public float $descuento = 0.0;
    public float $descuentoPuntos = 0.0;
    public float $costoEnvio = 0.0;
    public float $consumoBase = 0.0;
    public float $montoPropina = 0.0;
    public string $tipoPropina = 'cero'; // 'cero', 'diez_porciento', 'personalizada'
    public float $totalConPropina = 0.0;

    // Pago
    public string $metodoPago = 'efectivo'; // 'efectivo', 'tarjeta', 'mixto', 'transferencia'
    public float $montoPagado = 0.0;
    public float $montoEfectivoMixto = 0.0;
    public float $cambio = 0.0;

    // Cliente
    public ?int $clienteId = null;
    public ?Cliente $cliente = null;
    public string $busquedaCliente = '';
    public array $resultadosClientes = [];
    public bool $mostrarFormNuevoCliente = false;
    public string $nuevoClienteNombre = '';
    public string $nuevoClienteDocumento = '';
    public string $nuevoClienteTelefono = '';
    public string $nuevoClienteEmail = '';

    // Facturación y Canales
    public string $tipoFactura = 'fisica'; // 'fisica', 'electronica'
    public bool $enviarWhatsapp = false;
    public bool $enviarEmail = false;
    public ?string $urlWhatsappGenerada = null;

    public ?string $errorCobro = null;
    public ?string $mensajeExito = null;

    #[On('abrir-modal-cobro-unificado')]
    public function abrir(int $pedidoId): void
    {
        $this->reset([
            'busquedaCliente',
            'resultadosClientes',
            'mostrarFormNuevoCliente',
            'nuevoClienteNombre',
            'nuevoClienteDocumento',
            'nuevoClienteTelefono',
            'nuevoClienteEmail',
            'errorCobro',
            'mensajeExito',
            'urlWhatsappGenerada',
        ]);

        $this->pedidoId = $pedidoId;
        $this->cargarDatosPedido();
        $this->mostrarModal = true;
    }

    public function cerrar(): void
    {
        $this->mostrarModal = false;
        $this->pedido = null;
        $this->pedidoId = null;
    }

    protected function cargarDatosPedido(): void
    {
        $this->pedido = Pedido::with(['items.producto', 'mesa', 'mesero', 'usuario', 'cliente'])
            ->findOrFail($this->pedidoId);

        $this->subtotal = (float) $this->pedido->subtotal;
        $this->descuento = (float) $this->pedido->descuento;
        $this->descuentoPuntos = (float) ($this->pedido->descuento_puntos ?? 0);
        $this->costoEnvio = (float) ($this->pedido->costo_envio ?? 0);
        $this->consumoBase = (float) $this->pedido->total;

        // Cliente
        $this->cliente = $this->pedido->cliente;
        $this->clienteId = $this->cliente?->id;
        if ($this->cliente?->telefono) {
            $this->enviarWhatsapp = true;
        }
        if ($this->cliente?->email) {
            $this->enviarEmail = true;
        }

        // Propina
        if ((float) $this->pedido->propina > 0) {
            $this->montoPropina = (float) $this->pedido->propina;
            $this->tipoPropina = 'personalizada';
        } else {
            $this->tipoPropina = 'cero';
            $this->montoPropina = 0.0;
        }

        $this->metodoPago = $this->pedido->metodo_pago ?: 'efectivo';
        $this->recalcularTotales();

        // Monto por defecto: exacto
        $this->montoPagado = $this->totalConPropina;
        $this->recalcularCambio();
    }

    public function seleccionarPropina(string $tipo): void
    {
        $this->tipoPropina = $tipo;

        if ($tipo === 'cero') {
            $this->montoPropina = 0.0;
        } elseif ($tipo === 'diez_porciento') {
            $this->montoPropina = round($this->consumoBase * 0.10);
        }

        $this->recalcularTotales();
        if ($this->montoPagado < $this->totalConPropina) {
            $this->montoPagado = $this->totalConPropina;
        }
        $this->recalcularCambio();
    }

    public function updatedMontoPropina(): void
    {
        $this->montoPropina = max(0.0, (float) $this->montoPropina);
        $this->recalcularTotales();
        $this->recalcularCambio();
    }

    public function updatedMontoPagado(): void
    {
        $this->montoPagado = max(0.0, (float) $this->montoPagado);
        $this->recalcularCambio();
    }

    public function updatedMontoEfectivoMixto(): void
    {
        $this->montoEfectivoMixto = min($this->totalConPropina, max(0.0, (float) $this->montoEfectivoMixto));
        $this->recalcularCambio();
    }

    public function recalcularTotales(): void
    {
        $this->totalConPropina = $this->consumoBase + $this->montoPropina;
    }

    public function recalcularCambio(): void
    {
        if ($this->metodoPago === 'efectivo') {
            $this->cambio = max(0.0, (float) $this->montoPagado - (float) $this->totalConPropina);
        } elseif ($this->metodoPago === 'mixto') {
            $this->cambio = max(0.0, (float) $this->montoPagado - (float) $this->montoEfectivoMixto);
        } else {
            $this->cambio = 0.0;
        }
    }

    public function setMontoExacto(): void
    {
        $this->montoPagado = $this->totalConPropina;
        $this->recalcularCambio();
    }

    public function sumarBillete(float $denominacion): void
    {
        // Si el monto pagado actual era menor o igual al total o cero, sumar progresivamente
        $this->montoPagado = (float) $this->montoPagado + $denominacion;
        $this->recalcularCambio();
    }

    public function fijarBillete(float $denominacion): void
    {
        $this->montoPagado = $denominacion;
        $this->recalcularCambio();
    }

    public function updatedBusquedaCliente(string $query): void
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            $this->resultadosClientes = [];
            return;
        }

        $this->resultadosClientes = Cliente::query()
            ->where('nombre', 'ilike', "%{$query}%")
            ->orWhere('documento', 'ilike', "%{$query}%")
            ->orWhere('telefono', 'ilike', "%{$query}%")
            ->take(6)
            ->get(['id', 'nombre', 'documento', 'telefono', 'email', 'puntos_fidelidad', 'tier'])
            ->toArray();
    }

    public function asignarCliente(int $id): void
    {
        $this->cliente = Cliente::find($id);
        $this->clienteId = $this->cliente?->id;
        $this->busquedaCliente = '';
        $this->resultadosClientes = [];

        if ($this->pedido && $this->clienteId) {
            $this->pedido->update([
                'cliente_id' => $this->clienteId,
                'nombre_cliente' => $this->cliente->nombre,
            ]);
        }

        if ($this->cliente?->telefono) {
            $this->enviarWhatsapp = true;
        }
        if ($this->cliente?->email) {
            $this->enviarEmail = true;
        }
    }

    public function desasignarCliente(): void
    {
        $this->cliente = null;
        $this->clienteId = null;
        if ($this->pedido) {
            $this->pedido->update([
                'cliente_id' => null,
                'nombre_cliente' => 'Consumidor Final',
            ]);
        }
    }

    public function guardarNuevoCliente(): void
    {
        $this->validate([
            'nuevoClienteNombre' => 'required|string|min:3|max:120',
            'nuevoClienteDocumento' => 'nullable|string|max:30',
            'nuevoClienteTelefono' => 'nullable|string|max:20',
            'nuevoClienteEmail' => 'nullable|email|max:100',
        ]);

        $sucursalId = Auth::user()?->sucursal_id ?? $this->pedido?->sucursal_id ?? 1;

        $cliente = Cliente::create([
            'sucursal_id' => $sucursalId,
            'nombre' => trim($this->nuevoClienteNombre),
            'documento' => trim($this->nuevoClienteDocumento) ?: null,
            'telefono' => trim($this->nuevoClienteTelefono) ?: null,
            'email' => trim($this->nuevoClienteEmail) ?: null,
            'puntos_fidelidad' => 0,
            'tier' => 'bronce',
            'activo' => true,
        ]);

        $this->asignarCliente($cliente->id);
        $this->mostrarFormNuevoCliente = false;
        $this->reset(['nuevoClienteNombre', 'nuevoClienteDocumento', 'nuevoClienteTelefono', 'nuevoClienteEmail']);
    }

    public function confirmarCobro(): void
    {
        $user = Auth::user();

        // Verificación estricta de rol
        if (! $user || (! $user->isCajero() && ! $user->isAdmin() && ! $user->isGerente())) {
            $this->errorCobro = 'Operación no autorizada: Solo el personal de caja o gerencia puede ejecutar el cobro de tickets.';
            return;
        }

        if (! $this->pedido) {
            $this->errorCobro = 'No hay un pedido activo cargado.';
            return;
        }

        // Validar monto pagado
        $totalPagar = (float) $this->totalConPropina;
        $montoEfectivoFinal = null;

        if ($this->metodoPago === 'efectivo') {
            if ((float) $this->montoPagado < $totalPagar) {
                $this->errorCobro = "El monto entregado ($" . number_format($this->montoPagado, 0, ',', '.') . ") es menor al total a pagar ($" . number_format($totalPagar, 0, ',', '.') . ").";
                return;
            }
            $montoEfectivoFinal = (float) $this->montoPagado;
        } elseif ($this->metodoPago === 'mixto') {
            if ((float) $this->montoEfectivoMixto > $totalPagar) {
                $this->errorCobro = "El monto en efectivo no puede superar el total del pedido.";
                return;
            }
            $montoEfectivoFinal = (float) $this->montoEfectivoMixto;
            $this->montoPagado = $totalPagar;
        } else {
            $this->montoPagado = $totalPagar;
        }

        $porcentajePropina = $this->tipoPropina === 'diez_porciento' ? 10.0 : null;

        try {
            $pedidoService = app(PedidoService::class);
            $pedidoActualizado = $pedidoService->cobrarPedido(
                pedido: $this->pedido,
                metodoPago: $this->metodoPago,
                montoPagado: (float) $this->montoPagado,
                montoPagoEfectivo: $montoEfectivoFinal,
                propina: (float) $this->montoPropina,
                porcentajePropina: $porcentajePropina
            );

            // 1. Emisión de Comprobante / Factura
            if ($this->tipoFactura === 'fisica') {
                try {
                    app(ImpresionService::class)->despacharTicketVenta($pedidoActualizado, $user);
                } catch (\Throwable $e) {
                    // Log o fallo no bloqueante de impresión física
                }
            } elseif ($this->tipoFactura === 'electronica') {
                try {
                    app(DianPosElectronicoService::class)->emitirDianPos($pedidoActualizado);
                } catch (\Throwable $e) {
                    // DIAN simulada / offline fallback
                }
            }

            // 2. Canales de Envío Digital
            if ($this->enviarWhatsapp && $this->cliente?->telefono) {
                $telLimpio = preg_replace('/\D/', '', $this->cliente->telefono);
                if (strlen($telLimpio) === 10) {
                    $telLimpio = '57' . $telLimpio;
                }
                $textoMensaje = "¡Hola {$this->cliente->nombre}! 🧾 Aquí tienes el comprobante de tu consumo en RestoMaster Gastro OS:\n"
                    . "Orden #{$pedidoActualizado->codigo}\n"
                    . "Total Pagado: $" . number_format($totalPagar, 0, ',', '.') . " COP\n"
                    . "¡Gracias por tu visita y preferencia!";
                $this->urlWhatsappGenerada = "https://wa.me/{$telLimpio}?text=" . rawurlencode($textoMensaje);
            }

            $this->mensajeExito = "¡Cobro procesado exitosamente! Ticket #{$pedidoActualizado->codigo} cerrado.";

            // Disparar eventos globales para actualizar POS y Caja
            $this->dispatch('pedido-cobrado-exitosamente', [
                'pedido_id' => $pedidoActualizado->id,
                'mesa_id' => $pedidoActualizado->mesa_id,
                'cambio' => $this->cambio,
            ]);

            $this->dispatch('notificacion', [
                'mensaje' => "Cobro exitoso: Ticket #{$pedidoActualizado->codigo} ($" . number_format($totalPagar, 0, ',', '.') . " COP).",
                'tipo' => 'success',
            ]);

            // Si hay enlace de WhatsApp, abrir o esperar que el usuario cierre
            if (! $this->urlWhatsappGenerada) {
                $this->mostrarModal = false;
            }

        } catch (\Throwable $e) {
            $this->errorCobro = "Error al procesar el cobro: " . $e->getMessage();
        }
    }

    public function with(): array
    {
        if ($this->pedidoId && $this->mostrarModal) {
            $this->pedido = Pedido::with(['items.producto', 'mesa', 'mesero', 'usuario', 'cliente'])
                ->find($this->pedidoId);
        }

        return [
            'pedido' => $this->pedido,
        ];
    }
}; ?>

<div>
    @if($mostrarModal && $pedido)
        <div 
            x-data="{
                copiado: false,
                abrirWhatsapp(url) {
                    if (url) window.open(url, '_blank');
                }
            }"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 backdrop-blur-md p-2 sm:p-4 animate-fade-in overflow-y-auto"
            @keydown.escape.window="$wire.cerrar()"
        >
            <div 
                role="dialog" 
                aria-modal="true" 
                class="w-full max-w-5xl rounded-3xl bg-[#140e0b] border border-[#3d2b22] shadow-[0_25px_60px_-15px_rgba(0,0,0,0.9)] overflow-hidden flex flex-col max-h-[94vh]"
            >
                <!-- CABECERA DE LA CONSOLA MAESTRA DE COBRO -->
                <div class="px-5 py-3.5 bg-gradient-to-r from-[#1c130f] via-[#241712] to-[#1c130f] border-b border-[#3d2b22] flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-[#e0442e] to-[#992615] text-white flex items-center justify-center shadow-md shadow-[#e0442e]/30">
                            <span class="material-symbols-outlined text-[24px]">point_of_sale</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm sm:text-base font-black text-white tracking-wide">Terminal Maestro de Cobro</h2>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-[#2eb8b4]/20 text-[#2eb8b4] border border-[#2eb8b4]/30">
                                    {{ $pedido->mesa ? 'Mesa ' . $pedido->mesa->numero : strtoupper($pedido->tipo) }}
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-[#32231c] text-[#d6c4bc]">
                                    #{{ $pedido->codigo }}
                                </span>
                            </div>
                            <p class="text-[11px] text-[#a89086]">
                                Mesero a cargo: <span class="font-bold text-[#f5e8e2]">{{ $pedido->mesero?->name ?? $pedido->usuario?->name ?? 'Caja' }}</span>
                                · Fecha: {{ $pedido->created_at ? $pedido->created_at->format('d/m/Y h:i A') : now()->format('d/m/Y h:i A') }}
                            </p>
                        </div>
                    </div>
                    <button 
                        wire:click="cerrar" 
                        type="button"
                        class="w-9 h-9 rounded-xl flex items-center justify-center text-[#a89086] hover:text-white hover:bg-[#2c1d17] transition cursor-pointer"
                        title="Cerrar terminal de cobro"
                    >
                        <span class="material-symbols-outlined text-[22px]">close</span>
                    </button>
                </div>

                <!-- ALERTA DE ÉXITO O ERROR -->
                @if($mensajeExito)
                    <div class="m-4 p-4 rounded-2xl bg-[#10b981]/15 border border-[#10b981]/40 text-[#10b981] flex items-center justify-between animate-fade-in shadow-lg">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[28px]">check_circle</span>
                            <div>
                                <p class="text-sm font-black">{{ $mensajeExito }}</p>
                                <p class="text-xs text-[#a7f3d0]">Cambio entregado: ${{ number_format($cambio, 0, ',', '.') }} COP</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($urlWhatsappGenerada)
                                <a 
                                    href="{{ $urlWhatsappGenerada }}" 
                                    target="_blank" 
                                    class="px-3 py-1.5 rounded-xl bg-[#25D366] text-white font-black text-xs flex items-center gap-1.5 hover:brightness-110 shadow-md"
                                >
                                    <span class="material-symbols-outlined text-[16px]">chat</span>
                                    <span>Enviar WhatsApp</span>
                                </a>
                            @endif
                            <button 
                                wire:click="cerrar" 
                                class="px-4 py-1.5 rounded-xl bg-[#1c130f] border border-[#3d2b22] text-white font-bold text-xs hover:bg-[#2c1d17]"
                            >
                                Finalizar
                            </button>
                        </div>
                    </div>
                @endif

                @if($errorCobro)
                    <div class="mx-4 mt-3 p-3 rounded-2xl bg-[#ef4444]/15 border border-[#ef4444]/40 text-[#ef4444] text-xs font-bold flex items-center gap-2 animate-fade-in">
                        <span class="material-symbols-outlined text-[20px] shrink-0">error</span>
                        <span>{{ $errorCobro }}</span>
                    </div>
                @endif

                <!-- CUERPO PRINCIPAL BENTO TOUCH (2 COLUMNAS) -->
                <div class="p-4 sm:p-5 grid grid-cols-1 lg:grid-cols-12 gap-5 overflow-y-auto flex-1 min-h-0">
                    
                    <!-- ============================================================== -->
                    <!-- COLUMNA IZQUIERDA: VERIFICACIÓN DETALLADA DEL TICKET (5 COLS) -->
                    <!-- ============================================================== -->
                    <div class="lg:col-span-5 flex flex-col gap-3 min-h-0">
                        <div class="rounded-2xl bg-[#1b120e] border border-[#34241c] p-3.5 flex flex-col flex-1 min-h-[380px]">
                            <div class="flex items-center justify-between pb-2 border-b border-[#2d1e18]">
                                <span class="text-xs font-extrabold uppercase tracking-wider text-[#d6c4bc] flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-[#e0442e]">receipt</span>
                                    Verificación de Ticket
                                </span>
                                <span class="text-[11px] font-bold text-[#a89086]">
                                    {{ $pedido->items->count() }} ítems
                                </span>
                            </div>

                            <!-- Lista con scroll de productos del ticket -->
                            <div class="mt-2.5 space-y-2 flex-1 overflow-y-auto pr-1 max-h-[340px] resto-scrollbar">
                                @forelse($pedido->items as $item)
                                    <div class="p-2 rounded-xl bg-[#241712]/70 border border-[#38261e] flex items-start justify-between gap-2 text-xs">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="px-1.5 py-0.5 rounded-md bg-[#e0442e]/20 text-[#e0442e] font-black text-[11px]">
                                                    {{ $item->cantidad }}x
                                                </span>
                                                <span class="font-bold text-[#f5e8e2] truncate" title="{{ $item->nombre_producto }}">
                                                    {{ $item->nombre_producto }}
                                                </span>
                                            </div>
                                            @if($item->notas)
                                                <p class="text-[10px] text-[#f59e0b] italic mt-0.5 pl-6 truncate">
                                                    Nota: {{ $item->notas }}
                                                </p>
                                            @endif
                                            <span class="text-[10px] text-[#a89086] pl-6 block">
                                                ${{ number_format($item->precio_unitario, 0, ',', '.') }} c/u
                                            </span>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="font-mono font-black text-[#f5e8e2]">
                                                ${{ number_format($item->subtotal, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-8 text-center text-xs text-[#a89086]">
                                        No hay productos cargados en esta comanda.
                                    </div>
                                @endforelse
                            </div>

                            <!-- Desglose de subtotales -->
                            <div class="mt-3 pt-3 border-t border-[#2d1e18] space-y-1.5 text-xs text-[#d6c4bc]">
                                <div class="flex justify-between">
                                    <span class="text-[#a89086]">Subtotal Consumo:</span>
                                    <span class="font-mono font-bold">${{ number_format($subtotal, 0, ',', '.') }}</span>
                                </div>
                                @if($descuento > 0)
                                    <div class="flex justify-between text-[#10b981]">
                                        <span>Descuento Comercial:</span>
                                        <span class="font-mono font-bold">-${{ number_format($descuento, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if($descuentoPuntos > 0)
                                    <div class="flex justify-between text-[#38bdf8]">
                                        <span>Canje Puntos Club VIP:</span>
                                        <span class="font-mono font-bold">-${{ number_format($descuentoPuntos, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if($costoEnvio > 0)
                                    <div class="flex justify-between text-[#a89086]">
                                        <span>Tarifa Domicilio:</span>
                                        <span class="font-mono font-bold">+${{ number_format($costoEnvio, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Selector de Propina Voluntaria (Ley 1935 de 2018) -->
                            <div class="mt-3 p-2.5 rounded-xl bg-[#241712] border border-[#38261e]">
                                <div class="flex items-center justify-between text-[11px] font-bold text-[#d6c4bc] mb-1.5">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px] text-[#f59e0b]">volunteer_activism</span>
                                        Propina Voluntaria (Ley 1935):
                                    </span>
                                    <span class="font-mono text-[#f59e0b] font-black">
                                        +${{ number_format($montoPropina, 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-3 gap-1.5">
                                    <button 
                                        type="button"
                                        wire:click="seleccionarPropina('cero')" 
                                        class="py-1.5 text-center rounded-lg text-[10px] font-bold border transition cursor-pointer {{ $tipoPropina === 'cero' ? 'border-[#e0442e] bg-[#e0442e] text-white' : 'border-[#3d2b22] bg-[#1a120e] text-[#a89086] hover:text-white' }}"
                                    >
                                        Sin Propina
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="seleccionarPropina('diez_porciento')" 
                                        class="py-1.5 text-center rounded-lg text-[10px] font-bold border transition cursor-pointer {{ $tipoPropina === 'diez_porciento' ? 'border-[#e0442e] bg-[#e0442e] text-white' : 'border-[#3d2b22] bg-[#1a120e] text-[#a89086] hover:text-white' }}"
                                    >
                                        10% (${{ number_format(round($consumoBase * 0.10), 0, ',', '.') }})
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="seleccionarPropina('personalizada')" 
                                        class="py-1.5 text-center rounded-lg text-[10px] font-bold border transition cursor-pointer {{ $tipoPropina === 'personalizada' ? 'border-[#e0442e] bg-[#e0442e] text-white' : 'border-[#3d2b22] bg-[#1a120e] text-[#a89086] hover:text-white' }}"
                                    >
                                        Valor Libre
                                    </button>
                                </div>
                                @if($tipoPropina === 'personalizada')
                                    <div class="mt-2 flex items-center gap-1.5">
                                        <span class="text-xs text-[#a89086] font-bold">$</span>
                                        <input 
                                            type="number" 
                                            min="0"
                                            step="500"
                                            wire:model.live.debounce.300ms="montoPropina" 
                                            placeholder="Ingresa valor de propina..."
                                            class="w-full h-8 rounded-lg border border-[#3d2b22] bg-[#140e0b] px-2.5 text-xs font-bold font-mono text-[#f5e8e2] focus:border-[#e0442e] outline-none"
                                        />
                                    </div>
                                @endif
                            </div>

                            <!-- Gran Total a Pagar -->
                            <div class="mt-3 p-3 rounded-2xl bg-gradient-to-br from-[#2a1a14] to-[#1c120e] border border-[#e0442e]/40 text-center shadow-inner">
                                <span class="text-[10px] font-black uppercase tracking-wider text-[#d6c4bc]">Gran Total a Cobrar</span>
                                <div class="font-mono text-3xl font-black text-[#e0442e] mt-0.5 tracking-tight">
                                    ${{ number_format($totalConPropina, 0, ',', '.') }} <span class="text-xs text-[#a89086] font-normal">COP</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- =================================================================== -->
                    <!-- COLUMNA DERECHA: CLIENTE, BILLETES Y EJECUCIÓN DEL COBRO (7 COLS)   -->
                    <!-- =================================================================== -->
                    <div class="lg:col-span-7 flex flex-col gap-3 min-h-0">
                        
                        <!-- SECCIÓN 1: ASIGNACIÓN Y CREACIÓN RÁPIDA DE CLIENTE -->
                        <div class="rounded-2xl bg-[#1b120e] border border-[#34241c] p-3.5">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-extrabold uppercase tracking-wider text-[#d6c4bc] flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-[#2eb8b4]">person</span>
                                    Cliente / Comensal
                                </span>
                                @if(!$cliente)
                                    <button 
                                        type="button"
                                        wire:click="$toggle('mostrarFormNuevoCliente')" 
                                        class="text-[11px] font-bold text-[#2eb8b4] hover:underline flex items-center gap-1 cursor-pointer"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">person_add</span>
                                        <span>+ Cliente Rápido</span>
                                    </button>
                                @endif
                            </div>

                            <!-- Tarjeta si ya tiene cliente -->
                            @if($cliente)
                                <div class="p-2.5 rounded-xl bg-[#241712] border border-[#3d2b22] flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="w-8 h-8 rounded-lg bg-[#2eb8b4]/20 text-[#2eb8b4] flex items-center justify-center font-bold text-xs shrink-0">
                                            {{ strtoupper(substr($cliente->nombre, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-black text-[#f5e8e2] truncate">{{ $cliente->nombre }}</p>
                                            <p class="text-[10px] text-[#a89086]">
                                                Doc: <span class="text-[#d6c4bc]">{{ $cliente->documento ?: 'S/D' }}</span>
                                                · Tel: <span class="text-[#d6c4bc]">{{ $cliente->telefono ?: 'S/T' }}</span>
                                                @if($cliente->puntos_fidelidad)
                                                    · <span class="text-[#f59e0b] font-bold">⭐ {{ $cliente->puntos_fidelidad }} pts</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <button 
                                        type="button"
                                        wire:click="desasignarCliente" 
                                        class="text-[11px] text-[#ef4444] hover:underline font-bold shrink-0 cursor-pointer"
                                        title="Cambiar a Consumidor Final"
                                    >
                                        Cambiar
                                    </button>
                                </div>
                            @else
                                <!-- Buscador de clientes existentes -->
                                @if(!$mostrarFormNuevoCliente)
                                    <div class="relative">
                                        <div class="relative">
                                            <span class="material-symbols-outlined absolute left-2.5 top-2.5 text-[16px] text-[#a89086]">search</span>
                                            <input 
                                                type="text" 
                                                wire:model.live.debounce.300ms="busquedaCliente" 
                                                placeholder="Buscar por nombre, documento/NIT o celular..." 
                                                class="w-full h-9 pl-8 pr-3 rounded-xl bg-[#140e0b] border border-[#3d2b22] text-xs text-[#f5e8e2] placeholder-[#7d675e] focus:border-[#2eb8b4] outline-none"
                                            />
                                        </div>

                                        <!-- Dropdown de resultados -->
                                        @if(!empty($resultadosClientes))
                                            <div class="absolute z-20 left-0 right-0 mt-1 rounded-xl bg-[#1a120e] border border-[#3d2b22] shadow-2xl p-1 space-y-1 max-h-48 overflow-y-auto">
                                                @foreach($resultadosClientes as $cli)
                                                    <div 
                                                        wire:click="asignarCliente({{ $cli['id'] }})"
                                                        class="p-2 rounded-lg hover:bg-[#2c1d17] cursor-pointer flex items-center justify-between text-xs transition"
                                                    >
                                                        <div>
                                                            <p class="font-bold text-[#f5e8e2]">{{ $cli['nombre'] }}</p>
                                                            <p class="text-[10px] text-[#a89086]">
                                                                {{ $cli['documento'] ? 'Doc: ' . $cli['documento'] : '' }}
                                                                {{ $cli['telefono'] ? '· Tel: ' . $cli['telefono'] : '' }}
                                                            </p>
                                                        </div>
                                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-[#2eb8b4]/20 text-[#2eb8b4] font-bold">
                                                            Asignar
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <!-- Formulario rápido de nuevo cliente inline -->
                                    <div class="p-3 rounded-xl bg-[#241712] border border-[#2eb8b4]/30 space-y-2 animate-fade-in">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] font-black text-[#2eb8b4] uppercase">Nuevo Cliente Express</span>
                                            <button type="button" wire:click="$set('mostrarFormNuevoCliente', false)" class="text-[10px] text-[#a89086] hover:text-white">
                                                Cancelar
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <input 
                                                    type="text" 
                                                    wire:model="nuevoClienteNombre" 
                                                    placeholder="Nombre / Razón Social *" 
                                                    class="w-full h-8 px-2.5 rounded-lg bg-[#140e0b] border border-[#3d2b22] text-[#f5e8e2] placeholder-[#7d675e] text-xs focus:border-[#2eb8b4] outline-none"
                                                />
                                                @error('nuevoClienteNombre') <span class="text-[9px] text-red-400">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <input 
                                                    type="text" 
                                                    wire:model="nuevoClienteDocumento" 
                                                    placeholder="Cédula / NIT" 
                                                    class="w-full h-8 px-2.5 rounded-lg bg-[#140e0b] border border-[#3d2b22] text-[#f5e8e2] placeholder-[#7d675e] text-xs focus:border-[#2eb8b4] outline-none"
                                                />
                                            </div>
                                            <div>
                                                <input 
                                                    type="text" 
                                                    wire:model="nuevoClienteTelefono" 
                                                    placeholder="Celular (WhatsApp)" 
                                                    class="w-full h-8 px-2.5 rounded-lg bg-[#140e0b] border border-[#3d2b22] text-[#f5e8e2] placeholder-[#7d675e] text-xs focus:border-[#2eb8b4] outline-none"
                                                />
                                            </div>
                                            <div>
                                                <input 
                                                    type="email" 
                                                    wire:model="nuevoClienteEmail" 
                                                    placeholder="Correo Electrónico" 
                                                    class="w-full h-8 px-2.5 rounded-lg bg-[#140e0b] border border-[#3d2b22] text-[#f5e8e2] placeholder-[#7d675e] text-xs focus:border-[#2eb8b4] outline-none"
                                                />
                                            </div>
                                        </div>
                                        <button 
                                            type="button" 
                                            wire:click="guardarNuevoCliente" 
                                            class="w-full py-1.5 rounded-lg bg-[#2eb8b4] text-[#140e0b] font-black text-xs hover:brightness-110 cursor-pointer shadow"
                                        >
                                            Guardar y Asignar al Ticket
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <!-- SECCIÓN 2: MÉTODO DE PAGO Y BILLETES COLOMBIANOS -->
                        <div class="rounded-2xl bg-[#1b120e] border border-[#34241c] p-3.5 space-y-3">
                            <div>
                                <span class="text-xs font-extrabold uppercase tracking-wider text-[#d6c4bc] block mb-2">
                                    Método de Pago
                                </span>
                                <div class="grid grid-cols-4 gap-2">
                                    <button 
                                        type="button"
                                        wire:click="$set('metodoPago', 'efectivo')" 
                                        class="py-2 px-1 rounded-xl border text-xs font-black flex flex-col items-center justify-center gap-1 cursor-pointer transition {{ $metodoPago === 'efectivo' ? 'border-[#e0442e] bg-[#e0442e] text-white shadow-md' : 'border-[#3d2b22] bg-[#241712] text-[#a89086] hover:text-white' }}"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">payments</span>
                                        <span>Efectivo</span>
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="$set('metodoPago', 'tarjeta')" 
                                        class="py-2 px-1 rounded-xl border text-xs font-black flex flex-col items-center justify-center gap-1 cursor-pointer transition {{ $metodoPago === 'tarjeta' ? 'border-[#e0442e] bg-[#e0442e] text-white shadow-md' : 'border-[#3d2b22] bg-[#241712] text-[#a89086] hover:text-white' }}"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">credit_card</span>
                                        <span>Tarjeta</span>
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="$set('metodoPago', 'transferencia')" 
                                        class="py-2 px-1 rounded-xl border text-xs font-black flex flex-col items-center justify-center gap-1 cursor-pointer transition {{ $metodoPago === 'transferencia' ? 'border-[#e0442e] bg-[#e0442e] text-white shadow-md' : 'border-[#3d2b22] bg-[#241712] text-[#a89086] hover:text-white' }}"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">account_balance</span>
                                        <span>Transferencia</span>
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="$set('metodoPago', 'mixto')" 
                                        class="py-2 px-1 rounded-xl border text-xs font-black flex flex-col items-center justify-center gap-1 cursor-pointer transition {{ $metodoPago === 'mixto' ? 'border-[#e0442e] bg-[#e0442e] text-white shadow-md' : 'border-[#3d2b22] bg-[#241712] text-[#a89086] hover:text-white' }}"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">balance</span>
                                        <span>Mixto</span>
                                    </button>
                                </div>
                            </div>

                            @if($metodoPago === 'mixto')
                                <div class="p-2.5 rounded-xl bg-[#241712] border border-[#3d2b22] space-y-1">
                                    <label class="text-[11px] font-bold text-[#d6c4bc]">Monto en Efectivo:</label>
                                    <input 
                                        type="number" 
                                        min="0"
                                        step="1000"
                                        max="{{ (int) $totalConPropina }}"
                                        wire:model.live.debounce.300ms="montoEfectivoMixto" 
                                        class="w-full h-9 rounded-xl bg-[#140e0b] border border-[#3d2b22] px-3 font-mono font-bold text-sm text-[#f5e8e2] outline-none"
                                    />
                                    <p class="text-[10px] text-[#a89086]">
                                        El excedente (${{ number_format(max(0, $totalConPropina - $montoEfectivoMixto), 0, ',', '.') }}) se registra automáticamente como Tarjeta.
                                    </p>
                                </div>
                            @endif

                            <!-- BOTONES DE BILLETES COLOMBIANOS MINIATURA (EFECTIVO) -->
                            @if(in_array($metodoPago, ['efectivo', 'mixto'], true))
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-extrabold uppercase tracking-wider text-[#d6c4bc] flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px] text-[#10b981]">local_atm</span>
                                            Billetes Colombianos · Cobro Rápido
                                        </span>
                                        <button 
                                            type="button" 
                                            wire:click="setMontoExacto" 
                                            class="px-2.5 py-0.5 rounded-lg bg-[#2c1d17] hover:bg-[#38261e] border border-[#3d2b22] text-[11px] font-bold text-white transition cursor-pointer"
                                        >
                                            Valor Exacto
                                        </button>
                                    </div>

                                    <!-- Grid de Billetes en Miniatura con Look Oficial de la República de Colombia -->
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                        
                                        <!-- BILLETE $10.000 (Rosa / Amazonas / Virginia Gutiérrez) -->
                                        <button 
                                            type="button"
                                            wire:click="sumarBillete(10000)"
                                            class="relative group rounded-xl p-2.5 bg-gradient-to-r from-[#831843] via-[#9d174d] to-[#be185d] border border-[#f472b6]/40 text-white shadow-md hover:brightness-110 active:scale-95 transition cursor-pointer flex flex-col justify-between overflow-hidden h-16"
                                            title="Sumar billete de $10.000 COP"
                                        >
                                            <div class="flex items-center justify-between w-full">
                                                <span class="text-[9px] font-black uppercase tracking-widest text-[#fbcfe8]">COLOMBIA</span>
                                                <span class="text-[10px] font-black font-mono bg-black/30 px-1 rounded text-white">10 MIL</span>
                                            </div>
                                            <div class="text-left">
                                                <p class="font-mono text-base font-black leading-tight text-white drop-shadow">$10.000</p>
                                                <p class="text-[8px] text-[#fbcfe8]/80 font-bold uppercase truncate">Virginia Gutiérrez</p>
                                            </div>
                                        </button>

                                        <!-- BILLETE $20.000 (Naranja / Vueltiao / Alfonso López) -->
                                        <button 
                                            type="button"
                                            wire:click="sumarBillete(20000)"
                                            class="relative group rounded-xl p-2.5 bg-gradient-to-r from-[#9a3412] via-[#c2410c] to-[#ea580c] border border-[#fb923c]/40 text-white shadow-md hover:brightness-110 active:scale-95 transition cursor-pointer flex flex-col justify-between overflow-hidden h-16"
                                            title="Sumar billete de $20.000 COP"
                                        >
                                            <div class="flex items-center justify-between w-full">
                                                <span class="text-[9px] font-black uppercase tracking-widest text-[#fed7aa]">COLOMBIA</span>
                                                <span class="text-[10px] font-black font-mono bg-black/30 px-1 rounded text-white">20 MIL</span>
                                            </div>
                                            <div class="text-left">
                                                <p class="font-mono text-base font-black leading-tight text-white drop-shadow">$20.000</p>
                                                <p class="text-[8px] text-[#fed7aa]/80 font-bold uppercase truncate">Alfonso López M.</p>
                                            </div>
                                        </button>

                                        <!-- BILLETE $50.000 (Violeta / Macondo / Gabo) -->
                                        <button 
                                            type="button"
                                            wire:click="sumarBillete(50000)"
                                            class="relative group rounded-xl p-2.5 bg-gradient-to-r from-[#581c87] via-[#7e22ce] to-[#9333ea] border border-[#c084fc]/40 text-white shadow-md hover:brightness-110 active:scale-95 transition cursor-pointer flex flex-col justify-between overflow-hidden h-16"
                                            title="Sumar billete de $50.000 COP"
                                        >
                                            <div class="flex items-center justify-between w-full">
                                                <span class="text-[9px] font-black uppercase tracking-widest text-[#e9d5ff]">COLOMBIA</span>
                                                <span class="text-[10px] font-black font-mono bg-black/30 px-1 rounded text-white">50 MIL</span>
                                            </div>
                                            <div class="text-left">
                                                <p class="font-mono text-base font-black leading-tight text-white drop-shadow">$50.000</p>
                                                <p class="text-[8px] text-[#e9d5ff]/80 font-bold uppercase truncate">G. García Márquez</p>
                                            </div>
                                        </button>

                                        <!-- BILLETE $100.000 (Verde / Valle de Cocora / Carlos Lleras) -->
                                        <button 
                                            type="button"
                                            wire:click="sumarBillete(100000)"
                                            class="relative group rounded-xl p-2.5 bg-gradient-to-r from-[#064e3b] via-[#047857] to-[#059669] border border-[#34d399]/40 text-white shadow-md hover:brightness-110 active:scale-95 transition cursor-pointer flex flex-col justify-between overflow-hidden h-16"
                                            title="Sumar billete de $100.000 COP"
                                        >
                                            <div class="flex items-center justify-between w-full">
                                                <span class="text-[9px] font-black uppercase tracking-widest text-[#a7f3d0]">COLOMBIA</span>
                                                <span class="text-[10px] font-black font-mono bg-black/30 px-1 rounded text-white">100 MIL</span>
                                            </div>
                                            <div class="text-left">
                                                <p class="font-mono text-base font-black leading-tight text-white drop-shadow">$100.000</p>
                                                <p class="text-[8px] text-[#a7f3d0]/80 font-bold uppercase truncate">Carlos Lleras R.</p>
                                            </div>
                                        </button>

                                    </div>
                                </div>

                                <!-- Input directo de Monto Recibido y Cálculo de Vueltas -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                    <div>
                                        <label class="text-[11px] font-bold text-[#d6c4bc] block mb-1">Monto Entregado:</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-2.5 font-bold text-sm text-[#a89086]">$</span>
                                            <input 
                                                type="number" 
                                                step="500"
                                                min="0"
                                                wire:model.live.debounce.150ms="montoPagado" 
                                                class="w-full h-11 pl-7 pr-3 rounded-xl bg-[#140e0b] border border-[#3d2b22] font-mono text-xl font-black text-white focus:border-[#10b981] outline-none"
                                            />
                                        </div>
                                    </div>

                                    <!-- Vueltas / Cambio calculado en tiempo real -->
                                    <div>
                                        <label class="text-[11px] font-bold text-[#d6c4bc] block mb-1">Cambio a Devolver:</label>
                                        <div class="h-11 px-3 rounded-xl border flex items-center justify-between {{ $montoPagado >= $totalConPropina ? 'bg-[#10b981]/15 border-[#10b981]/50 text-[#10b981]' : 'bg-[#f59e0b]/15 border-[#f59e0b]/40 text-[#f59e0b]' }}">
                                            <span class="text-xs font-black">
                                                {{ $montoPagado >= $totalConPropina ? 'Vueltas:' : 'Faltan:' }}
                                            </span>
                                            <span class="font-mono text-xl font-black">
                                                ${{ number_format($montoPagado >= $totalConPropina ? $cambio : ($totalConPropina - $montoPagado), 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- SECCIÓN 3: MODALIDAD DE FACTURA Y CANALES DIGITALES -->
                            <div class="pt-2 border-t border-[#2d1e18] space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <button 
                                        type="button"
                                        wire:click="$set('tipoFactura', 'fisica')"
                                        class="p-2 rounded-xl border text-xs font-bold flex items-center gap-2 cursor-pointer transition {{ $tipoFactura === 'fisica' ? 'border-[#e0442e] bg-[#e0442e]/15 text-white' : 'border-[#3d2b22] bg-[#140e0b] text-[#a89086]' }}"
                                    >
                                        <span class="material-symbols-outlined text-[18px] text-[#e0442e]">print</span>
                                        <div class="text-left">
                                            <p class="font-black leading-tight">Factura Física</p>
                                            <p class="text-[9px] text-[#a89086]">Térmica 80mm</p>
                                        </div>
                                    </button>

                                    <button 
                                        type="button"
                                        wire:click="$set('tipoFactura', 'electronica')"
                                        class="p-2 rounded-xl border text-xs font-bold flex items-center gap-2 cursor-pointer transition {{ $tipoFactura === 'electronica' ? 'border-[#2eb8b4] bg-[#2eb8b4]/15 text-white' : 'border-[#3d2b22] bg-[#140e0b] text-[#a89086]' }}"
                                    >
                                        <span class="material-symbols-outlined text-[18px] text-[#2eb8b4]">qr_code_2</span>
                                        <div class="text-left">
                                            <p class="font-black leading-tight">Factura Electrónica</p>
                                            <p class="text-[9px] text-[#a89086]">DIAN POS (CUFE)</p>
                                        </div>
                                    </button>
                                </div>

                                <!-- Checkboxes de Canales Digitales -->
                                <div class="flex items-center gap-4 pt-1 px-1 text-xs text-[#d6c4bc]">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            wire:model="enviarWhatsapp" 
                                            class="w-4 h-4 rounded bg-[#140e0b] border-[#3d2b22] text-[#25D366] focus:ring-0 cursor-pointer"
                                        />
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px] text-[#25D366]">chat</span>
                                            Enviar WhatsApp
                                        </span>
                                    </label>

                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            wire:model="enviarEmail" 
                                            class="w-4 h-4 rounded bg-[#140e0b] border-[#3d2b22] text-[#38bdf8] focus:ring-0 cursor-pointer"
                                        />
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px] text-[#38bdf8]">mail</span>
                                            Enviar por Correo
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- BOTONES DE ACCIÓN FINAL -->
                        <div class="grid grid-cols-2 gap-3 mt-auto pt-2">
                            <button 
                                type="button"
                                wire:click="cerrar" 
                                class="h-12 rounded-2xl border border-[#3d2b22] bg-[#241712] text-xs font-black text-[#d6c4bc] hover:text-white hover:bg-[#2c1d17] transition cursor-pointer"
                            >
                                Cancelar
                            </button>

                            <button 
                                type="button"
                                wire:click="confirmarCobro" 
                                wire:loading.attr="disabled"
                                class="h-12 rounded-2xl bg-gradient-to-r from-[#10b981] to-[#059669] hover:from-[#059669] hover:to-[#047857] text-white text-xs font-black shadow-lg shadow-[#10b981]/25 flex items-center justify-center gap-2 cursor-pointer active:scale-98 transition disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="confirmarCobro" class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px]">verified</span>
                                    <span>Confirmar y Finalizar Cobro</span>
                                </span>
                                <span wire:loading wire:target="confirmarCobro" class="flex items-center gap-1.5">
                                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Emitiendo Factura...</span>
                                </span>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
