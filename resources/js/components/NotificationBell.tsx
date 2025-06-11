import { useEffect, useState } from 'react';
import { Bell } from 'lucide-react';
import Echo from '@/lib/echo';
import { Link } from '@inertiajs/react';
import clsx from 'clsx';

interface Notification {
  id: number;
  message: string;
  route: string;
  clientName?: string;
  isNew?: boolean;
  isRead?: boolean;
}

export default function NotificationBell() {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);

  useEffect(() => {
    const channel = Echo.channel('supports');

    channel.listen('.record.changed', (e: any) => {
      if (e.model === 'Support' && e.action === 'created') {
        const newNotification = {
          id: e.data.id,
          message: `Nuevo soporte: ${e.data.subject}`,
          route: `/reports/${e.data.id}`,
          clientName: e.data.client?.Razon_Social || 'Cliente desconocido',
          isNew: true,
          isRead: false,
        };

        setNotifications((prev) => [newNotification, ...prev]);
      }
    });

    return () => {
      Echo.leave('supports');
    };
  }, []);

  const handleMarkAsRead = (id: number) => {
    setNotifications((prev) =>
      prev.map((n) =>
        n.id === id ? { ...n, isRead: true, isNew: false } : n
      )
    );
  };

  return (
    <div className="relative mr-10">
      <button
        onClick={() => setShowDropdown(!showDropdown)}
        className="relative focus:outline-none"
      >
        <Bell className="w-6 h-6" />
        {notifications.length > 0 && (
          <span className="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full animate-pulse">
            {notifications.length}
          </span>
        )}
      </button>

      {showDropdown && (
        <div className="absolute right-0 mt-2 w-80 bg-white border rounded shadow-lg z-50">
          <div className="p-2 text-sm font-semibold border-b">Notificaciones</div>
          {notifications.length === 0 ? (
            <div className="p-2 text-sm text-gray-500">Sin notificaciones</div>
          ) : (
            <ul className="max-h-60 overflow-auto">
              {notifications.map((n) => (
                <li
                  key={n.id}
                  className={clsx(
                    'p-3 text-sm transition duration-300 ease-in-out hover:bg-gray-100 border-b',
                    !n.isRead && 'bg-yellow-100 border border-yellow-300 shadow-md',
                    n.isNew && 'animate-glow'
                  )}
                >
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="font-semibold text-gray-800">{n.message}</p>
                      <p className="text-xs text-gray-600">Cliente: {n.clientName}</p>
                    </div>
                    <Link
                      href={n.route}
                      className="text-white bg-blue-500 hover:bg-blue-600 text-xs font-semibold px-3 py-1 rounded"
                      onClick={() => handleMarkAsRead(n.id)}
                    >
                      Ver más
                    </Link>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      <style jsx>{`
        @keyframes glow {
          0% { box-shadow: 0 0 5px rgba(255, 215, 0, 0.3); }
          50% { box-shadow: 0 0 20px rgba(255, 215, 0, 0.8); }
          100% { box-shadow: 0 0 5px rgba(255, 215, 0, 0.3); }
        }
        .animate-glow {
          animation: glow 2s ease-in-out infinite;
        }
      `}</style>
    </div>
  );
}
