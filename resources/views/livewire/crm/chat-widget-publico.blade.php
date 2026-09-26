<?php

use App\Models\CrmConversacion;
use App\Models\CrmMensaje;
use App\Services\Ai\CrmChatOrchestratorService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $abierto = false;

    public string $session_token = '';

    public ?int $conversacion_id = null;

    public string $mensajeInput = '';

    public string $nombreContacto = '';

    public string $modoAtencion = 'ia';

    public array $mensajes = [];

    public function mount(): void
    {
        // Generar o recuperar session_token único del comensal
        $this->session_token = Session::get('restomaster_chat_token', function () {
            $token = (string) Str::uuid();
            Session::put('restomaster_chat_token', $token);

            return $token;
        });

        $this->cargarConversacion();
    }

    public function cargarConversacion(): void
    {
        $conv = CrmConversacion::where('canal', 'web')
            ->where('session_token', $this->session_token)
            ->first();

        if ($conv) {
            $this->conversacion_id = $conv->id;
            $this->modoAtencion = $conv->modo_atencion;
            $this->nombreContacto = $conv->nombre_contacto ?: 'Visitante';
            $this->recargarMensajes();
        }
    }

    public function recargarMensajes(): void
    {
        if (! $this->conversacion_id) {
            return;
        }

        $this->mensajes = CrmMensaje::where('crm_conversacion_id', $this->conversacion_id)
            ->orderBy('created_at', 'asc')
            ->take(50)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'emisor' => $m->emisor,
                'contenido' => $m->contenido,
                'hora' => $m->created_at->format('g:i A'),
            ])
            ->toArray();
    }

    public function toggleChat(): void
    {
        $this->abierto = ! $this->abierto;
        if ($this->abierto) {
            $this->cargarConversacion();
            $this->dispatch('chat-abierto');
        }
    }

    public function enviarMensaje(): void
    {
        $texto = trim($this->mensajeInput);
        if (empty($texto) || mb_strlen($texto) > 1000) {
            return;
        }

        // Freno de rate limit por comensal en widget público (15 mensajes por minuto)
        $rateKey = 'public_chat_widget:'.$this->session_token;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 15)) {
            $this->js("alert('Estás enviando mensajes muy rápido. Por favor espera un momento.');");

            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 60);

        $this->mensajeInput = '';

        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $conv = $orchestrator->obtenerOCrearConversacion(
            canal: 'web',
            identificadorRemoto: $this->session_token,
            nombre: $this->nombreContacto ?: 'Visitante Web'
        );

        $this->conversacion_id = $conv->id;
        $this->modoAtencion = $conv->modo_atencion;

        $orchestrator->procesarMensajeCliente($conv, $texto);

        $this->recargarMensajes();
        $this->dispatch('mensaje-enviado');
    }

    public function enviarAccionRapida(string $texto): void
    {
        $this->mensajeInput = $texto;
        $this->enviarMensaje();
    }
};
?>

<div class="fixed bottom-6 right-6 z-50 font-sans" x-data="{ abierto: @entangle('abierto') }">
    <!-- Ventana de Chat Flotante -->
    <div 
        x-show="abierto" 
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 translate-y-6 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-6 scale-95"
        class="w-[360px] sm:w-[400px] h-[580px] max-h-[85vh] bg-[#140c09] text-[#f5e8e2] rounded-3xl border border-[#432f26]/80 shadow-2xl flex flex-col overflow-hidden backdrop-blur-xl mb-4"
        style="box-shadow: 0 20px 40px -10px rgba(0,0,0,0.8), 0 0 25px rgba(224, 68, 46, 0.15);"
    >
        <!-- Header del Chat -->
        <div class="px-5 py-4 bg-gradient-to-r from-[#21140f] to-[#180e09] border-b border-[#432f26]/60 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-[#e0442e] to-[#ff7e67] flex items-center justify-center text-white font-black shadow-md">
                        <span class="material-symbols-outlined text-[20px]">smart_toy</span>
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 border-2 border-[#140c09] animate-pulse"></span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-1.5 leading-tight">
                        <span>RestoMaster Concierge</span>
                    </h3>
                    <p class="text-[11px] text-[#c4a89e] flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>En línea · Asesor & Reservas</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1">
                <button wire:click="toggleChat" class="w-8 h-8 rounded-full hover:bg-white/10 flex items-center justify-center text-[#c4a89e] hover:text-white transition-colors" title="Cerrar">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
        </div>

        <!-- Área de Conversación con Polling suave de 4s -->
        <div 
            class="flex-1 p-4 overflow-y-auto space-y-3.5 no-scrollbar" 
            id="chat-messages-container"
            @if($abierto) wire:poll.4s="recargarMensajes" @endif
            x-init="$watch('abierto', val => { if(val) setTimeout(() => $el.scrollTop = $el.scrollHeight, 100) })"
            x-on:mensaje-enviado.window="setTimeout(() => $el.scrollTop = $el.scrollHeight, 100)"
        >
            <!-- Mensaje Inicial de Bienvenida -->
            <div class="flex gap-2.5 items-start">
                <div class="w-7 h-7 rounded-xl bg-[#2a1a14] border border-[#432f26] flex items-center justify-center text-[#ff7e67] shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-[15px]">restaurant</span>
                </div>
                <div class="space-y-2 max-w-[85%]">
                    <div class="p-3.5 rounded-2xl rounded-tl-sm bg-[#1e130e] border border-[#432f26]/60 text-xs text-[#eedfd9] leading-relaxed shadow-sm">
                        ¡Hola! Te damos la bienvenida a <strong>RestoMaster Provenza</strong> ✨. ¿En qué podemos deleitarte hoy? Puedo ayudarte a consultar la carta, ver opciones sin gluten o agendar tu mesa.
                    </div>

                    @if(empty($mensajes))
                        <!-- Botones de Acción Rápida -->
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <button wire:click="enviarAccionRapida('¿Qué cortes de carne o especialidades me recomiendan?')" class="text-[11px] px-3 py-1.5 rounded-xl bg-[#261711] hover:bg-[#342017] border border-[#52382c] text-[#f5e8e2] font-semibold transition-all text-left">
                                🥩 Recomendaciones de la carta
                            </button>
                            <button wire:click="enviarAccionRapida('Deseo reservar una mesa para cenar hoy')" class="text-[11px] px-3 py-1.5 rounded-xl bg-[#261711] hover:bg-[#342017] border border-[#52382c] text-[#f5e8e2] font-semibold transition-all text-left">
                                📅 Reservar mesa
                            </button>
                            <button wire:click="enviarAccionRapida('¿Tienen platos para personas alérgicas o celíacas?')" class="text-[11px] px-3 py-1.5 rounded-xl bg-[#261711] hover:bg-[#342017] border border-[#52382c] text-[#f5e8e2] font-semibold transition-all text-left">
                                🌾 Alérgenos y celíacos
                            </button>
                            <button wire:click="enviarAccionRapida('Deseo comunicarme con un asesor humano')" class="text-[11px] px-3 py-1.5 rounded-xl bg-[#261711] hover:bg-[#342017] border border-[#52382c] text-[#f5e8e2] font-semibold transition-all text-left">
                                👤 Hablar con una persona
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Historial de Mensajes -->
            @foreach($mensajes as $msg)
                @if($msg['emisor'] === 'cliente')
                    <div class="flex justify-end">
                        <div class="max-w-[82%] space-y-1">
                            <div class="p-3.5 rounded-2xl rounded-tr-sm bg-gradient-to-r from-[#e0442e] to-[#cc3722] text-white text-xs leading-relaxed shadow-md">
                                {{ $msg['contenido'] }}
                            </div>
                            <span class="block text-[10px] text-right text-[#a88d84] px-1">{{ $msg['hora'] }}</span>
                        </div>
                    </div>
                @else
                    <div class="flex gap-2.5 items-start">
                        <div class="w-7 h-7 rounded-xl bg-[#2a1a14] border border-[#432f26] flex items-center justify-center text-[#ff7e67] shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-[15px]">room_service</span>
                        </div>
                        <div class="max-w-[85%] space-y-1">
                            <div class="p-3.5 rounded-2xl rounded-tl-sm bg-[#1e130e] border border-[#432f26]/60 text-xs text-[#eedfd9] leading-relaxed shadow-sm whitespace-pre-line">
                                {{ $msg['contenido'] }}
                            </div>
                            <span class="block text-[10px] text-[#a88d84] px-1">{{ $msg['hora'] }}</span>
                        </div>
                    </div>
                @endif
            @endforeach

            <!-- Indicador de Carga / Pensando -->
            <div wire:loading wire:target="enviarMensaje, enviarAccionRapida" class="flex gap-2.5 items-start">
                <div class="w-7 h-7 rounded-xl bg-[#2a1a14] border border-[#432f26] flex items-center justify-center text-[#ff7e67] shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-[15px] animate-spin">sync</span>
                </div>
                <div class="p-3 rounded-2xl bg-[#1e130e] border border-[#432f26]/60 flex items-center gap-1.5 text-xs text-[#c4a89e]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#ff7e67] animate-bounce"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-[#ff7e67] animate-bounce [animation-delay:0.2s]"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-[#ff7e67] animate-bounce [animation-delay:0.4s]"></span>
                    <span class="ml-1 text-[11px] text-[#a88d84]">Escribiendo respuesta...</span>
                </div>
            </div>
        </div>

        <!-- Input de Redacción de Mensaje -->
        <div class="p-3 bg-[#180e09] border-t border-[#432f26]/60 shrink-0">
            <form wire:submit="enviarMensaje" class="flex items-center gap-2">
                <input 
                    type="text" 
                    wire:model="mensajeInput" 
                    placeholder="Escribe tu mensaje o consulta..." 
                    class="flex-1 px-4 py-2.5 rounded-xl bg-[#241610] text-xs text-white placeholder-[#8a6e65] border border-[#432f26] focus:border-[#e0442e] focus:outline-none focus:ring-1 focus:ring-[#e0442e]"
                    autofocus
                >
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="w-10 h-10 rounded-xl bg-[#e0442e] hover:bg-[#ff553e] text-white flex items-center justify-center shadow-md transition-all shrink-0 disabled:opacity-50"
                >
                    <span class="material-symbols-outlined text-[18px]">send</span>
                </button>
            </form>
            <div class="flex items-center justify-between mt-2 px-1 text-[10px] text-[#8a6e65]">
                <span>RestoMaster Concierge 24/7</span>
                <span class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-[12px]">lock</span>
                    <span>Conversación privada</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Botón Burbuja Flotante para Abrir/Cerrar el Chat -->
    <button 
        wire:click="toggleChat"
        class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-[#c4321d] to-[#e0442e] hover:from-[#e0442e] hover:to-[#ff5c47] text-white flex items-center justify-center shadow-2xl transition-all duration-300 hover:scale-105 active:scale-95 group relative"
        style="box-shadow: 0 10px 25px -5px rgba(224, 68, 46, 0.5), 0 0 15px rgba(224, 68, 46, 0.3);"
        title="¿Deseas hablar con nosotros? Abrir Concierge"
    >
        <span x-show="!abierto" class="material-symbols-outlined text-[28px] transition-transform group-hover:rotate-6">chat</span>
        <span x-show="abierto" class="material-symbols-outlined text-[28px]">expand_more</span>
        
        <!-- Punto de pulso activo -->
        <span x-show="!abierto" class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 border-2 border-[#0e0907] flex items-center justify-center">
            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
        </span>
    </button>
</div>
