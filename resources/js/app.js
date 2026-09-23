//
// Toast global: escucha eventos Livewire 'notificacion' ({mensaje, tipo})
// y flashes de sesión con la misma forma visual.
//
document.addEventListener('DOMContentLoaded', () => {
    const contenedor = document.getElementById('app-toasts');
    if (! contenedor) {
        return;
    }

    const colores = {
        success: 'bg-secondary-container text-on-secondary-container border-secondary/40',
        error: 'bg-error-container text-on-error-container border-error/40',
        info: 'bg-primary-container text-on-primary-container border-primary/40',
        warning: 'bg-tertiary-container text-on-tertiary-container border-tertiary/40',
    };

    window.mostrarNotificacion = ({ mensaje, tipo = 'info' }) => {
        const toast = document.createElement('div');
        toast.className = `max-w-xs rounded-2xl border px-4 py-3 text-xs font-bold shadow-2xl animate-fade-in ${(colores[tipo] ?? colores.info)}`;
        toast.setAttribute('role', 'status');
        toast.textContent = mensaje;
        contenedor.appendChild(toast);
        window.setTimeout(() => toast.remove(), 4500);
    };

    const engancharLivewire = () => {
        if (window.Livewire && typeof window.Livewire.on === 'function') {
            window.Livewire.on('notificacion', (datos) => {
                const evento = Array.isArray(datos) ? datos[0] : datos;
                window.mostrarNotificacion(evento ?? {});
            });
        } else {
            window.setTimeout(engancharLivewire, 300);
        }
    };
    engancharLivewire();
});

// Store reactivo para colapsar/expandir el menú lateral (Mini-Rail)
const initSidebarStore = () => {
    if (window.Alpine && typeof window.Alpine.store === 'function') {
        if (!window.Alpine.store('sidebar')) {
            window.Alpine.store('sidebar', {
                collapsed: localStorage.getItem('resto_sidebar_collapsed') === 'true',
                toggle() {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('resto_sidebar_collapsed', this.collapsed);
                }
            });
        }
    }
};

initSidebarStore();
document.addEventListener('alpine:init', initSidebarStore);
document.addEventListener('livewire:navigated', initSidebarStore);

//
// Cobro rápido POS: al abrir el cobro (o elegir efectivo/mixto) se enfoca
// y selecciona la caja de "Monto Entregado" para el cajero.
// Sin x-data en el input para no interferir con el morph de Livewire.
//
const enfocarMontoEntregado = () => {
    const input = document.querySelector('input[data-monto-entregado]');
    if (!input || document.activeElement === input) {
        return;
    }
    if (typeof input.focus === 'function') {
        try {
            input.focus({ preventScroll: true });
        } catch (_) {
            input.focus();
        }
    }
    try {
        input.select();
    } catch (_) {
        // Inputs no seleccionables: se ignora sin romper.
    }
};

const engancharEnfoqueMonto = () => {
    if (window.Livewire && typeof window.Livewire.on === 'function') {
        window.Livewire.on('enfocar-monto', enfocarMontoEntregado);
    } else {
        window.setTimeout(engancharEnfoqueMonto, 300);
    }
};
engancharEnfoqueMonto();
document.addEventListener('livewire:navigated', engancharEnfoqueMonto);

//
// Mejora táctil de inputs numéricos (RestoMaster):
//  1) Autoselección: al enfocar un input numérico se selecciona el valor
//     para reemplazarlo de un toque (pantallas táctiles / POS).
//  2) Formato es-CO: los inputs con data-miles muestran miles con punto y
//     decimales con coma (1.234.567,89). Mientras se escribe se trabaja en
//     crudo para no pelear con wire:model; al salir se formatea y se
//     sincroniza el valor normalizado. Compatible con wire:model.live.
//
const decDe = (el) => {
    const n = parseInt(el.getAttribute('data-decimales') ?? '2', 10);
    return Number.isNaN(n) ? 2 : Math.min(Math.max(n, 0), 4);
};

// Convierte lo visible (crudo o formateado) a crudo interno "1234.56".
// Convención es-CO: el último separador es decimal, los demás son miles.
const crudoMiles = (valor, decimales = 2) => {
    const partes = String(valor ?? '').split(/[.,]/);
    let frac = '';
    let entero = '';
    if (partes.length === 1) {
        entero = partes[0];
    } else {
        frac = partes.pop() ?? '';
        entero = partes.join('');
    }
    entero = entero.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    frac = frac.replace(/\D/g, '').slice(0, decimales);
    if (entero === '' && frac === '') {
        return '';
    }
    return frac !== '' ? `${entero === '' ? '0' : entero}.${frac}` : entero;
};

const formatearMiles = (crudo, decimales = 2) => {
    if (crudo === '' || crudo === null || crudo === undefined) {
        return '';
    }
    const n = Number(crudo);
    if (!Number.isFinite(n)) {
        return String(crudo);
    }
    return new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimales,
    }).format(n);
};

const esInputMiles = (t) => t instanceof HTMLInputElement && t.hasAttribute('data-miles');

// Al enfocar: mostrar crudo + seleccionar (o solo seleccionar en type=number).
document.addEventListener('focusin', (e) => {
    const t = e.target;
    if (!(t instanceof HTMLInputElement) || t.readOnly || t.disabled) {
        return;
    }
    if (esInputMiles(t)) {
        t.value = crudoMiles(t.value, decDe(t));
        t.select();
        return;
    }
    if (t.type === 'number' || t.inputMode === 'numeric' || t.inputMode === 'decimal') {
        t.select();
    }
});

// Teclado: solo dígitos y un separador (el resto se bloquea antes del input).
document.addEventListener('keydown', (e) => {
    const t = e.target;
    if (!esInputMiles(t) || !e.isTrusted || e.ctrlKey || e.metaKey || e.altKey) {
        return;
    }
    const permitidas = ['Backspace', 'Delete', 'Tab', 'Enter', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'Decimal'];
    if (permitidas.includes(e.key)) {
        return;
    }
    if (!/^[0-9,.\s]$/.test(e.key)) {
        e.preventDefault();
    }
});

// Entrada real (pegar, autocompletar): sanear y re-sincronizar con Livewire.
document.addEventListener('input', (e) => {
    const t = e.target;
    if (!esInputMiles(t) || !e.isTrusted) {
        return;
    }
    const crudo = crudoMiles(t.value, decDe(t));
    if (crudo !== t.value) {
        t.value = crudo;
        t.dispatchEvent(new Event('input', { bubbles: true }));
    }
});

// Al salir: normalizar (redondear a decimales), sincronizar y formatear.
document.addEventListener('focusout', (e) => {
    const t = e.target;
    if (!esInputMiles(t)) {
        return;
    }
    const dec = decDe(t);
    const crudo = crudoMiles(t.value, dec);
    let normalizado = '';
    if (crudo !== '') {
        const n = Number(crudo);
        if (Number.isFinite(n)) {
            normalizado = dec === 0 ? String(Math.trunc(n)) : String(Number(n.toFixed(dec)));
        } else {
            normalizado = crudo;
        }
    }
    if (normalizado !== t.value) {
        t.value = normalizado;
        t.dispatchEvent(new Event('input', { bubbles: true }));
    }
    t.value = formatearMiles(normalizado === '' ? '' : normalizado, dec);
});

// Tras cada render de Livewire (y al cargar): formatear los no enfocados.
const reformatearMiles = () => {
    document.querySelectorAll('input[data-miles]').forEach((el) => {
        if (el === document.activeElement) {
            return;
        }
        el.value = formatearMiles(crudoMiles(el.value, decDe(el)), decDe(el));
    });
};

document.addEventListener('DOMContentLoaded', reformatearMiles);
document.addEventListener('livewire:navigated', reformatearMiles);
if (window.Livewire && typeof window.Livewire.hook === 'function') {
    window.Livewire.hook('morph.updated', reformatearMiles);
} else {
    document.addEventListener('livewire:init', () => {
        if (typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morph.updated', reformatearMiles);
        }
    });
}
