import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const isBrowser = typeof window !== 'undefined';
const isHttps = isBrowser && window.location.protocol === 'https:';
const hostname = isBrowser ? window.location.hostname : 'localhost';
const isLocalhost = hostname === 'localhost' || hostname === '127.0.0.1';

// Si el navegador usa un puerto explícito (ej: 8004 en Coolify/Docker), se respeta automáticamente
const browserPort = isBrowser && window.location.port ? parseInt(window.location.port, 10) : null;
const defaultPort = browserPort || (isHttps ? 443 : (isLocalhost ? 8080 : 80));

const host = import.meta.env.VITE_REVERB_HOST || hostname;
const port = import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : defaultPort;
const key = import.meta.env.VITE_REVERB_APP_KEY || (isLocalhost ? '42nzxmdpa0plgriowevm' : 'restomaster-reverb-key');

// Solo forzar TLS si la página actual se sirve bajo HTTPS para evitar net::ERR_CERT_AUTHORITY_INVALID en HTTP
const forceTLS = isHttps;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: forceTLS,
    enabledTransports: ['ws', 'wss'],
});
