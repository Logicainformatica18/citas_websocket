import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Trash2, Plus, Link2, GraduationCap } from 'lucide-react';
import CareerModal from './CareerModal';
import CareerCoursesModal from './CareerCoursesModal';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Carreras Profesionales', href: '/careers' }];

// 📅 Helper de fecha
function formatDate(dateString?: string | null): string {
  if (!dateString) return '-';
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('es-PE', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  } catch {
    return '-';
  }
}

type Career = {
  id: number;
  name: string;
  faculty?: string | null;
  degree_title?: string | null;
  duration_years?: number | null;
  active: boolean;
  courses_count?: number;
  created_at?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function CareersIndex() {
  const { careers: initialPagination } = usePage<{ careers: Pagination<Career> }>().props;

  const [items, setItems] = useState<Career[]>([]);
  const [pagination, setPagination] = useState<Pagination<Career>>(initialPagination);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showModal, setShowModal] = useState(false);
  const [showCoursesModal, setShowCoursesModal] = useState(false);
  const [selectedCareerId, setSelectedCareerId] = useState<number | null>(null);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const normalizePagePayload = (payload: any): Pagination<Career> => {
    const pager = payload?.careers ?? payload ?? {};
    const data: Career[] = Array.isArray(pager) ? pager : (pager?.data ?? []);
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
      setSelectedIds([]);
    } catch (e) {
      console.error('Error al cargar página', e);
      alert('No se pudo cargar la página.');
    }
  };

  const removeOne = async (id: number, name: string) => {
    if (!confirm(`¿Eliminar la carrera "${name}"?`)) return;
    try {
      await axios.delete(`/careers/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      setSelectedIds((prev) => prev.filter((x) => x !== id));
    } catch (e) {
      console.error('Error al eliminar', e);
      alert('No se pudo eliminar la carrera.');
    }
  };

  const removeBulk = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`¿Eliminar ${selectedIds.length} carrera(s)?`)) return;
    try {
      await axios.post('/careers/bulk-delete', { ids: selectedIds });
      setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id)));
      setSelectedIds([]);
    } catch (e) {
      console.error('Error al eliminar en lote', e);
      alert('No se pudo eliminar en lote.');
    }
  };

  const openSyncModal = (careerId: number) => {
    setSelectedCareerId(careerId);
    setShowCoursesModal(true);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">
        {/* Header */}
        <div className="flex items-center justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
          <h1 className="text-3xl font-semibold flex items-center gap-2">
            <GraduationCap className="w-7 h-7 text-blue-600 dark:text-blue-400" />
            Carreras Profesionales
          </h1>

          <div className="flex items-center gap-3">
            {selectedIds.length > 0 && (
              <button
                onClick={removeBulk}
                className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md shadow transition"
              >
                Eliminar Seleccionadas ({selectedIds.length})
              </button>
            )}
            <button
              onClick={() => setShowModal(true)}
              className="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-md shadow-md flex items-center gap-2 transition"
            >
              <Plus className="w-4 h-4" /> Nueva Carrera
            </button>
          </div>
        </div>

        {/* Tabla */}
        <div className="overflow-x-auto rounded-lg shadow border border-gray-200 dark:border-gray-800">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm uppercase">
              <tr>
                <th className="px-4 py-2 text-center">
                  <input
                    type="checkbox"
                    checked={items.length > 0 && items.every(i => selectedIds.includes(i.id))}
                    onChange={(e) =>
                      setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])
                    }
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">Facultad</th>
                <th className="px-4 py-2">Título</th>
                <th className="px-4 py-2">Duración</th>
                <th className="px-4 py-2 text-center">Cursos</th>
                <th className="px-4 py-2 text-center">Estado</th>
                <th className="px-4 py-2">Creada</th>
              </tr>
            </thead>
            <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
              {items.map((item) => (
                <tr
                  key={item.id}
                  className="hover:bg-gray-50 dark:hover:bg-gray-800/60 transition-colors"
                >
                  <td className="px-4 py-2 text-center">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(item.id)}
                      onChange={(e) =>
                        setSelectedIds((prev) =>
                          e.target.checked
                            ? [...prev, item.id]
                            : prev.filter((id) => id !== item.id)
                        )
                      }
                    />
                  </td>
                  <td className="px-4 py-2 whitespace-nowrap flex flex-wrap gap-2">
                    <button
                      onClick={() => openSyncModal(item.id)}
                      className="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 inline-flex items-center gap-1 text-sm font-medium"
                    >
                      <Link2 className="w-4 h-4" /> Sincronizar
                    </button>
                    <button
                      onClick={() => removeOne(item.id, item.name)}
                      className="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 inline-flex items-center gap-1 text-sm font-medium"
                    >
                      <Trash2 className="w-4 h-4" /> Eliminar
                    </button>
                  </td>
                  <td className="px-4 py-2 font-semibold">{item.name}</td>
                  <td className="px-4 py-2">{item.faculty ?? '-'}</td>
                  <td className="px-4 py-2">{item.degree_title ?? '-'}</td>
                  <td className="px-4 py-2 text-center">{item.duration_years ?? '-'}</td>
                  <td className="px-4 py-2 text-center">{item.courses_count ?? 0}</td>
                  <td className="px-4 py-2 text-center">
                    {item.active ? (
                      <span className="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                        Activa
                      </span>
                    ) : (
                      <span className="px-2 py-1 text-xs rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                        Inactiva
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-2">{formatDate(item.created_at)}</td>
                </tr>
              ))}

              {items.length === 0 && (
                <tr>
                  <td className="px-4 py-6 text-center text-gray-500 dark:text-gray-400" colSpan={9}>
                    No hay carreras registradas.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Paginación */}
        <div className="flex justify-center mt-6 gap-1">
          {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
            .filter(
              (page) =>
                page <= 2 ||
                page >= pagination.last_page - 1 ||
                (page >= pagination.current_page - 2 &&
                  page <= pagination.current_page + 2)
            )
            .map((page, idx, arr) => {
              const prev = arr[idx - 1];
              const isGap = prev && page - prev > 1;
              return (
                <span key={page} className="flex">
                  {isGap && <span className="px-2 py-1 text-gray-400 dark:text-gray-500">…</span>}
                  <button
                    onClick={() => fetchPage(`/careers/fetch?page=${page}`)}
                    className={`px-3 py-1 rounded-md text-sm font-medium transition ${
                      pagination.current_page === page
                        ? 'bg-blue-600 text-white shadow'
                        : 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600'
                    }`}
                    disabled={pagination.current_page === page}
                  >
                    {page}
                  </button>
                </span>
              );
            })}
        </div>
      </div>

      {/* Modales */}
      {showModal && (
        <CareerModal
          open={showModal}
          onClose={() => setShowModal(false)}
          onCreated={() => fetchPage('/careers/fetch')}
        />
      )}

      {showCoursesModal && selectedCareerId && (
        <CareerCoursesModal
          open={showCoursesModal}
          onClose={() => setShowCoursesModal(false)}
          careerId={selectedCareerId}
          onSynced={() => fetchPage('/careers/fetch')}
        />
      )}
    </AppLayout>
  );
}
