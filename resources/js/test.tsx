import { useEffect } from 'react';
import echo from '@/lib/echo';

export default function Home() {
  useEffect(() => {
    echo.channel('chat')
      .listen('.message.sent', (e) => {
        console.log('Recibido en Reverb:', e.message);
      });
  }, []);

  return (
    <div>
      <h1>Página de prueba WebSocket</h1>
    </div>
  );
}
