@extends('layouts.publico')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-3xl bg-[#1a110d] border border-amber-500/30 p-6 md:p-8 shadow-2xl space-y-6">
        <!-- Encabezado Club VIP Haute Prestige -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 text-amber-400 border border-amber-500/30 text-xs font-black uppercase tracking-wider">
                <span class="material-symbols-outlined text-[16px]">stars</span>
                <span>Invitación Exclusiva · Club VIP</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-[#f5e8e2] tracking-tight">
                Bienvenido al Círculo VIP
            </h1>
            <p class="text-xs text-[#c9b7b0] max-w-sm mx-auto">
                Has sido seleccionado por tu lealtad en RestoMaster para disfrutar de privilegios gastronómicos, promociones reservadas y atención preferencial.
            </p>
        </div>

        @if(session('error'))
            <div class="p-3.5 rounded-2xl bg-red-500/15 border border-red-500/30 text-xs text-red-300 font-bold">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('vip.registro.guardar', $token) }}" method="POST" class="space-y-4">
            @csrf

            <!-- Nombre -->
            <div>
                <label class="block text-xs font-bold text-[#c9b7b0] uppercase tracking-wider mb-1">Nombre Completo *</label>
                <input
                    type="text"
                    name="nombre"
                    value="{{ old('nombre', $cliente->nombre) }}"
                    required
                    class="w-full h-11 px-3.5 rounded-xl border border-[#432f26] bg-[#281a14] text-xs text-[#f5e8e2] font-semibold focus:border-amber-500 focus:ring-0"
                />
                @error('nombre') <span class="text-[11px] text-red-400 font-bold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Teléfono -->
                <div>
                    <label class="block text-xs font-bold text-[#c9b7b0] uppercase tracking-wider mb-1">Teléfono / WhatsApp *</label>
                    <input
                        type="text"
                        name="telefono"
                        value="{{ old('telefono', $cliente->telefono) }}"
                        required
                        class="w-full h-11 px-3.5 rounded-xl border border-[#432f26] bg-[#281a14] text-xs text-[#f5e8e2] font-semibold focus:border-amber-500 focus:ring-0"
                    />
                    @error('telefono') <span class="text-[11px] text-red-400 font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Correo -->
                <div>
                    <label class="block text-xs font-bold text-[#c9b7b0] uppercase tracking-wider mb-1">Correo Electrónico *</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $cliente->email) }}"
                        required
                        class="w-full h-11 px-3.5 rounded-xl border border-[#432f26] bg-[#281a14] text-xs text-[#f5e8e2] font-semibold focus:border-amber-500 focus:ring-0"
                    />
                    @error('email') <span class="text-[11px] text-red-400 font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Fecha de Nacimiento -->
            <div>
                <label class="block text-xs font-bold text-[#c9b7b0] uppercase tracking-wider mb-1">
                    Fecha de Nacimiento * (Celebración y regalos VIP)
                </label>
                <input
                    type="date"
                    name="fecha_nacimiento"
                    value="{{ old('fecha_nacimiento', $cliente->fecha_nacimiento?->format('Y-m-d')) }}"
                    required
                    class="w-full h-11 px-3.5 rounded-xl border border-[#432f26] bg-[#281a14] text-xs text-[#f5e8e2] font-semibold focus:border-amber-500 focus:ring-0"
                />
                @error('fecha_nacimiento') <span class="text-[11px] text-red-400 font-bold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Contraseña -->
                <div>
                    <label class="block text-xs font-bold text-[#c9b7b0] uppercase tracking-wider mb-1">Crea tu Contraseña *</label>
                    <input
                        type="password"
                        name="password"
                        required
                        placeholder="Mínimo 8 caracteres"
                        class="w-full h-11 px-3.5 rounded-xl border border-[#432f26] bg-[#281a14] text-xs text-[#f5e8e2] font-semibold focus:border-amber-500 focus:ring-0"
                    />
                    @error('password') <span class="text-[11px] text-red-400 font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Confirmar Contraseña -->
                <div>
                    <label class="block text-xs font-bold text-[#c9b7b0] uppercase tracking-wider mb-1">Confirmar Contraseña *</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        required
                        placeholder="Repite la contraseña"
                        class="w-full h-11 px-3.5 rounded-xl border border-[#432f26] bg-[#281a14] text-xs text-[#f5e8e2] font-semibold focus:border-amber-500 focus:ring-0"
                    />
                </div>
            </div>

            <!-- Consentimientos Legales (Habeas Data & Comunicaciones) -->
            <div class="pt-3 border-t border-[#432f26] space-y-2 text-xs">
                <label class="flex items-start gap-2.5 text-[#c9b7b0] cursor-pointer">
                    <input
                        type="checkbox"
                        name="acepta_tratamiento_datos"
                        value="1"
                        checked
                        required
                        class="mt-0.5 rounded border-[#432f26] bg-[#281a14] text-amber-500 focus:ring-0"
                    />
                    <span>
                        Acepto el <a href="#" class="text-amber-400 underline font-bold">tratamiento de mis datos personales</a> conforme a la Ley 1581 de 2012 y las políticas de privacidad del restaurante.
                    </span>
                </label>
                @error('acepta_tratamiento_datos') <span class="text-[11px] text-red-400 font-bold block">{{ $message }}</span> @enderror

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                    <label class="flex items-center gap-2 text-[11px] text-[#c9b7b0] cursor-pointer">
                        <input
                            type="checkbox"
                            name="autoriza_whatsapp"
                            value="1"
                            checked
                            class="rounded border-[#432f26] bg-[#281a14] text-amber-500 focus:ring-0"
                        />
                        <span>Recibir beneficios por WhatsApp</span>
                    </label>

                    <label class="flex items-center gap-2 text-[11px] text-[#c9b7b0] cursor-pointer">
                        <input
                            type="checkbox"
                            name="autoriza_email"
                            value="1"
                            checked
                            class="rounded border-[#432f26] bg-[#281a14] text-amber-500 focus:ring-0"
                        />
                        <span>Recibir beneficios por Correo</span>
                    </label>
                </div>
            </div>

            <button
                type="submit"
                class="w-full py-3.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-stone-900 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/25 active:scale-95 transition-all flex items-center justify-center gap-2 mt-4"
            >
                <span class="material-symbols-outlined text-[20px]">stars</span>
                <span>Confirmar & Activar Mi Membresía VIP</span>
            </button>
        </form>
    </div>
</div>
@endsection
