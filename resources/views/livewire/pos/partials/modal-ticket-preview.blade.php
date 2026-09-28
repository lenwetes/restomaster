<!-- Thermal Ticket 80mm Simulation Modal (Optimizado para Impresoras Locales USB / Driver Navegador) -->
@if($mostrarTicket && $pedidoCompletado)
    <div x-data @keydown.escape.window="$wire.cerrarTicket()" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto animate-fade-in">
        <div role="dialog" aria-modal="true" aria-labelledby="modal-ticket-title" class="print-ticket-termico w-full max-w-sm rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest font-mono text-xs max-h-[90vh] overflow-y-auto">
            <!-- Badge de Confirmación de Persistencia Real en BD -->
            <div class="no-print mb-3 p-2.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 flex items-center justify-between text-emerald-600">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">verified</span>
                    <span class="font-extrabold text-[11px]">Ticket Guardado en BD</span>
                </div>
                <span class="font-mono text-[10px] font-black">ID #{{ $pedidoCompletado->id }}</span>
            </div>

            <!-- Thermal Receipt Header (configurable ticket_80mm) -->
            <div class="text-center border-b border-dashed border-surface-container-high pb-4">
                <p id="modal-ticket-title" class="text-base font-black tracking-tight text-primary">{{ $ticketConfig['nombre_comercial'] ?? 'RESTOMASTER' }}</p>
                @if(!empty($ticketConfig['lema']))
                    <p class="text-[11px] text-on-surface-variant">{{ $ticketConfig['lema'] }}</p>
                @endif
                @if(!empty($ticketConfig['razon_social']))
                    <p class="text-[10px] text-on-surface-variant/70">{{ $ticketConfig['razon_social'] }}</p>
                @endif
                @if(!empty($ticketConfig['nit']) || !empty($ticketConfig['regimen']))
                    <p class="text-[10px] text-on-surface-variant/70">NIT: {{ $ticketConfig['nit'] ?? '' }}{{ !empty($ticketConfig['regimen']) ? ' · '.$ticketConfig['regimen'] : '' }}</p>
                @endif
                @if(!empty($ticketConfig['direccion']))
                    <p class="text-[10px] text-on-surface-variant/70">{{ $ticketConfig['direccion'] }}</p>
                @endif
                @if(!empty($ticketConfig['telefono']))
                    <p class="text-[10px] text-on-surface-variant/70">{{ $ticketConfig['telefono'] }}</p>
                @endif
                @if(!empty($ticketConfig['mensaje_bienvenida']))
                    <p class="text-[10px] italic text-on-surface-variant/70">"{{ $ticketConfig['mensaje_bienvenida'] }}"</p>
                @endif
                @if(!empty($ticketConfig['resolucion_dian']))
                    <p class="text-[9px] text-on-surface-variant/70">{{ $ticketConfig['resolucion_dian'] }}</p>
                @endif
                @if(!empty($ticketConfig['rango_autorizado']))
                    <p class="text-[9px] text-on-surface-variant/70">{{ $ticketConfig['rango_autorizado'] }}</p>
                @endif
            </div>

            <!-- Ticket Details -->
            <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                <div class="flex justify-between">
                    <span>ORDEN:</span>
                    <span class="font-bold text-primary">{{ $pedidoCompletado->codigo }}</span>
                </div>
                <div class="flex justify-between">
                    <span>FECHA:</span>
                    <span>{{ now()->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>TIPO:</span>
                    <span class="font-bold uppercase text-secondary">{{ $pedidoCompletado->tipo }} {{ $pedidoCompletado->mesa ? "- Mesa {$pedidoCompletado->mesa->numero}" : '' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>CAJERO:</span>
                    <span>{{ Auth::user()->name }}</span>
                </div>
                @if(!empty($ticketConfig['mostrar_datos_mesero']) && $pedidoCompletado->mesero)
                    <div class="flex justify-between font-bold text-primary">
                        <span>MESERO:</span>
                        <span>{{ $pedidoCompletado->mesero->name }}</span>
                    </div>
                @endif
            </div>

            <!-- Ticket Line Items -->
            <div class="py-3 border-b border-dashed border-surface-container-high space-y-1.5">
                @foreach($pedidoCompletado->items as $it)
                    <div class="flex justify-between text-[11px]">
                        <span>{{ $it->cantidad }}x {{ $it->nombre_producto }}</span>
                        <span class="font-bold">${{ number_format($it->subtotal, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            <!-- Ticket Totals -->
            <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                <div class="flex justify-between">
                    <span>SUBTOTAL:</span>
                    <span>${{ number_format($pedidoCompletado->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($pedidoCompletado->descuento > 0)
                    <div class="flex justify-between text-secondary">
                        <span>DESCUENTO:</span>
                        <span>-${{ number_format($pedidoCompletado->descuento, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if((float) ($pedidoCompletado->propina ?? 0) > 0)
                    <div class="flex justify-between font-bold text-primary">
                        <span>PROPINA VOLUNTARIA:</span>
                        <span>+${{ number_format($pedidoCompletado->propina, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm font-black pt-1 text-on-surface">
                    <span>TOTAL A PAGAR:</span>
                    <span class="text-primary">${{ number_format((float) $pedidoCompletado->total + (float) ($pedidoCompletado->propina ?? 0), 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-on-surface-variant pt-1">
                    <span>PAGADO ({{ strtoupper($pedidoCompletado->metodo_pago) }}):</span>
                    <span>${{ number_format($pedidoCompletado->monto_pagado, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between font-bold text-secondary">
                    <span>CAMBIO:</span>
                    <span>${{ number_format($pedidoCompletado->cambio, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Ticket Footer Message (configurable ticket_80mm) -->
            <div class="pt-4 text-center text-[10px] text-on-surface-variant space-y-1">
                @if(!empty($ticketConfig['pie_pagina']))
                    <p class="font-bold text-on-surface">{{ $ticketConfig['pie_pagina'] }}</p>
                @endif
                @if(!empty($ticketConfig['sugerir_propina']) && !empty($ticketConfig['mensaje_propina']))
                    <p class="text-[9px]">{{ $ticketConfig['mensaje_propina'] }}</p>
                @endif
                @if(!empty($ticketConfig['redes_sociales']))
                    <p class="text-[9px] font-semibold">{{ $ticketConfig['redes_sociales'] }}</p>
                @endif
                @if(!empty($ticketConfig['politica_cambios']))
                    <p class="text-[9px]">{{ $ticketConfig['politica_cambios'] }}</p>
                @endif
            </div>

            <!-- Close / Print buttons (Ocultos al imprimir en papel) -->
            <div class="no-print mt-5 grid grid-cols-2 gap-2">
                <button 
                    onclick="window.print()" 
                    class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer flex items-center justify-center gap-1.5"
                >
                    <span class="material-symbols-outlined text-[16px]">print</span>
                    <span>Imprimir</span>
                </button>
                <button 
                    wire:click="cerrarTicket" 
                    class="rounded-xl bg-primary py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container cursor-pointer"
                >
                    ✓ Finalizar
                </button>
            </div>

            <!-- Botón de Corrección / Devolución Rápida en Caja si hubo error -->
            <div class="no-print mt-2.5 text-center">
                <a href="{{ route('caja.control') }}" class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-500 hover:text-rose-600 transition-colors">
                    <span class="material-symbols-outlined text-[14px]">undo</span>
                    <span>¿Error en el cobro? Gestionar Devolución en Caja</span>
                </a>
            </div>
        </div>
    </div>
@endif
