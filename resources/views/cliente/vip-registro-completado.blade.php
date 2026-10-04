@extends('layouts.publico')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-3xl bg-[#1a110d] border border-amber-500/30 p-8 text-center shadow-2xl space-y-5">
        <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 mx-auto flex items-center justify-center border border-emerald-500/30">
            <span class="material-symbols-outlined text-[36px]">mark_email_read</span>
        </div>

        <div class="space-y-1.5">
            <h1 class="text-2xl font-black text-[#f5e8e2]">¡Solicitud Recibida!</h1>
            <p class="text-xs text-[#c9b7b0]">
                Gracias por registrarte, <strong>{{ $cliente->nombre }}</strong>. Tu solicitud de ingreso al Club VIP está siendo validada por la dirección del restaurante.
            </p>
        </div>

        <div class="p-4 rounded-2xl bg-[#281a14] border border-[#432f26] text-xs text-left space-y-2 text-[#c9b7b0]">
            <div class="flex items-center gap-2 text-[#f5e8e2] font-bold">
                <span class="material-symbols-outlined text-amber-500 text-[18px]">verified</span>
                <span>¿Qué sucederá ahora?</span>
            </div>
            <p class="text-[11px]">
                En breves momentos recibirás un mensaje de bienvenida en tu WhatsApp o Correo con tus credenciales para acceder a tus beneficios y promociones exclusivas.
            </p>
        </div>

        <a
            href="{{ url('/') }}"
            class="inline-flex items-center justify-center gap-2 w-full py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-stone-900 font-extrabold text-xs uppercase tracking-wider transition-all"
        >
            <span>Ir a la Carta Digital</span>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
    </div>
</div>
@endsection
