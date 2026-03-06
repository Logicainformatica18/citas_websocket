import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Trash2, Plus, FileText } from 'lucide-react';
import SyllabusModal from './SyllabusModal';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sílabos', href: '/syllabus' }];

type Tecnologia = { nombre: string; tipo?: string | null };

type Upload = {
  id: number;
  filename: string;
  path: string;
  status: 'pending' | 'processing' | 'processed' | 'failed';
  detected_course?: string | null;
  structured_data?: {
    curso?: string;
    lenguajes?: string[];
    tecnologias?: (string | Tecnologia)[];
    metodologias?: string[];
  };
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function SyllabusIndex() {
  const { uploads: initialPagination } = usePage<{ uploads: Pagination<Upload> }>().props;
  const [items, setItems] = useState<Upload[]>([]);
  const [pagination, setPagination] = useState<Pagination<Upload>>(initialPagination);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showModal, setShowModal] = useState(false);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

 const fetchPage = async (url: string) => {
  try {

    const res = await axios.get(url, {
      params: { search }
    });

    const pager = res.data;

    setItems(pager.data ?? []);
    setPagination({
      data: pager.data ?? [],
      current_page: pager.current_page ?? 1,
      last_page: pager.last_page ?? 1,
      next_page_url: pager.next_page_url ?? null,
      prev_page_url: pager.prev_page_url ?? null,
    });

    setSelectedIds([]);

  } catch (e) {
    console.error("Error al cargar página", e);
  }
};
const [search, setSearch] = useState("");
const handleSearch = async (value: string) => {
  setSearch(value);

  try {
    const res = await axios.get("/syllabus/fetch", {
      params: { search: value }
    });

    const pager = res.data;

    setItems(pager.data ?? []);
    setPagination({
      data: pager.data ?? [],
      current_page: pager.current_page ?? 1,
      last_page: pager.last_page ?? 1,
      next_page_url: pager.next_page_url ?? null,
      prev_page_url: pager.prev_page_url ?? null,
    });

  } catch (e) {
    console.error("Error buscando", e);
  }
};
  const removeOne = async (id: number, filename: string) => {
    if (!confirm(`¿Eliminar el sílabo "${filename}"?`)) return;
    try {
      await axios.delete(`/syllabus/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
    } catch {
      alert('No se pudo eliminar.');
    }
  };

  const removeBulk = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`¿Eliminar ${selectedIds.length} sílabo(s)?`)) return;
    try {
      await axios.post('/syllabus/bulk-delete', { ids: selectedIds });
      setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id)));
      setSelectedIds([]);
    } catch {
      alert('No se pudo eliminar en lote.');
    }
  };

  // 🔽 Render de tecnologías agrupadas
  const renderTecnologias = (tecnologias?: (string | Tecnologia)[]) => {
    if (!tecnologias || tecnologias.length === 0)
      return <span className="text-gray-500 dark:text-gray-400">-</span>;

    const normalizadas = tecnologias.map((t) =>
      typeof t === 'string'
        ? { nombre: t, tipo: 'Sin categoría' }
        : { nombre: t.nombre, tipo: t.tipo ?? 'Sin categoría' }
    );

    const agrupadas = normalizadas.reduce((acc: Record<string, Tecnologia[]>, t) => {
      if (!acc[t.tipo!]) acc[t.tipo!] = [];
      acc[t.tipo!].push(t);
      return acc;
    }, {});

    return (
      <select
        className="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-sm rounded-md px-2 py-1 w-full text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:outline-none"
        defaultValue={normalizadas[0]?.nombre}
      >
        {Object.entries(agrupadas).map(([tipo, lista]) => (
          <optgroup key={tipo} label={tipo}>
            {lista.map((t, idx) => (
              <option key={`${tipo}-${t.nombre}-${idx}`} value={t.nombre}>
                {t.nombre}
              </option>
            ))}
          </optgroup>
        ))}
      </select>
    );
  };

return (
  <AppLayout breadcrumbs={breadcrumbs}>
    <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">

      {/* HEADER ISIL */}
    <div className="flex items-center justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">

  <h1 className="text-3xl font-semibold flex items-center gap-2">
    <FileText className="text-[#1CBCE8] w-7 h-7" />
    <span className="text-[#0C647A] dark:text-[#1CBCE8]">
      Gestión de Sílabos
    </span>
  </h1>

  <div className="flex items-center gap-3">

    {/* BUSCADOR */}
    <input
      type="text"
      placeholder="Buscar curso..."
      value={search}
      onChange={(e) => handleSearch(e.target.value)}
      className="px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-md text-sm
      bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200
      focus:ring-2 focus:ring-[#1CBCE8] focus:outline-none"
    />

    <button
      onClick={() => setShowModal(true)}
      className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow flex items-center gap-2 transition"
    >
      <Plus className="w-4 h-4" /> Subir Sílabos
    </button>

  </div>
</div>

      {/* BOTÓN ELIMINAR MÚLTIPLE */}
      {selectedIds.length > 0 && (
        <div className="mb-4">
          <button
            onClick={removeBulk}
            className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md shadow transition"
          >
            Eliminar Seleccionados ({selectedIds.length})
          </button>
        </div>
      )}

      {/* TABLA */}
      <div className="overflow-x-auto rounded-lg shadow border border-gray-200 dark:border-gray-800">
        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">

          {/* HEADER ISIL */}
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
              <th className="px-4 py-2">Acciones</th>
              <th className="px-4 py-2">Ver</th>
              <th className="px-4 py-2">Archivo</th>
              <th className="px-4 py-2">Estado</th>
              <th className="px-4 py-2">Curso</th>
              <th className="px-4 py-2">Lenguajes</th>
              <th className="px-4 py-2">Tecnologías</th>
              <th className="px-4 py-2">Metodologías</th>
            </tr>
          </thead>

          {/* BODY */}
          <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
            {items.map((item) => (
              <tr
                key={item.id}
                className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
              >
                {/* SELECT */}
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
                <td className="px-4 py-2 whitespace-nowrap">
                  <button
                    onClick={() => removeOne(item.id, item.filename)}
                    className="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 inline-flex items-center gap-1 text-sm font-medium"
                  >
                    <Trash2 className="w-4 h-4" /> Eliminar
                  </button>
                </td>

                {/* VER PDF */}
                <td className="px-4 py-2">
                  <a
                    href={`/${item.path}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-[#1CBCE8] hover:underline font-medium text-sm"
                  >
                    Ver PDF
                  </a>
                </td>

                {/* FILENAME */}
                <td className="px-4 py-2">{item.filename}</td>

                {/* ESTADO → CHIP ISIL */}
                <td className="px-4 py-2">
                  <span
                    className={`px-2 py-1 rounded-full text-xs font-medium
                      ${
                        item.status === "pending"
                          ? "bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                          : item.status === "processing"
                          ? "bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                          : item.status === "processed"
                          ? "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200"
                          : "bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200"
                      }
                    `}
                  >
                    {item.status}
                  </span>
                </td>

                {/* CURSO */}
                <td className="px-4 py-2">{item.structured_data?.curso ?? "-"}</td>

                {/* LENGUAJES */}
                <td className="px-4 py-2">
                  {item.structured_data?.lenguajes?.join(", ") ?? "-"}
                </td>

                {/* TECNOLOGÍAS */}
                <td className="px-4 py-2">
                  {renderTecnologias(item.structured_data?.tecnologias)}
                </td>

                {/* METODOLOGÍAS */}
                <td className="px-4 py-2">
                  {item.structured_data?.metodologias?.join(", ") ?? "-"}
                </td>
              </tr>
            ))}

            {items.length === 0 && (
              <tr>
                <td colSpan={9} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                  No hay sílabos para mostrar.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* PAGINACIÓN ISIL */}
      {pagination.last_page > 1 && (
        <div className="flex justify-center mt-6 gap-2">
          {(() => {
            const pages: (number | string)[] = [];
            const total = pagination.last_page;
            const current = pagination.current_page;

            if (total <= 7) {
              for (let i = 1; i <= total; i++) pages.push(i);
            } else {
              pages.push(1);
              if (current > 4) pages.push("...");
              const start = Math.max(2, current - 1);
              const end = Math.min(total - 1, current + 1);
              for (let i = start; i <= end; i++) pages.push(i);
              if (current < total - 3) pages.push("...");
              pages.push(total);
            }

            return pages.map((p, idx) =>
              p === "..." ? (
                <span key={`ellipsis-${idx}`} className="px-2 text-gray-500 dark:text-gray-400">
                  …
                </span>
              ) : (
                <button
                  key={`page-${p}-${idx}`}
                  onClick={() => fetchPage(`/syllabus/fetch?page=${p}`)}
                  className={`px-3 py-1 rounded-md text-sm transition
                    ${
                      pagination.current_page === p
                        ? "bg-[#1CBCE8] text-white shadow"
                        : "bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    }
                  `}
                  disabled={pagination.current_page === p}
                >
                  {p}
                </button>
              )
            );
          })()}
        </div>
      )}

    </div>

    {/* MODAL SUBIR SYLLABUS */}
    {showModal && (
      <SyllabusModal
        open={showModal}
        onClose={() => setShowModal(false)}
        onUploaded={() => fetchPage("/syllabus/fetch")}
      />
    )}
  </AppLayout>
);

}
