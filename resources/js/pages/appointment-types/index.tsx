// ✅ AppointmentTypes index.tsx con tabla responsiva y adaptada
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';
import AppointmentTypeModal from './modal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Tipos de Cita', href: '/appointment-types' },
];

type AppointmentType = {
  id_tipo_cita: number;
  tipo: string;
  habilitado: boolean;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

export default function AppointmentTypes() {
  const { appointmentTypes: initialPagination } = usePage<{ appointmentTypes: Pagination<AppointmentType> }>().props;
  const [items, setItems] = useState(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<AppointmentType | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const handleSaved = (saved: AppointmentType) => {
    setItems((prev) => {
      const exists = prev.find((p) => p.id_tipo_cita === saved.id_tipo_cita);
      return exists ? prev.map((p) => (p.id_tipo_cita === saved.id_tipo_cita ? saved : p)) : [saved, ...prev];
    });
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    const res = await axios.get(`/appointment-types/${id}`);
    setEditItem(res.data.appointmentType);
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
        <h1 className="text-2xl font-bold mb-4">Tipos de Cita</h1>
        <div className="flex flex-wrap gap-2 mb-4">
          <button onClick={() => { setEditItem(null); setShowModal(true); }} className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Nuevo</button>
          {selectedIds.length > 0 && (
            <button
              onClick={async () => {
                if (confirm(`¿Eliminar ${selectedIds.length} tipo(s)?`)) {
                  await axios.post('/appointment-types/bulk-delete', { ids: selectedIds });
                  setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id_tipo_cita)));
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
                  <input type="checkbox" checked={selectedIds.length === items.length} onChange={(e) => setSelectedIds(e.target.checked ? items.map((i) => i.id_tipo_cita) : [])} />
                </th>
                <th className="px-4 py-2 text-left">Acciones</th>
                <th className="px-4 py-2 text-left">ID</th>
                <th className="px-4 py-2 text-left">Tipo</th>
                <th className="px-4 py-2 text-left">¿Habilitado?</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id_tipo_cita} className="border-t hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <input type="checkbox" checked={selectedIds.includes(item.id_tipo_cita)} onChange={(e) => setSelectedIds((prev) => e.target.checked ? [...prev, item.id_tipo_cita] : prev.filter((id) => id !== item.id_tipo_cita))} />
                  </td>
                  <td className="px-4 py-2 space-x-2">
                    <button onClick={() => fetchItem(item.id_tipo_cita)} className="text-blue-600 hover:underline"><Paintbrush className="w-4 h-4 inline" /> Editar</button>
                    <button onClick={async () => {
                      if (confirm('¿Eliminar este tipo?')) {
                        await axios.delete(`/appointment-types/${item.id_tipo_cita}`);
                        setItems((prev) => prev.filter((i) => i.id_tipo_cita !== item.id_tipo_cita));
                      }
                    }} className="text-red-600 hover:underline"><Trash2 className="w-4 h-4 inline" /> Eliminar</button>
                  </td>
                  <td className="px-4 py-2">{item.id_tipo_cita}</td>
                  <td className="px-4 py-2">{item.tipo}</td>
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
                onClick={() => fetchPage(`/appointment-types/fetch?page=${page}`)}
                className={`px-3 py-1 rounded text-sm font-medium transition ${pagination.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'}`}
              >{page}</button>
            );
          })}
        </div>
      </div>

      {showModal && (
        <AppointmentTypeModal
          open={showModal}
          onClose={() => { setShowModal(false); setEditItem(null); }}
          onSaved={handleSaved}
          itemToEdit={editItem}
        />
      )}
    </AppLayout>
  );
}