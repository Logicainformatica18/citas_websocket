import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2, Search, BookOpen } from 'lucide-react';
import CourseModal from './modal';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cursos', href: '/courses' }];

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
  const [searchTerm, setSearchTerm] = useState('');
  const [typingTimeout, setTypingTimeout] = useState<NodeJS.Timeout | null>(null);

  // 🔍 Búsqueda en tiempo real con delay
  useEffect(() => {
    if (typingTimeout) clearTimeout(typingTimeout);

    const timeout = setTimeout(async () => {
      if (searchTerm.trim() === '') {
        fetchPage('/courses');
        return;
      }

      try {
        const res = await axios.get('/courses/search', { params: { name: searchTerm } });
        const pager = res.data?.courses ?? res.data;
        setItems(pager?.data ?? []);
        setPagination({
          data: pager?.data ?? [],
          current_page: pager?.current_page ?? 1,
          last_page: pager?.last_page ?? 1,
          next_page_url: pager?.next_page_url ?? null,
          prev_page_url: pager?.prev_page_url ?? null,
        });
      } catch (error) {
        console.error(error);
      }
    }, 600);

    setTypingTimeout(timeout);
  }, [searchTerm]);

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
    } catch {
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
      <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition">
        {/* Header */}
        <div className="flex flex-wrap items-center justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
          <h1 className="text-3xl font-semibold flex items-center gap-2">
            <BookOpen className="w-6 h-6 text-blue-600 dark:text-blue-400" />
            Gestión de Cursos
          </h1>

          <div className="flex items-center gap-3">
            {selectedIds.length > 0 && (
              <button
                onClick={removeBulk}
                className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md shadow transition"
              >
                Eliminar Seleccionados ({selectedIds.length})
              </button>
            )}
            <button
              onClick={() => {
                setEditItem(null);
                setShowModal(true);
              }}
              className="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-md shadow-md flex items-center gap-2 transition"
            >
              Mantenimiento Cursos ISIL
            </button>
          </div>
        </div>

        {/* Buscador */}
        <div className="relative mb-6 w-full md:w-1/2">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
          <input
            type="text"
            placeholder="Buscar curso..."
            className="w-full pl-9 pr-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>

        {/* Tabla */}
        <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800 shadow-sm">
          <table className="min-w-full table-auto text-sm">
            <thead className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 uppercase text-xs">
              <tr>
                <th className="px-4 py-2 text-center">
                  <input
                    type="checkbox"
                    checked={
                      items.length > 0 && items.every((i) => selectedIds.includes(i.id))
                    }
                    onChange={(e) =>
                      setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])
                    }
                  />
                </th>
                <th className="px-4 py-2 text-left">Acciones</th>
                <th className="px-4 py-2 text-left">ID</th>
                <th className="px-4 py-2 text-left">Nombre</th>
                <th className="px-4 py-2">Lenguajes</th>
                <th className="px-4 py-2">Tecnologías</th>
                <th className="px-4 py-2">Metodologías</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
              {items.map((item) => (
                <tr
                  key={item.id}
                  className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
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
                  <td className="px-4 py-2 whitespace-nowrap flex gap-2">
                    <button
                      onClick={() => fetchItem(item.id)}
                      className="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 inline-flex items-center gap-1 text-sm font-medium"
                    >
                      <Paintbrush className="w-4 h-4" /> Editar
                    </button>
                    <button
                      onClick={() => removeOne(item.id, item.name)}
                      className="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 inline-flex items-center gap-1 text-sm font-medium"
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
                            className="px-2 py-0.5 bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200 rounded text-xs"
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
                            className="px-2 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200 rounded text-xs"
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
                            className="px-2 py-0.5 bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200 rounded text-xs"
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
                  <td
                    colSpan={7}
                    className="px-4 py-6 text-center text-gray-500 dark:text-gray-400"
                  >
                    No hay cursos para mostrar.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Paginación */}
        {pagination.last_page > 1 && (
          <div className="flex justify-center mt-6 gap-1">
            {[...Array(pagination.last_page)].map((_, index) => {
              const page = index + 1;
              return (
                <button
                  key={page}
                  onClick={() => fetchPage(`/courses/fetch?page=${page}`)}
                  className={`px-3 py-1 rounded-md text-sm font-medium transition ${
                    pagination.current_page === page
                      ? 'bg-blue-600 text-white shadow'
                      : 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600'
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

      {/* Modal de mantenimiento */}
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
