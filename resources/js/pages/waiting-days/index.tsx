// ✅ WaitingDays index.tsx con tabla mejor ordenada
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';
import DaysWaitModal from './modal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Días de Espera', href: '/waiting-days' },
];

type WaitingDay = {
  id_dias_espera: number;
  dias: string;
  habilitado: boolean;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

export default function WaitingDays() {
  const { waitingDays: initialPagination } = usePage<{ waitingDays: Pagination<WaitingDay> }>().props;

  const [items, setItems] = useState(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<WaitingDay | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const handleSaved = (saved: WaitingDay) => {
    setItems((prev) => {
      const exists = prev.find((i) => i.id_dias_espera === saved.id_dias_espera);
      return exists ? prev.map((i) => (i.id_dias_espera === saved.id_dias_espera ? saved : i)) : [saved, ...prev];
    });
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    const res = await axios.get(`/waiting-days/${id}`);
    setEditItem(res.data.waitingDay);
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
        <h1 className="text-2xl font-bold mb-6">Días de Espera</h1>

        <div className="flex items-center gap-2 mb-4">
          <button onClick={() => { setEditItem(null); setShowModal(true); }} className="px-4 py-2 bg-blue-600 text-white rounded">
            Nuevo Día de Espera
          </button>

          {selectedIds.length > 0 && (
            <button
              onClick={async () => {
                if (confirm(`¿Eliminar ${selectedIds.length} registros seleccionados?`)) {
                  await axios.post('/waiting-days/bulk-delete', { ids: selectedIds });
                  setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id_dias_espera)));
                  setSelectedIds([]);
                }
              }}
              className="px-4 py-2 bg-red-600 text-white rounded"
            >Eliminar Seleccionados</button>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border rounded">
            <thead className="bg-gray-100 text-left">
              <tr>
                <th className="px-4 py-2"><input type="checkbox" checked={selectedIds.length === items.length} onChange={(e) => setSelectedIds(e.target.checked ? items.map((i) => i.id_dias_espera) : [])} /></th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Días</th>
                <th className="px-4 py-2">Habilitado</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id_dias_espera} className="border-t hover:bg-gray-50">
                  <td className="px-4 py-2"><input type="checkbox" checked={selectedIds.includes(item.id_dias_espera)} onChange={(e) => setSelectedIds((prev) => e.target.checked ? [...prev, item.id_dias_espera] : prev.filter((id) => id !== item.id_dias_espera))} /></td>
                  <td className="px-4 py-2 space-x-2">
                    <button onClick={() => fetchItem(item.id_dias_espera)} className="text-blue-600 hover:underline"><Paintbrush className="w-4 h-4 inline" /> Editar</button>
                    <button onClick={async () => {
                      if (confirm('¿Eliminar este registro?')) {
                        await axios.delete(`/waiting-days/${item.id_dias_espera}`);
                        setItems((prev) => prev.filter((i) => i.id_dias_espera !== item.id_dias_espera));
                      }
                    }} className="text-red-600 hover:underline"><Trash2 className="w-4 h-4 inline" /> Eliminar</button>
                  </td>
                  <td className="px-4 py-2">{item.id_dias_espera}</td>
                  <td className="px-4 py-2">{item.dias}</td>
                  <td className="px-4 py-2">{item.habilitado ? 'Sí' : 'No'}</td>
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
                onClick={() => fetchPage(`/waiting-days/fetch?page=${page}`)}
                className={`px-3 py-1 rounded ${pagination.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}
              >{page}</button>
            );
          })}
        </div>
      </div>

      {showModal && (
        <DaysWaitModal
          open={showModal}
          onClose={() => { setShowModal(false); setEditItem(null); }}
          onSaved={handleSaved}
          itemToEdit={editItem}
        />
      )}
    </AppLayout>
  );
}
