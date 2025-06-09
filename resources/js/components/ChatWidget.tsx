import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import Echo from '@/lib/echo'; // ✅ Reverb ya configurado en echo.ts

console.log('✅ Echo configurado manualmente con canal público');

interface Message {
  id: number;
  user: {
    email: string;
    name: string;
    role_name?: string;
  };
  message: string;
  created_at: string;
}

interface PageProps {
  auth: {
    user: {
      email: string;
      role_name?: string;
    };
  };
}

export default function ChatWidget() {
  const { auth } = usePage<PageProps>().props;
  const [messages, setMessages] = useState<Message[]>([]);
  const [newMessage, setNewMessage] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!isOpen) return;

    loadMessages();

    try {
      console.log('📡 Subscribing to chat-room...');
      const channel = Echo.channel('chat-room');

      channel.listen('.chat.message.sent', (e: Message) => {
        console.log('💬 Nuevo mensaje:', e);
        setMessages((prev) => [...prev, e]);
      });

      return () => {
        Echo.leave('chat-room');
        console.log('👋 Abandonado canal chat-room');
      };
    } catch (err) {
      console.error('❌ Error en conexión a WebSocket chat-room:', err);
    }
  }, [isOpen]);

  const loadMessages = async () => {
    try {
      const res = await axios.get('/chat/messages');
      setMessages(res.data);
    } catch (error) {
      console.error('Error loading messages', error);
    }
  };

  const sendMessage = async () => {
    if (!newMessage.trim()) return;

    try {
      await axios.post('/chat/messages', {
        message: newMessage,
      });
      setNewMessage('');
    } catch (error) {
      console.error('Error sending message', error);
    }
  };

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  return (
    <div className="fixed bottom-4 right-4 z-50">
      {!isOpen ? (
        <button
          onClick={() => setIsOpen(true)}
          className="bg-blue-600 text-white px-4 py-2 rounded shadow-lg hover:bg-blue-700"
        >
          Abrir Chat
        </button>
      ) : (
        <div className="w-80 h-96 bg-white dark:bg-gray-800 shadow-lg rounded-lg flex flex-col overflow-hidden">
          <div className="bg-blue-600 text-white px-4 py-2 font-semibold flex justify-between items-center">
            <span>Chat de usuarios</span>
            <button onClick={() => setIsOpen(false)} className="text-white text-lg">×</button>
          </div>
          <div className="flex-1 p-2 overflow-y-auto space-y-2">
            {messages.map((msg) => (
              <div
                key={msg.id}
                className="bg-gray-100 dark:bg-gray-700 p-2 rounded shadow"
              >
                <div className="text-xs text-gray-500 dark:text-gray-300">
                  {msg.user.email} ({msg.user.role_name ?? '-'}) -{' '}
                  {new Date(msg.created_at).toLocaleTimeString()}
                </div>
                <div className="text-sm text-gray-900 dark:text-white">{msg.message}</div>
              </div>
            ))}
            <div ref={messagesEndRef} />
          </div>
          <div className="p-2 border-t border-gray-200 dark:border-gray-600">
            <div className="flex items-center space-x-2">
              <input
                type="text"
                value={newMessage}
                onChange={(e) => setNewMessage(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && sendMessage()}
                className="flex-1 px-2 py-1 border rounded dark:bg-gray-700 dark:text-white"
                placeholder="Escribe un mensaje..."
              />
              <button
                onClick={sendMessage}
                className="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700"
              >
                Enviar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
