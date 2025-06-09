// ✅ ExternalStates index.tsx con tabla más estética y responsiva
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';
import ExternalStateModal from './modal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Estado de Atención', href: '/external-states' },
];

type ExternalState = {
  id: number;
  description: string;
  detail: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

export default function ExternalStates() {
  const { externalStates: initialPagination } = usePage<{ externalStates: Pagination<ExternalState> }>().props;
  const [items, setItems] = useState(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<ExternalState | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const handleSaved = (saved: ExternalState) => {
    setItems((prev) => {
      const exists = prev.find((p) => p.id === saved.id);
      return exists ? prev.map((p) => (p.id === saved.id ? saved : p)) : [saved, ...prev];
    });
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    const res = await axios.get(`/external-states/${id}`);
    setEditItem(res.data.externalState);
    setShowModal(true);
  };

  const fetchPage = async (url: string) => {
    const res = await axios.get(url);
    setItems(res.data.data);
    setPagination(res.data);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">Estado de Atención</h1>
        <div className="flex flex-wrap gap-2 mb-4">
          <button onClick={() => { setEditItem(null); setShowModal(true); }} className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Nuevo</button>
          {selectedIds.length > 0 && (
            <button
              onClick={async () => {
                if (confirm(`¿Eliminar ${selectedIds.length} elemento(s)?`)) {
                  await axios.post('/external-states/bulk-delete', { ids: selectedIds });
                  setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id)));
                  setSelectedIds([]);
                }
              }}
              className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
            >Eliminar seleccionados</button>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full bg-white border rounded shadow-sm">
            <thead className="bg-gray-100 text-gray-700">
              <tr>
                <th className="px-4 py-2 text-left">
                  <input type="checkbox" checked={selectedIds.length === items.length} onChange={(e) => setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])} />
                </th>
                <th className="px-4 py-2 text-left">Acciones</th>
                <th className="px-4 py-2 text-left">ID</th>
                <th className="px-4 py-2 text-left">Descripción</th>
                <th className="px-4 py-2 text-left">Detalle</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-t hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <input type="checkbox" checked={selectedIds.includes(item.id)} onChange={(e) => setSelectedIds((prev) => e.target.checked ? [...prev, item.id] : prev.filter((id) => id !== item.id))} />
                  </td>
                  <td className="px-4 py-2 space-x-2">
                    <button onClick={() => fetchItem(item.id)} className="text-blue-600 hover:underline"><Paintbrush className="w-4 h-4 inline" /> Editar</button>
                    <button onClick={async () => {
                      if (confirm('¿Eliminar este elemento?')) {
                        await axios.delete(`/external-states/${item.id}`);
                        setItems((prev) => prev.filter((i) => i.id !== item.id));
                      }
                    }} className="text-red-600 hover:underline"><Trash2 className="w-4 h-4 inline" /> Eliminar</button>
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
                onClick={() => fetchPage(`/external-states/fetch?page=${page}`)}
                className={`px-3 py-1 rounded text-sm font-medium transition ${pagination.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'}`}
              >{page}</button>
            );
          })}
        </div>
      </div>

      {showModal && (
        <ExternalStateModal
          open={showModal}
          onClose={() => { setShowModal(false); setEditItem(null); }}
          onSaved={handleSaved}
          itemToEdit={editItem}
        />
      )}
    </AppLayout>
  );
}
