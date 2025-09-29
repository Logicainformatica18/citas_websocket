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

export default function CoursesIndex() {
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
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<Course | null>(null);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      const pager = res.data?.courses ?? res.data;
      setItems(pager?.data ?? []);
      setPagination({
        data: pager?.data ?? [],
        current_page: pager?.current_page ?? 1,
        last_page: pager?.last_page ?? 1,
        next_page_url: pager?.next_page_url ?? null,
        prev_page_url: pager?.prev_page_url ?? null,
      });
      setSelectedIds([]);
    } catch (e) {
      alert('No se pudo cargar la página.');
    }
  };

  const fetchItem = async (id: number) => {
    try {
      const res = await axios.get(`/courses/${id}`);
      setEditItem(res?.data?.course ?? null);
      setShowModal(true);
    } catch {
      alert('No se pudo cargar el curso.');
    }
  };

  const removeOne = async (id: number, name: string) => {
    if (!confirm(`¿Eliminar el curso "${name}"?`)) return;
    try {
      await axios.delete(`/courses/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      setSelectedIds((prev) => prev.filter((x) => x !== id));
    } catch {
      alert('No se pudo eliminar.');
    }
  };

  const removeBulk = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`¿Eliminar ${selectedIds.length} curso(s)?`)) return;
    try {
      await axios.post('/courses/bulk-delete', { ids: selectedIds });
      setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id)));
      setSelectedIds([]);
    } catch {
      alert('No se pudo eliminar en lote.');
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-6">Gestión de Cursos</h1>

        {/* Botones */}
        <div className="flex gap-2 mb-6">
          <button
            onClick={() => {
              setEditItem(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
          >
            Mantenimiento Cursos ISIL
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

        {/* Tabla */}
        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200">
            <thead className="bg-gray-100 dark:bg-gray-800 text-left text-gray-700 dark:text-gray-300">
              <tr>
                <th className="px-4 py-2">
                  <input
                    type="checkbox"
                    checked={
                      items.length > 0 &&
                      items.every((i) => selectedIds.includes(i.id))
                    }
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
                <tr
                  key={item.id}
                  className="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 align-top"
                >
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
                  <td className="px-4 py-2 whitespace-nowrap">
                    <button
                      onClick={() => fetchItem(item.id)}
                      className="text-blue-500 hover:text-blue-400 inline-flex items-center gap-1 text-sm"
                    >
                      <Paintbrush className="w-4 h-4" /> Editar
                    </button>
                    <button
                      onClick={() => removeOne(item.id, item.name)}
                      className="ml-2 text-red-500 hover:text-red-400 inline-flex items-center gap-1 text-sm"
                    >
                      <Trash2 className="w-4 h-4" /> Eliminar
                    </button>
                  </td>
                  <td className="px-4 py-2">{item.id}</td>
                  <td className="px-4 py-2 font-semibold">{item.name}</td>
                  <td className="px-4 py-2">
                    {item.languages.length ? (
                      <div className="flex flex-wrap gap-1">
                        {item.languages.map((l) => (
                          <span
                            key={l.id}
                            className="px-2 py-0.5 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded text-xs"
                          >
                            {l.name}
                          </span>
                        ))}
                      </div>
                    ) : (
                      '-'
                    )}
                  </td>
                  <td className="px-4 py-2">
                    {item.technologies.length ? (
                      <div className="flex flex-wrap gap-1">
                        {item.technologies.map((t) => (
                          <span
                            key={t.id}
                            className="px-2 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded text-xs"
                          >
                            {t.name}
                          </span>
                        ))}
                      </div>
                    ) : (
                      '-'
                    )}
                  </td>
                  <td className="px-4 py-2">
                    {item.methodologies.length ? (
                      <div className="flex flex-wrap gap-1">
                        {item.methodologies.map((m) => (
                          <span
                            key={m.id}
                            className="px-2 py-0.5 bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded text-xs"
                          >
                            {m.name}
                          </span>
                        ))}
                      </div>
                    ) : (
                      '-'
                    )}
                  </td>
                </tr>
              ))}
              {items.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                    No hay cursos para mostrar.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Paginación */}
        {pagination.last_page > 1 && (
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
                      : 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                  }`}
                  disabled={pagination.current_page === page}
                >
                  {page}
                </button>
              );
            })}
          </div>
        )}
      </div>

      {showModal && (
        <CourseModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditItem(null);
          }}
          onSaved={(saved) => {
            const idx = items.findIndex((i) => i.id === saved.id);
            if (idx >= 0) {
              const next = [...items];
              next[idx] = saved;
              setItems(next);
            } else {
              setItems([saved, ...items]);
            }
          }}
          itemToEdit={editItem}
          languages={languages}
          technologies={technologies}
          methodologies={methodologies}
        />
      )}
    </AppLayout>
  );
}
