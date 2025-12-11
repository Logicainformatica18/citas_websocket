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

      {/* HEADER ISIL */}
      <div className="flex flex-wrap items-center justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <BookOpen className="w-7 h-7 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">Gestión de Cursos</span>
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
            className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow flex items-center gap-2 transition"
          >
            Mantenimiento Cursos ISIL
          </button>
        </div>
      </div>

      {/* BUSCADOR */}
      <div className="relative mb-6 w-full md:w-1/2">
        <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
        <input
          type="text"
          placeholder="Buscar curso..."
          className="
            w-full pl-9 pr-3 py-2 rounded-md
            border border-gray-300 dark:border-gray-700
            bg-white dark:bg-gray-800
            text-gray-900 dark:text-gray-100
            focus:ring-2 focus:ring-[#1CBCE8] outline-none
          "
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
        />
      </div>

      {/* TABLA ISIL */}
      <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800 shadow">
        <table className="min-w-full table-auto text-sm">

          {/* HEAD */}
          <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs tracking-wide">
            <tr>
              <th className="px-4 py-2 text-center">
                <input
                  type="checkbox"
                  checked={items.length > 0 && items.every((i) => selectedIds.includes(i.id))}
                  onChange={(e) =>
                    setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])
                  }
                />
              </th>
              <th className="px-4 py-2 text-left">Acciones</th>
              <th className="px-4 py-2 text-left">ID</th>
              <th className="px-4 py-2 text-left">Nombre</th>
              <th className="px-4 py-2 text-left">Lenguajes</th>
              <th className="px-4 py-2 text-left">Tecnologías</th>
              <th className="px-4 py-2 text-left">Metodologías</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">

            {items.map((item) => (
              <tr
                key={item.id}
                className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
              >
                {/* CHECKBOX */}
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

                {/* ACCIONES */}
                <td className="px-4 py-2 whitespace-nowrap flex gap-2">
                  <button
                    onClick={() => fetchItem(item.id)}
                    className="text-[#1CBCE8] hover:text-[#17A8D0] inline-flex items-center gap-1 text-sm font-medium"
                  >
                    <Paintbrush className="w-4 h-4" /> Editar
                  </button>

                  <button
                    onClick={() => removeOne(item.id, item.name)}
                    className="text-red-600 hover:text-red-700 inline-flex items-center gap-1 text-sm font-medium"
                  >
                    <Trash2 className="w-4 h-4" /> Eliminar
                  </button>
                </td>

                {/* ID */}
                <td className="px-4 py-2">{item.id}</td>

                {/* NOMBRE */}
                <td className="px-4 py-2 font-semibold">{item.name}</td>

                {/* LENGUAJES */}
                <td className="px-4 py-2">
                  {item.languages.length ? (
                    <div className="flex flex-wrap gap-1">
                      {item.languages.map((l) => (
                        <span
                          key={l.id}
                          className="px-2 py-0.5 bg-[#C9F3FF] text-[#0C647A] dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8] border border-[#1CBCE8]/30 rounded text-xs"
                        >
                          {l.name}
                        </span>
                      ))}
                    </div>
                  ) : (
                    "-"
                  )}
                </td>

                {/* TECNOLOGÍAS */}
                <td className="px-4 py-2">
                  {item.technologies.length ? (
                    <div className="flex flex-wrap gap-1">
                      {item.technologies.map((t) => (
                        <span
                          key={t.id}
                          className="px-2 py-0.5 bg-[#C9F3FF] text-[#0C647A] dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8] border border-[#1CBCE8]/30 rounded text-xs"
                        >
                          {t.name}
                        </span>
                      ))}
                    </div>
                  ) : (
                    "-"
                  )}
                </td>

                {/* METODOLOGÍAS */}
                <td className="px-4 py-2">
                  {item.methodologies.length ? (
                    <div className="flex flex-wrap gap-1">
                      {item.methodologies.map((m) => (
                        <span
                          key={m.id}
                          className="px-2 py-0.5 bg-[#C9F3FF] text-[#0C647A] dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8] border border-[#1CBCE8]/30 rounded text-xs"
                        >
                          {m.name}
                        </span>
                      ))}
                    </div>
                  ) : (
                    "-"
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

      {/* PAGINACIÓN ISIL */}
   {pagination.last_page > 1 && (
  <div className="flex justify-center mt-6 gap-1">

    {(() => {
      const pages = [];
      const total = pagination.last_page;
      const current = pagination.current_page;

      const addPage = (p: number) =>
        pages.push(
          <button
            key={p}
            onClick={() => fetchPage(`/courses/fetch?page=${p}`)}
            className={`px-3 py-1 rounded-md text-sm font-medium transition
              ${
                current === p
                  ? "bg-[#1CBCE8] text-white shadow"
                  : "bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
              }
            `}
            disabled={current === p}
          >
            {p}
          </button>
        );

      // Mostrar primera página si estás lejos
      if (current > 3) addPage(1);

      // Mostrar puntos suspensivos
      if (current > 4)
        pages.push(<span key="dots1" className="px-2 text-gray-400">…</span>);

      // Ventana central
      for (let p = current - 2; p <= current + 2; p++) {
        if (p >= 1 && p <= total) addPage(p);
      }

      // Segundo grupo de puntos suspensivos
      if (current < total - 3)
        pages.push(<span key="dots2" className="px-2 text-gray-400">…</span>);

      // Última página
      if (current < total - 2) addPage(total);

      return pages;
    })()}
  </div>
)}

    </div>

    {/* MODAL */}
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
