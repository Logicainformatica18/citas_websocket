import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';
import CourseModal from './modal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Cursos', href: '/courses' },
];

type SimpleItem = { id: number; name: string };

type Course = {
  id: number;
  name: string;
  languages: SimpleItem[];
  technologies: SimpleItem[];
  methodologies: SimpleItem[];
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function Courses() {
  const {
    courses: initialPagination,
    languages,
    technologies,
    methodologies,
  } = usePage<{
    courses: Pagination<Course>;
    languages: SimpleItem[];
    technologies: SimpleItem[];
    methodologies: SimpleItem[];
  }>().props;

  const [items, setItems] = useState<Course[]>([]);
  const [pagination, setPagination] = useState<Pagination<Course>>(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<Course | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const upsertCourse = (saved: Course) => {
    setItems((prev) => {
      const idx = prev.findIndex((i) => i.id === saved.id);
      if (idx >= 0) {
        const next = [...prev];
        next[idx] = saved;
        return next;
      }
      return [saved, ...prev];
    });
  };

  const handleSaved = (saved: Course) => {
    upsertCourse(saved);
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    try {
      const res = await axios.get(`/courses/${id}`);
      setEditItem(res?.data?.course ?? null);
      setShowModal(true);
    } catch (e) {
      console.error('No se pudo cargar el curso', e);
      alert('No se pudo cargar el curso');
    }
  };

  const normalizePagePayload = (payload: any): Pagination<Course> => {
    const pager = payload?.courses ?? payload ?? {};
    const data: Course[] = Array.isArray(pager) ? pager : (pager?.data ?? []);
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
    if (!confirm(`¿Eliminar el curso "${name}"?`)) return;
    try {
      await axios.delete(`/courses/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      setSelectedIds((prev) => prev.filter((x) => x !== id));
    } catch (e) {
      console.error('Error al eliminar', e);
      alert('No se pudo eliminar el curso.');
    }
  };

  const removeBulk = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`¿Eliminar ${selectedIds.length} curso(s)?`)) return;
    try {
      await axios.post('/courses/bulk-delete', { ids: selectedIds });
      setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id)));
      setSelectedIds([]);
    } catch (e) {
      console.error('Error al eliminar en lote', e);
      alert('No se pudo eliminar en lote.');
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
     <div className="p-8">
  <div className="flex items-center justify-between mb-6">
    <h1 className="text-2xl font-bold">Cursos</h1>

    <div className="flex gap-2">
      <button
        onClick={() => {
          setEditItem(null);
          setShowModal(true);
        }}
        className="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition"
      >
        Mantenemiento Cursos ISIL
      </button>

      {selectedIds.length > 0 && (
        <button
          onClick={removeBulk}
          className="px-4 py-2 bg-red-600 text-white rounded-lg shadow hover:bg-red-700 transition"
        >
          Eliminar Seleccionados
        </button>
      )}
    </div>
  </div>

  {/* Tabla dentro de card */}
  <div className="bg-white shadow rounded-lg overflow-hidden">
    <table className="min-w-full table-auto">
      <thead className="bg-gray-200 text-gray-700">
        <tr>
          <th className="px-4 py-2">
            <input
              type="checkbox"
              checked={items.length > 0 && items.every(i => selectedIds.includes(i.id))}
              onChange={(e) =>
                setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])
              }
            />
          </th>
          <th className="px-4 py-2">Acciones</th>
          <th className="px-4 py-2">ID</th>
          <th className="px-4 py-2">Nombre</th>
          <th className="px-4 py-2">Lenguajes</th>
          <th className="px-4 py-2">Tecnologías</th>
          <th className="px-4 py-2">Metodologías</th>
        </tr>
      </thead>
     <tbody>
  {items.map((item) => (
    <tr key={item.id} className="border-t hover:bg-gray-50">
      {/* Checkbox */}
      <td className="px-4 py-2">
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

      {/* Acciones */}
      <td className="px-4 py-2 space-x-2 whitespace-nowrap text-gray-700">
        <button
          onClick={() => fetchItem(item.id)}
          className="text-blue-600 hover:underline inline-flex items-center gap-1"
        >
          <Paintbrush className="w-4 h-4" /> Editar
        </button>
        <button
          onClick={() => removeOne(item.id, item.name)}
          className="text-red-600 hover:underline inline-flex items-center gap-1"
        >
          <Trash2 className="w-4 h-4" /> Eliminar
        </button>
      </td>

      {/* ID */}
      <td className="px-4 py-2 text-gray-600">{item.id}</td>

      {/* Nombre */}
      <td className="px-4 py-2 font-semibold text-gray-900">{item.name}</td>

      {/* Lenguajes */}
      <td className="px-4 py-2 text-gray-700">
        {item.languages.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {item.languages.map((l) => (
              <span
                key={l.id}
                className="px-2 py-0.5 text-xs rounded bg-green-100 text-green-800 border border-green-200"
              >
                {l.name}
              </span>
            ))}
          </div>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </td>

      {/* Tecnologías */}
      <td className="px-4 py-2 text-gray-700">
        {item.technologies.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {item.technologies.map((t) => (
              <span
                key={t.id}
                className="px-2 py-0.5 text-xs rounded bg-blue-100 text-blue-800 border border-blue-200"
              >
                {t.name}
              </span>
            ))}
          </div>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </td>

      {/* Metodologías */}
      <td className="px-4 py-2 text-gray-700">
        {item.methodologies.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {item.methodologies.map((m) => (
              <span
                key={m.id}
                className="px-2 py-0.5 text-xs rounded bg-purple-100 text-purple-800 border border-purple-200"
              >
                {m.name}
              </span>
            ))}
          </div>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </td>
    </tr>
  ))}

  {/* Estado vacío */}
  {items.length === 0 && (
    <tr>
      <td
        className="px-4 py-10 text-center text-gray-500 text-sm"
        colSpan={7}
      >
        No hay cursos para mostrar.
      </td>
    </tr>
  )}
</tbody>

    </table>
  </div>

  {/* Paginación */}
  <div className="flex justify-center mt-6 gap-2">
    {[...Array(pagination.last_page)].map((_, index) => {
      const page = index + 1;
      return (
        <button
          key={page}
          onClick={() => fetchPage(`/courses/fetch?page=${page}`)}
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
        <CourseModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditItem(null);
          }}
          onSaved={handleSaved}
          itemToEdit={editItem}
          languages={languages}
          technologies={technologies}
          methodologies={methodologies}
        />
      )}
    </AppLayout>
  );
}
