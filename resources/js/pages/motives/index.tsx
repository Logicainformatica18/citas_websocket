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

type AreaMini = { id_area: number; descripcion: string };

type Motive = {
  id_motivos_cita: number;
  nombre_motivo: string;
  detail?: string | null; // ← NUEVO
  detail_2?: string | null; // ← NUEVO
  id_tipo_cita: number | null;
  id_dia_espera: number | null;
  id_area: number;
  habilitado: boolean;
  tipoCita?: { id_tipo_cita?: number; tipo: string } | null;
  diaEspera?: { id_dias_espera?: number; dias: string } | null;
  area?: { id_area?: number; descripcion: string } | null; // área principal (columna id_area)
  areas_pivot?: AreaMini[]; // áreas por tabla pivote N:M
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
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
    areas: AreaMini[];
  }>().props;

  const [items, setItems] = useState<Motive[]>([]);
  const [pagination, setPagination] = useState<Pagination<Motive>>(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<Motive | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const upsertMotive = (saved: Motive) => {
    setItems((prev) => {
      const idx = prev.findIndex((i) => i.id_motivos_cita === saved.id_motivos_cita);
      if (idx >= 0) {
        const next = [...prev];
        next[idx] = saved;
        return next;
      }
      return [saved, ...prev];
    });
  };

  const handleSaved = (saved: Motive) => {
    upsertMotive(saved);
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    try {
      const res = await axios.get(`/motives/${id}`);
      // Espera { motive: {..., areas_pivot: [...], areas_ids: [...] } }
      setEditItem(res?.data?.motive ?? null);
      setShowModal(true);
    } catch (e) {
      console.error('No se pudo cargar el motivo', e);
      alert('No se pudo cargar el motivo');
    }
  };

  const normalizePagePayload = (payload: any): Pagination<Motive> => {
    // Soporta: { data, current_page, last_page } o { motives: {...} }
    const pager = payload?.motives ?? payload ?? {};
    const data: Motive[] = Array.isArray(pager)
      ? pager
      : (pager?.data ?? []);
    return {
      data,
      current_page: pager?.current_page ?? 1,
      last_page: pager?.last_page ?? 1,
      next_page_url: pager?.next_page_url ?? null,
      prev_page_url: pager?.prev_page_url ?? null,
    };
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      const norm = normalizePagePayload(res?.data ?? null);
      setItems(norm.data);
      setPagination(norm);
      setSelectedIds([]); // limpia selección al cambiar de página
    } catch (e) {
      console.error('Error al cargar página', e);
      alert('No se pudo cargar la página.');
    }
  };

  const removeOne = async (id: number, nombre: string) => {
    if (!confirm(`¿Eliminar el motivo "${nombre}"?`)) return;
    try {
      await axios.delete(`/motives/${id}`);
      setItems((prev) => prev.filter((i) => i.id_motivos_cita !== id));
      setSelectedIds((prev) => prev.filter((x) => x !== id));
    } catch (e) {
      console.error('Error al eliminar', e);
      alert('No se pudo eliminar el motivo.');
    }
  };

  const removeBulk = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`¿Eliminar ${selectedIds.length} motivo(s)?`)) return;
    try {
      await axios.post('/motives/bulk-delete', { ids: selectedIds });
      setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id_motivos_cita)));
      setSelectedIds([]);
    } catch (e) {
      console.error('Error al eliminar en lote', e);
      alert('No se pudo eliminar en lote.');
    }
  };

  const renderAreasCell = (item: Motive) => {
    // Área principal + áreas por pivote (sin duplicados)
    const labels: string[] = [];
    if (item.area?.descripcion) labels.push(item.area.descripcion);
    if (Array.isArray(item.areas_pivot)) {
      for (const a of item.areas_pivot) {
        if (a?.descripcion && !labels.includes(a.descripcion)) {
          labels.push(a.descripcion);
        }
      }
    }
    if (labels.length === 0) return '-';
    return (
      <div className="flex flex-wrap gap-1">
        {labels.map((txt, i) => (
          <span
            key={`${txt}-${i}`}
            className="inline-block px-2 py-0.5 text-xs rounded bg-blue-50 text-blue-700 border border-blue-200"
          >
            {txt}
          </span>
        ))}
      </div>
    );
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
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
          >
            Nuevo Motivo
          </button>

          {selectedIds.length > 0 && (
            <button
              onClick={removeBulk}
              className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
            >
              Eliminar Seleccionados
            </button>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border rounded bg-white">
            <thead className="bg-gray-100 text-left">
              <tr>
                <th className="px-4 py-2">
                  <input
                    type="checkbox"
                    checked={items.length > 0 && selectedIds.length === items.length}
                    onChange={(e) =>
                      setSelectedIds(e.target.checked ? items.map((i) => i.id_motivos_cita) : [])
                    }
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Nombre</th>
                {/* <th className="px-4 py-2">Tipo de Cita</th>
                <th className="px-4 py-2">Día Espera</th> */}
                <th className="px-4 py-2">Detalle Call Center</th> {/* ← NUEVO (puedes ocultarlo si no lo quieres ver) */}
                <th className="px-4 py-2">Detalle ATC Interno</th> {/* ← NUEVO (puedes ocultarlo si no lo quieres ver) */}
                <th className="px-4 py-2">Áreas</th>
                <th className="px-4 py-2">¿Habilitado?</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr
                  key={item.id_motivos_cita}
                  className="border-t hover:bg-gray-50 align-top"
                >
                  <td className="px-4 py-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(item.id_motivos_cita)}
                      onChange={(e) =>
                        setSelectedIds((prev) =>
                          e.target.checked
                            ? [...prev, item.id_motivos_cita]
                            : prev.filter((id) => id !== item.id_motivos_cita)
                        )
                      }
                    />
                  </td>
                  <td className="px-4 py-2 space-x-2 whitespace-nowrap">
                    <button
                      onClick={() => fetchItem(item.id_motivos_cita)}
                      className="text-blue-600 hover:underline"
                    >
                      <Paintbrush className="w-4 h-4 inline" /> Editar
                    </button>
                    <button
                      onClick={() => removeOne(item.id_motivos_cita, item.nombre_motivo)}
                      className="text-red-600 hover:underline"
                    >
                      <Trash2 className="w-4 h-4 inline" /> Eliminar
                    </button>
                  </td>
                  <td className="px-4 py-2">{item.id_motivos_cita}</td>
                  <td className="px-4 py-2">{item.nombre_motivo}</td>
                  {/* <td className="px-4 py-2">{item.tipoCita?.tipo ?? '-'}</td>
                  <td className="px-4 py-2">{item.diaEspera?.dias ?? '-'}</td> */}
                  <td className="px-4 py-2">
                    {item.detail
                      ? <span className="block max-w-[32ch] truncate" title={item.detail}>{item.detail}</span>
                      : '-'}
                  </td>
                     <td className="px-4 py-2">
                    {item.detail_2
                      ? <span className="block max-w-[32ch] truncate" title={item.detail_2}>{item.detail_2}</span>
                      : '-'}
                  </td>
                  <td className="px-4 py-2">{renderAreasCell(item)}</td>
                  <td className="px-4 py-2">{item.habilitado ? 'Sí' : 'No'}</td>
                </tr>
              ))}

              {items.length === 0 && (
                <tr>
                  <td className="px-4 py-6 text-center text-gray-500" colSpan={7}>
                    No hay motivos para mostrar.
                  </td>
                </tr>
              )}
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
