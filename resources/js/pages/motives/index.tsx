// ✅ motives/index.tsx completo y adaptado con relaciones garantizadas
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';
import MotiveModal from './modal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Motivos de Cita', href: '/motives' },
];

type Motive = {
  id_motivos_cita: number;
  nombre_motivo: string;
  id_tipo_cita: number | null;
  id_dia_espera: number | null;
  id_area: number;
  habilitado: boolean;
  tipoCita?: { tipo: string } | null;
  diaEspera?: { dias: string } | null;
  area?: { descripcion: string } | null;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

export default function Motives() {
  const {
    motives: initialPagination,
    appointmentTypes,
    waitingDays,
    areas,
  } = usePage<{
    motives: Pagination<Motive>;
    appointmentTypes: any[];
    waitingDays: any[];
    areas: any[];
  }>().props;

  const [items, setItems] = useState<Motive[]>([]);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<Motive | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  useEffect(() => {
    setItems(initialPagination.data);
  }, [initialPagination]);

  const handleSaved = (saved: Motive) => {
    setItems((prev) => {
      const exists = prev.find((i) => i.id_motivos_cita === saved.id_motivos_cita);
      return exists ? prev.map((i) => (i.id_motivos_cita === saved.id_motivos_cita ? saved : i)) : [saved, ...prev];
    });
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    const res = await axios.get(`/motives/${id}`);
    setEditItem(res.data.motive);
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
        <h1 className="text-2xl font-bold mb-6">Motivos de Cita</h1>

        <div className="flex items-center gap-2 mb-4">
          <button
            onClick={() => {
              setEditItem(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-blue-600 text-white rounded"
          >
            Nuevo Motivo
          </button>

          {selectedIds.length > 0 && (
            <button
              onClick={async () => {
                if (confirm(`¿Eliminar ${selectedIds.length} motivo(s)?`)) {
                  await axios.post('/motives/bulk-delete', { ids: selectedIds });
                  setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id_motivos_cita)));
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
                    onChange={(e) => setSelectedIds(e.target.checked ? items.map((i) => i.id_motivos_cita) : [])}
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">Tipo de Cita</th>
                <th className="px-4 py-2">Día Espera</th>
                <th className="px-4 py-2">Área</th>
                <th className="px-4 py-2">¿Habilitado?</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id_motivos_cita} className="border-t hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(item.id_motivos_cita)}
                      onChange={(e) => setSelectedIds((prev) =>
                        e.target.checked ? [...prev, item.id_motivos_cita] : prev.filter((id) => id !== item.id_motivos_cita)
                      )}
                    />
                  </td>
                  <td className="px-4 py-2 space-x-2">
                    <button onClick={() => fetchItem(item.id_motivos_cita)} className="text-blue-600 hover:underline">
                      <Paintbrush className="w-4 h-4 inline" /> Editar
                    </button>
                    <button
                      onClick={async () => {
                        if (confirm('¿Eliminar este motivo?')) {
                          await axios.delete(`/motives/${item.id_motivos_cita}`);
                          setItems((prev) => prev.filter((i) => i.id_motivos_cita !== item.id_motivos_cita));
                        }
                      }}
                      className="text-red-600 hover:underline"
                    >
                      <Trash2 className="w-4 h-4 inline" /> Eliminar
                    </button>
                  </td>
                  <td className="px-4 py-2">{item.id_motivos_cita}</td>
                  <td className="px-4 py-2">{item.nombre_motivo}</td>
                  <td className="px-4 py-2">{item.tipoCita?.tipo ?? '-'}</td>
                  <td className="px-4 py-2">{item.diaEspera?.dias ?? '-'}</td>
                  <td className="px-4 py-2">{item.area?.descripcion ?? '-'}</td>
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
                onClick={() => fetchPage(`/motives/fetch?page=${page}`)}
                className={`px-3 py-1 rounded ${pagination.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}
              >
                {page}
              </button>
            );
          })}
        </div>
      </div>

      {showModal && (
        <MotiveModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditItem(null);
          }}
          onSaved={handleSaved}
          itemToEdit={editItem}
          appointmentTypes={appointmentTypes}
          waitingDays={waitingDays}
          areas={areas}
        />
      )}
    </AppLayout>
  );
}
