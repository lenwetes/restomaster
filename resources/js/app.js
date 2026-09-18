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
