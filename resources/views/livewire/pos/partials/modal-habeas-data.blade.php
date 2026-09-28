<!-- Modal Ley 1581 Habeas Data y Consentimiento -->
@if($mostrarModalHabeasData)
    <div x-data @keydown.escape.window="$wire.set('mostrarModalHabeasData', false)" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
        <div role="dialog" aria-modal="true" aria-labelledby="modal-habeas-title" class="w-full max-w-lg rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">verified_user</span>
                    </div>
                    <div>
                        <h3 id="modal-habeas-title" class="text-base font-extrabold text-on-surface">Habeas Data & Datos de Contacto</h3>
                        <p class="text-[11px] text-on-surface-variant">Ley 1581 de 2012 · Fidelización y Facturación</p>
                    </div>
                </div>
                <button wire:click="$set('mostrarModalHabeasData', false)" aria-label="Cerrar modal" class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-full text-on-surface-variant hover:text-on-surface cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <!-- Resumen Legal Informativo -->
                <div class="rounded-2xl bg-surface-container-low border border-surface-container-high p-3.5 text-[11px] text-on-surface-variant space-y-1.5">
                    <p class="font-bold text-on-surface flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-primary">policy</span>
                        Autorización para Tratamiento de Datos Personales
                    </p>
                    <p class="leading-relaxed">
                        En cumplimiento de la Ley Estatutaria 1581 de 2012, el comensal autoriza el tratamiento de sus datos de contacto para la prestación del servicio gastronómico, emisión de facturas electrónicas, acumulación de puntos de fidelidad y notificaciones vía WhatsApp o correo electrónico.
                    </p>
                </div>

                <!-- Campos de Contacto -->
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Nombre Completo del Comensal *:</label>
                        <input 
                            type="text" 
                            wire:model="habeasNombre" 
                            placeholder="Ej: Valentina Gómez" 
                            class="w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-2 text-xs font-medium text-on-surface focus:border-primary focus:ring-0"
                        />
                        @error('habeasNombre') <span class="text-error text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Teléfono Móvil (WhatsApp / Pedidos):</label>
                        <input 
                            type="tel" 
                            wire:model="habeasTelefono" 
                            placeholder="Ej: 3001234567" 
                            class="w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-2 text-xs font-medium text-on-surface focus:border-primary focus:ring-0"
                        />
                        @error('habeasTelefono') <span class="text-error text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Correo Electrónico (Facturación & Promos):</label>
                        <input 
                            type="email" 
                            wire:model="habeasEmail" 
                            placeholder="comensal@ejemplo.com" 
                            class="w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-2 text-xs font-medium text-on-surface focus:border-primary focus:ring-0"
                        />
                        @error('habeasEmail') <span class="text-error text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Dirección para Domicilios (Opcional):</label>
                        <input 
                            type="text" 
                            wire:model="habeasDireccion" 
                            placeholder="Calle 123 #45-67, Apto 101" 
                            class="w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-2 text-xs font-medium text-on-surface focus:border-primary focus:ring-0"
                        />
                        @error('habeasDireccion') <span class="text-error text-[11px]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Checkboxes de Consentimiento -->
                <div class="space-y-2.5 pt-2 border-t border-surface-container-high">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input 
                            type="checkbox" 
                            wire:model="habeasAcepta" 
                            class="mt-0.5 rounded border-outline-variant text-primary focus:ring-primary h-4 w-4"
                        />
                        <span class="text-xs font-bold text-on-surface leading-tight">
                            Acepto expresamente los términos y autorizo el tratamiento de mis datos personales (Habeas Data).
                        </span>
                    </label>
                    @error('habeasAcepta') <span class="text-error text-[11px] block">{{ $message }}</span> @enderror

                    <label class="flex items-center gap-2.5 cursor-pointer pl-6">
                        <input 
                            type="checkbox" 
                            wire:model="habeasWhatsapp" 
                            class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4"
                        />
                        <span class="text-xs text-on-surface-variant">
                            Autorizo envío de promociones, estado de pedidos y cupones por WhatsApp.
                        </span>
                    </label>

                    <label class="flex items-center gap-2.5 cursor-pointer pl-6">
                        <input 
                            type="checkbox" 
                            wire:model="habeasEmailPromos" 
                            class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4"
                        />
                        <span class="text-xs text-on-surface-variant">
                            Autorizo envío de boletines de ofertas y facturación por correo electrónico.
                        </span>
                    </label>
                </div>

                <!-- Botones de Acción -->
                <div class="pt-3 grid grid-cols-2 gap-2 border-t border-surface-container-high">
                    <button 
                        type="button" 
                        wire:click="$set('mostrarModalHabeasData', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button 
                        type="button" 
                        wire:click="guardarHabeasData" 
                        class="rounded-xl bg-primary py-2.5 text-xs font-black text-on-primary shadow-md hover:bg-primary-container cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        <span>Guardar Consentimiento</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
