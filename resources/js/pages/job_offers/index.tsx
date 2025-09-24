import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Trash2, Plus } from 'lucide-react';
import JobOfferModal from './JobOfferModal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Ofertas de Empleo', href: '/job-offers' },
];
// Helper para formatear fechas
function formatDate(dateString?: string | null): string {
  if (!dateString) return "-";
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return "-"; // por si el string no es válido
    return date.toLocaleDateString("es-PE", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  } catch {
    return "-";
  }
}

type JobOffer = {
  id: number;
  title: string;
  company?: string | null;
  location?: string | null;
  modality?: string | null;
  salary_min?: number | null;
  salary_max?: number | null;
  currency?: string | null;
  source: string;
  url?: string | null;
  published_at?: string | null;
  created_at?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function JobOffersIndex() {
  const { offers: initialPagination } = usePage<{ offers: Pagination<JobOffer> }>().props;

  const [items, setItems] = useState<JobOffer[]>([]);
  const [pagination, setPagination] = useState<Pagination<JobOffer>>(initialPagination);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showModal, setShowModal] = useState(false);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const normalizePagePayload = (payload: any): Pagination<JobOffer> => {
    const pager = payload?.offers ?? payload ?? {};
    const data: JobOffer[] = Array.isArray(pager) ? pager : (pager?.data ?? []);
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

  const removeOne = async (id: number, title: string) => {
    if (!confirm(`¿Eliminar la oferta "${title}"?`)) return;
    try {
      await axios.delete(`/job-offers/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      setSelectedIds((prev) => prev.filter((x) => x !== id));
    } catch (e) {
      console.error('Error al eliminar', e);
      alert('No se pudo eliminar la oferta.');
    }
  };

  const removeBulk = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`¿Eliminar ${selectedIds.length} oferta(s)?`)) return;
    try {
      await axios.delete('/job-offers', { data: { ids: selectedIds } });
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
        <h1 className="text-2xl font-bold mb-6">Ofertas de Empleo</h1>

        <div className="flex items-center gap-2 mb-4">
          <button
            onClick={() => setShowModal(true)}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 inline-flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Importar desde API
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
                    checked={items.length > 0 && items.every(i => selectedIds.includes(i.id))}
                    onChange={(e) =>
                      setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])
                    }
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Título</th>
                <th className="px-4 py-2">Empresa</th>
                <th className="px-4 py-2">Ubicación</th>
                <th className="px-4 py-2">Modalidad</th>
                <th className="px-4 py-2">Salario</th>
                <th className="px-4 py-2">Fuente</th>
                <th className="px-4 py-2">Publicado</th>
                <th className="px-4 py-2">Registrado en</th>
              </tr>
            </thead>
           <tbody>
  {items.map((item) => (
    <tr key={item.id} className="border-t hover:bg-gray-50 align-top">
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
          onClick={() => removeOne(item.id, item.title)}
          className="text-red-600 hover:underline inline-flex items-center gap-1"
        >
          <Trash2 className="w-4 h-4" /> Eliminar
        </button>
      </td>
      <td className="px-4 py-2">
        {item.url ? (
          <a
            href={item.url}
            target="_blank"
            rel="noopener noreferrer"
            className="text-blue-600 hover:underline"
          >
            {item.title}
          </a>
        ) : (
          item.title
        )}
      </td>
      <td className="px-4 py-2">{item.company ?? "-"}</td>
      <td className="px-4 py-2">{item.location ?? "-"}</td>
      <td className="px-4 py-2">{item.modality ?? "-"}</td>
      <td className="px-4 py-2">
        {item.salary_min
          ? `${item.salary_min} - ${item.salary_max} ${item.currency}`
          : "N/A"}
      </td>
      <td className="px-4 py-2">
        <span className="px-2 py-0.5 text-xs rounded bg-gray-100 border">
          {item.source}
        </span>
      </td>
      <td className="px-4 py-2">{formatDate(item.published_at)}</td>
      <td className="px-4 py-2">{formatDate(item.created_at)}</td>
    </tr>
  ))}

  {items.length === 0 && (
    <tr>
      <td className="px-4 py-6 text-center text-gray-500" colSpan={10}>
        No hay ofertas para mostrar.
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
                onClick={() => fetchPage(`/job-offers/fetch?page=${page}`)}
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
        <JobOfferModal
          open={showModal}
          onClose={() => setShowModal(false)}
          onImported={() => fetchPage('/job-offers/fetch')}
        />
      )}
    </AppLayout>
  );
}
