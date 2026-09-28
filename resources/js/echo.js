import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const isBrowser = typeof window !== 'undefined';
const isHttps = isBrowser && window.location.protocol === 'https:';
const hostname = isBrowser ? window.location.hostname : 'localhost';
const isLocalhost = hostname === 'localhost' || hostname === '127.0.0.1';

// En entorno local de desarrollo (artisan serve), Reverb escucha directamente en el puerto 8080.
// En producción (Coolify con Nginx), el tráfico viaja por el puerto web estándar (443 HTTPS o 80 HTTP).
const defaultPort = isHttps ? 443 : (isLocalhost ? 8080 : 80);

const host = import.meta.env.VITE_REVERB_HOST || hostname;
const port = import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : defaultPort;
const key = import.meta.env.VITE_REVERB_APP_KEY || (isLocalhost ? '42nzxmdpa0plgriowevm' : 'restomaster-reverb-key');
const forceTLS = import.meta.env.VITE_REVERB_SCHEME ? import.meta.env.VITE_REVERB_SCHEME === 'https' : isHttps;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: forceTLS,
    enabledTransports: ['ws', 'wss'],
});
