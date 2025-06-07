// resources/js/pages/types/index.tsx
import axios from 'axios';
import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Paintbrush, Trash2 } from 'lucide-react';
import TypeModal from './modal';
import HourglassLoader from '@/components/HourglassLoader'; // si ya lo usas en otros módulos

type BreadcrumbItem = {
  title: string;
  href: string;
};

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Tipos', href: '/types' },
];

type TypeItem = {
  id: number;
  description: string;
  detail: string;
  created_at?: string;
  updated_at?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};


export default function Types() {
  const { types: initialPagination } = usePage<{ types: Pagination<TypeItem> }>().props;
  const [items, setItems] = useState<TypeItem[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<TypeItem | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const handleSaved = (saved: TypeItem) => {
    setItems((prev) => {
      const exists = prev.find((i) => i.id === saved.id);
      return exists ? prev.map((i) => (i.id === saved.id ? saved : i)) : [saved, ...prev];
    });
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    try {
      setEditingId(id);
      const res = await axios.get(`/types/${id}`);
      setEditItem(res.data.type);
      setShowModal(true);
    } finally {
      setEditingId(null);
    }
  };

  const fetchPage = async (url: string) => {
    const res = await axios.get(url);
    setItems(res.data.data);
    setPagination(res.data);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-6">Tipos</h1>

        <div className="flex items-center gap-2 mb-4">
          <button
            onClick={() => {
              setEditItem(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-blue-600 text-white rounded"
          >
            Nuevo Tipo
          </button>

          {selectedIds.length > 0 && (
            <button
              onClick={async () => {
                if (confirm(`¿Eliminar ${selectedIds.length} tipo(s)?`)) {
                  await axios.post('/types/bulk-delete', { ids: selectedIds });
                  setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id)));
                  setSelectedIds([]);
                }
              }}
              className="px-4 py-2 bg-red-600 text-white rounded"
            >
              Eliminar Seleccionados
            </button>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border rounded">
            <thead className="bg-gray-100 text-left">
              <tr>
                <th className="px-4 py-2">
                  <input
                    type="checkbox"
                    checked={selectedIds.length === items.length}
                    onChange={(e) => setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])}
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Descripción</th>
                <th className="px-4 py-2">Detalle</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-t hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(item.id)}
                      onChange={(e) => setSelectedIds((prev) =>
                        e.target.checked ? [...prev, item.id] : prev.filter((id) => id !== item.id)
                      )}
                    />
                  </td>
                  <td className="px-4 py-2 space-x-2">
                    <button
                      onClick={() => fetchItem(item.id)}
                      disabled={editingId === item.id}
                      className="text-blue-600 hover:underline"
                    >
                      {editingId === item.id ? (
                        <HourglassLoader />
                      ) : (
                        <>
                          <Paintbrush className="w-4 h-4 inline" /> Editar
                        </>
                      )}
                    </button>

                    <button
                      onClick={async () => {
                        if (confirm('¿Eliminar este tipo?')) {
                          try {
                            setDeletingId(item.id);
                            await axios.delete(`/types/${item.id}`);
                            setItems((prev) => prev.filter((i) => i.id !== item.id));
                          } finally {
                            setDeletingId(null);
                          }
                        }
                      }}
                      disabled={deletingId === item.id}
                      className="text-red-600 hover:underline"
                    >
                      {deletingId === item.id ? (
                        <HourglassLoader />
                      ) : (
                        <>
                          <Trash2 className="w-4 h-4 inline" /> Eliminar
                        </>
                      )}
                    </button>
                  </td>
                  <td className="px-4 py-2">{item.id}</td>
                  <td className="px-4 py-2">{item.description}</td>
                  <td className="px-4 py-2">{item.detail}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex justify-center mt-6 gap-2">
          {[...Array(pagination.last_page)].map((_, index) => {
            const page = index + 1;
            return (
              <button
                key={page}
                onClick={() => fetchPage(`/types/fetch?page=${page}`)}
                className={`px-3 py-1 rounded ${pagination.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}
              >
                {page}
              </button>
            );
          })}
        </div>
      </div>

      {showModal && (
        <TypeModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditItem(null);
          }}
          onSaved={handleSaved}
          itemToEdit={editItem}
        />
      )}
    </AppLayout>
  );
}
