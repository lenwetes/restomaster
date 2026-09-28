import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const isBrowser = typeof window !== 'undefined';
const isHttps = isBrowser && window.location.protocol === 'https:';
const defaultHost = isBrowser ? window.location.hostname : 'localhost';
const defaultPort = isHttps ? 443 : 80;

const host = import.meta.env.VITE_REVERB_HOST || defaultHost;
const port = import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : defaultPort;
const key = import.meta.env.VITE_REVERB_APP_KEY || 'restomaster-reverb-key';
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
