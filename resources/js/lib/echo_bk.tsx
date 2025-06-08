import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const host = import.meta.env.VITE_REVERB_HOST || 'localhost';
const port = import.meta.env.VITE_REVERB_PORT || 8080;
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

const echoInstance = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: host,
  wsPort: port,
  wssPort: port,
  forceTLS: scheme === 'https',
  enabledTransports: ['ws', 'wss'],
  cluster: 'mt1', // 👈 Agrega esta línea para evitar el error
});
window.Echo = echoInstance;

export default echoInstance;
