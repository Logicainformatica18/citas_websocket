import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import Echo from '@/lib/echo';

interface Message {
  id: number;
  user: {
    id: number;
    email: string;
    names: string;
    role_name?: string;
  };
  message: string;
  created_at: string;
  recipient_id?: number;
}

interface User {
  id: number;
  names: string;
  email: string;
}

interface PageProps {
  auth: {
    user: {
      id: number;
      email: string;
      names: string;
      role_name?: string;
    };
  };
  users: User[];
}

export default function ChatWidget() {
  const { auth, users } = usePage<PageProps>().props;
  const [messages, setMessages] = useState<Message[]>([]);
  const [newMessage, setNewMessage] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const messagesEndRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!isOpen || !selectedUser) return;

    loadMessages();

    const channel = Echo.private(`chat.${auth.user.id}`);
    channel.listen('.chat.message.sent', (e: any) => {
      const message = e.message ?? e; // fallback if message is at root
      setMessages((prev) => [...prev, message]);
    });

    return () => {
      Echo.leave(`chat.${auth.user.id}`);
    };
  }, [isOpen, selectedUser]);

  const loadMessages = async () => {
    try {
      const res = await axios.get(`/chat/messages/${selectedUser?.id}`);
      setMessages(res.data);
    } catch (error) {
      console.error('Error loading messages', error);
    }
  };

  const sendMessage = async () => {
    if (!newMessage.trim() || !selectedUser) return;

    try {
      await axios.post('/chat/messages', {
        message: newMessage,
        recipient_id: selectedUser.id,
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
        <div className="w-96 h-[30rem] bg-white dark:bg-gray-800 shadow-lg rounded-lg flex flex-col overflow-hidden">
          <div className="bg-blue-600 text-white px-4 py-2 font-semibold flex justify-between items-center">
            <span>Chat de usuarios</span>
            <button onClick={() => setIsOpen(false)} className="text-white text-lg">×</button>
          </div>

          {!selectedUser ? (
            <div className="flex-1 overflow-y-auto p-2">
              <h3 className="text-sm text-gray-700 dark:text-white mb-2">Selecciona un usuario:</h3>
              <ul className="space-y-1">
                {users.filter(u => u.id !== auth.user.id).map(user => (
                  <li key={user.id}>
                    <button
                      onClick={() => setSelectedUser(user)}
                      className="w-full text-left p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                      {user.names} ({user.email})
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          ) : (
            <>
              <div className="bg-gray-200 dark:bg-gray-700 text-sm text-center py-1">
                Chateando con <strong>{selectedUser.names}</strong>
              </div>
              <div className="flex-1 p-2 overflow-y-auto space-y-2">
                {messages.map((msg) => (
                  <div key={msg.id} className="bg-gray-100 dark:bg-gray-700 p-2 rounded shadow">
                    <div className="text-xs text-gray-500 dark:text-gray-300">
                      {msg.user.email} - {new Date(msg.created_at).toLocaleTimeString()}
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
            </>
          )}
        </div>
      )}
    </div>
  );
}
