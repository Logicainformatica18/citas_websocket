import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Trash2, Plus } from 'lucide-react';
import SyllabusModal from './SyllabusModal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Sílabos', href: '/syllabus' },
];

type Tecnologia = {
  nombre: string;
  tipo?: string | null;
};

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
      const res = await axios.get(url);
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
      console.error('Error al cargar página', e);
      alert('No se pudo cargar la página.');
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

// 🔽 Combobox que muestra tecnologías agrupadas por tipo
const renderTecnologias = (tecnologias?: (string | Tecnologia)[]) => {
  if (!tecnologias || tecnologias.length === 0) {
    return <span className="text-gray-400">-</span>;
  }

  // Normalizar las tecnologías
  const normalizadas = tecnologias.map((t) =>
    typeof t === 'string'
      ? { nombre: t, tipo: 'Sin categoría' }
      : { nombre: t.nombre, tipo: t.tipo ?? 'Sin categoría' }
  );

  // Agrupar por tipo
  const agrupadas = normalizadas.reduce((acc: Record<string, Tecnologia[]>, t) => {
    if (!acc[t.tipo!]) acc[t.tipo!] = [];
    acc[t.tipo!].push(t);
    return acc;
  }, {});

  return (
    <div className="relative inline-block w-full">
      <select
        className="bg-gray-800 border border-gray-700 text-sm rounded px-2 py-1 w-full text-gray-200 focus:outline-none focus:ring focus:ring-blue-600"
        defaultValue={normalizadas[0]?.nombre}
      >
        {Object.entries(agrupadas).map(([tipo, lista]) => (
          <optgroup key={tipo} label={tipo} className="bg-gray-900 text-gray-100">
            {lista.map((t, idx) => (
              <option key={`${tipo}-${t.nombre}-${idx}`} value={t.nombre}>
                {t.nombre}
              </option>
            ))}
          </optgroup>
        ))}
      </select>
    </div>
  );
};


  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 text-white">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold">Gestión de Sílabos</h1>
          <button
            onClick={() => setShowModal(true)}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded inline-flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Subir Sílabos
          </button>
        </div>

        {selectedIds.length > 0 && (
          <div className="mb-4">
            <button
              onClick={removeBulk}
              className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
            >
              Eliminar Seleccionados
            </button>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border border-gray-700 rounded bg-gray-900 text-gray-200">
            <thead className="bg-gray-800 text-left text-gray-300">
              <tr>
                <th className="px-4 py-2">
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
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-t border-gray-700 hover:bg-gray-800">
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
                      onClick={() => removeOne(item.id, item.filename)}
                      className="text-red-400 hover:text-red-300 inline-flex items-center gap-1 text-sm"
                    >
                      <Trash2 className="w-4 h-4" /> Eliminar
                    </button>
                  </td>
                  <td className="px-4 py-2">
                    <a
                      href={`/${item.path}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-blue-400 hover:text-blue-300 underline text-sm"
                    >
                      Ver PDF
                    </a>
                  </td>
                  <td className="px-4 py-2">{item.filename}</td>
                  <td className="px-4 py-2">
                    <span
                      className={`px-2 py-1 rounded text-xs font-medium ${
                        item.status === 'pending'
                          ? 'bg-yellow-900 text-yellow-200'
                          : item.status === 'processing'
                          ? 'bg-blue-900 text-blue-200'
                          : item.status === 'processed'
                          ? 'bg-green-900 text-green-200'
                          : 'bg-red-900 text-red-200'
                      }`}
                    >
                      {item.status}
                    </span>
                  </td>
                  <td className="px-4 py-2">{item.structured_data?.curso ?? '-'}</td>
                  <td className="px-4 py-2">{item.structured_data?.lenguajes?.join(', ') ?? '-'}</td>
                  <td className="px-4 py-2">{renderTecnologias(item.structured_data?.tecnologias)}</td>
                  <td className="px-4 py-2">{item.structured_data?.metodologias?.join(', ') ?? '-'}</td>
                </tr>
              ))}

              {items.length === 0 && (
                <tr>
                  <td colSpan={9} className="px-4 py-6 text-center text-gray-400">
                    No hay sílabos para mostrar.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* 🔹 Paginación */}
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
                if (current > 4) pages.push('...');
                const start = Math.max(2, current - 1);
                const end = Math.min(total - 1, current + 1);
                for (let i = start; i <= end; i++) pages.push(i);
                if (current < total - 3) pages.push('...');
                pages.push(total);
              }

              return pages.map((p, idx) =>
                p === '...' ? (
                  <span key={`ellipsis-${idx}`} className="px-2 text-gray-500">
                    …
                  </span>
                ) : (
                  <button
                    key={`page-${p}-${idx}`}
                    onClick={() => fetchPage(`/syllabus/fetch?page=${p}`)}
                    className={`px-3 py-1 rounded text-sm font-medium transition ${
                      pagination.current_page === p
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                    }`}
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

      {showModal && (
        <SyllabusModal
          open={showModal}
          onClose={() => setShowModal(false)}
          onUploaded={() => fetchPage('/syllabus/fetch')}
        />
      )}
    </AppLayout>
  );
}
