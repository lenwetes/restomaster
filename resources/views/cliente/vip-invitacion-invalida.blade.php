@extends('layouts.publico')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-3xl bg-[#1a110d] border border-red-500/30 p-8 text-center shadow-2xl space-y-5">
        <div class="w-16 h-16 rounded-full bg-red-500/20 text-red-400 mx-auto flex items-center justify-center border border-red-500/30">
            <span class="material-symbols-outlined text-[36px]">link_off</span>
        </div>

        <div class="space-y-1.5">
            <h1 class="text-2xl font-black text-[#f5e8e2]">Enlace No Válido</h1>
            <p class="text-xs text-[#c9b7b0]">
                {{ $motivo ?? 'Esta invitación al Club VIP ya no se encuentra vigente o ha alcanzado su límite de uso.' }}
            </p>
        </div>

        <p class="text-xs text-stone-400">
            Si eres cliente frecuente y deseas ingresar al Club VIP, consulta con nuestro personal o administrador en tu próxima visita.
        </p>

        <a
            href="{{ url('/') }}"
            class="inline-flex items-center justify-center gap-2 w-full py-3 rounded-2xl bg-[#281a14] hover:bg-[#34221b] text-[#f5e8e2] font-extrabold text-xs uppercase tracking-wider border border-[#432f26] transition-all"
        >
            <span>Regresar al Inicio</span>
        </a>
    </div>
</div>
@endsection
