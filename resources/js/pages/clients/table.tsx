import { Paintbrush, Trash2 } from 'lucide-react';
import HourglassLoader from '@/components/HourglassLoader';
import { useState } from 'react';
import axios from 'axios';

interface Client {
  id_cliente: number;
  Codigo: string;
  Razon_Social: string;
  DNI: string;
  Email: string;
  Telefono: string;
  Direccion: string;
  canal: string;
  habilitado: number;
}

interface Pagination<T> {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
}

interface Props {
  clients: Client[];
  setClients: React.Dispatch<React.SetStateAction<Client[]>>;
  pagination: Pagination<Client>;
  fetchPage: (url: string) => Promise<void>;
  fetchClient: (id: number) => Promise<void>;
}

export default function ClientTable({
  clients,
  setClients,
  pagination,
  fetchPage,
  fetchClient,
}: Props) {
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  return (
    <>
      {selectedIds.length > 0 && (
        <button
          onClick={async () => {
            if (confirm(`¿Eliminar ${selectedIds.length} clientes?`)) {
              try {
                await axios.post('/clients/bulk-delete', { ids: selectedIds });
                setClients((prev) => prev.filter((c) => !selectedIds.includes(c.id_cliente)));
                setSelectedIds([]);
              } catch (e) {
                alert('Error al eliminar en Lote');
                console.error(e);
              }
            }
          }}
          className="mb-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
        >
          Eliminar seleccionados
        </button>
      )}

      <div className="overflow-x-auto mt-4">
        <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
          <thead className="bg-gray-100 dark:bg-gray-800">
            <tr>
              <th className="px-4 py-2">
                <input
                  type="checkbox"
                  checked={selectedIds.length === clients.length}
                  onChange={(e) =>
                    setSelectedIds(e.target.checked ? clients.map((c) => c.id_cliente) : [])
                  }
                />
              </th>
              <th className="px-4 py-2">Acciones</th>
              <th className="px-4 py-2">ID</th>
              <th className="px-4 py-2">Código</th>
              <th className="px-4 py-2">Razón Social</th>
              <th className="px-4 py-2">DNI</th>
              <th className="px-4 py-2">Email</th>
              <th className="px-4 py-2">Teléfono</th>
              <th className="px-4 py-2">Dirección</th>
              <th className="px-4 py-2">Canal</th>
              <th className="px-4 py-2">Habilitado</th>
            </tr>
          </thead>
          <tbody>
            {clients.map((client) => (
              <tr key={client.id_cliente} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700">
                <td className="px-4 py-2">
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(client.id_cliente)}
                    onChange={(e) =>
                      setSelectedIds((prev) =>
                        e.target.checked
                          ? [...prev, client.id_cliente]
                          : prev.filter((id) => id !== client.id_cliente)
                      )
                    }
                  />
                </td>
                <td className="px-4 py-2 text-sm space-x-2">
                  <button
                    onClick={async () => {
                      setEditingId(client.id_cliente);
                      await fetchClient(client.id_cliente);
                      setEditingId(null);
                    }}
                    disabled={editingId === client.id_cliente}
                    className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                  >
                    {editingId === client.id_cliente ? (
                      <HourglassLoader />
                    ) : (
                      <>
                        <Paintbrush className="w-4 h-4" /> Editar
                      </>
                    )}
                  </button>
                  <button
                    onClick={async () => {
                      if (confirm(`¿Eliminar cliente "${client.Razon_Social}"?`)) {
                        try {
                          setDeletingId(client.id_cliente);
                          await axios.delete(`/clients/${client.id_cliente}`);
                          setClients((prev) => prev.filter((c) => c.id_cliente !== client.id_cliente));
                        } catch (e) {
                          alert('Error al eliminar');
                          console.error(e);
                        } finally {
                          setDeletingId(null);
                        }
                      }
                    }}
                    disabled={deletingId === client.id_cliente}
                    className="text-red-600 hover:underline dark:text-red-400 flex items-center gap-1"
                  >
                    {deletingId === client.id_cliente ? (
                      <HourglassLoader />
                    ) : (
                      <>
                        <Trash2 className="w-4 h-4" /> Eliminar
                      </>
                    )}
                  </button>
                </td>
                <td className="px-4 py-2">{client.id_cliente}</td>
                <td className="px-4 py-2">{client.Codigo}</td>
                <td className="px-4 py-2">{client.Razon_Social}</td>
                <td className="px-4 py-2">{client.DNI}</td>
                <td className="px-4 py-2">{client.Email}</td>
                <td className="px-4 py-2">{client.Telefono}</td>
                <td className="px-4 py-2">{client.Direccion}</td>
                <td className="px-4 py-2">{client.canal}</td>
                <td className="px-4 py-2">{client.habilitado ? 'Sí' : 'No'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex justify-center mt-6 space-x-2">
        {[...Array(pagination.last_page)].map((_, index) => {
          const page = index + 1;
          return (
            <button
              key={page}
              onClick={() => fetchPage(`/clients/fetch?page=${page}`)}
              className={`px-3 py-1 rounded text-sm font-medium transition ${
                pagination.current_page === page
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
              }`}
              disabled={pagination.current_page === page}
            >
              {page}
            </button>
          );
        })}
      </div>
    </>
  );
}
