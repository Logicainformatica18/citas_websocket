import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage, router } from '@inertiajs/react';   // <--- OK
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Trash2, Plus, Briefcase } from 'lucide-react';
import JobOfferModal from './JobOfferModal';
import JobOfferCsvModal from './JobOfferCsvModal';
import JobOfferFilters from './JobOfferFilters';
import JobOfferDetailModal from "./JobOfferDetailModal";

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ofertas de Empleo', href: '/job-offers' },
];

// === FORMATEO DE FECHAS ===
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
function cleanFilters(filters: any) {
    const cleaned: any = {};

    Object.keys(filters).forEach(key => {
        const v = filters[key];

        if (v === null || v === "null" || v === "" || v === undefined) {
            cleaned[key] = null;
            return;
        }

        // Si viene "x,y,z"
        if (typeof v === "string" && v.includes(",")) {
            cleaned[key] = v.split(",").filter(x => x.trim() !== "");
            return;
        }

        // Si viene algo tipo [] o ["IBM"]
        if (Array.isArray(v)) {
            cleaned[key] = v.filter(x => x !== "" && x !== null);
            return;
        }

        cleaned[key] = v;
    });

    return cleaned;
}

export default function JobOffersIndex() {
    const { offers: initialPagination, filters, combos } = usePage().props;
    const [exporting, setExporting] = useState(false);

    const [items, setItems] = useState(initialPagination.data);
    const [pagination, setPagination] = useState(initialPagination);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [showModal, setShowModal] = useState(false);
    const [showCsvModal, setShowCsvModal] = useState(false);

    // === CUANDO CAMBIAN LOS RESULTADOS DE INERTIA ===
    useEffect(() => {
        setItems(initialPagination.data);
        setPagination(initialPagination);
    }, [initialPagination]);

    // ================================================
    //   📌 NUEVA PAGINACIÓN QUE CONSERVA LOS FILTROS
    // ================================================
    const goToPage = (page: number) => {
        const params = { ...filters, page };

        router.get('/job-offers', params, {
            preserveScroll: true,
            preserveState: true,
        });
    };


    // ================================================
    //   📌 ELIMINAR UNA OFERTA
    // ================================================
    const removeOne = async (id: number, title: string) => {
        if (!confirm(`¿Eliminar la oferta "${title}"?`)) return;

        try {
            await axios.delete(`/job-offers/${id}`);
            setItems(prev => prev.filter(i => i.id !== id));
            setSelectedIds(prev => prev.filter(x => x !== id));
        } catch {
            alert('No se pudo eliminar la oferta.');
        }
    };

    // ================================================
    //   📌 ELIMINAR EN LOTE
    // ================================================
    const removeBulk = async () => {
        if (selectedIds.length === 0) return;
        if (!confirm(`¿Eliminar ${selectedIds.length} oferta(s)?`)) return;

        try {
            await axios.delete('/job-offers', { data: { ids: selectedIds } });
            setItems(prev => prev.filter(i => !selectedIds.includes(i.id)));
            setSelectedIds([]);
        } catch {
            alert('No se pudo eliminar en lote.');
        }
    };
    const exportExcel = async () => {
        try {
            setExporting(true);

            const cleaned = cleanFilters(filters);
            const params = new URLSearchParams(cleaned).toString();

            const response = await fetch(`/job-offers/export-excel?${params}`, {
                method: "GET",
            });

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);

            const a = document.createElement("a");
            a.href = url;
            a.download = "job_offers.xlsx";
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);

        } catch (error) {
            console.error("Error exportando Excel:", error);
        } finally {
            setExporting(false);
        }
    };
    const [detailItem, setDetailItem] = useState(null);
    const [showDetail, setShowDetail] = useState(false);

    const openDetail = async (id: number) => {
        try {
            const res = await axios.get(`/job-offers/${id}`);
            setDetailItem(res.data.offer);
            setShowDetail(true);
        } catch (e) {
            alert("No se pudo cargar el detalle.");
        }
    };




  return (
  <AppLayout breadcrumbs={breadcrumbs}>
    <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors">

      {/* 🔎 FILTROS */}
      <JobOfferFilters filters={filters} combos={combos} />

      {/* 📊 Exportar */}
      <button
        disabled={exporting}
        onClick={exportExcel}
        className="px-4 py-2 rounded-md shadow flex items-center gap-2 bg-yellow-500 hover:bg-yellow-600 text-white disabled:opacity-50 my-4"
      >
        {exporting ? (
          <>
            <span className="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4"></span>
            Generando...
          </>
        ) : (
          <>📊 Exportar Excel (Hasta 4 mil Registros)</>
        )}
      </button>

      {/* HEADER */}
      <div className="flex flex-wrap items-center justify-between mb-6 pb-4 border-b border-gray-300 dark:border-gray-700">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <Briefcase className="w-7 h-7 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">Ofertas de Empleo</span>
        </h1>

        <div className="flex items-center gap-3">
          {selectedIds.length > 0 && (
            <button
              onClick={removeBulk}
              className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md shadow"
            >
              Eliminar Seleccionadas ({selectedIds.length})
            </button>
          )}

          <button
            onClick={() => setShowCsvModal(true)}
            className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md shadow flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Importar CSV
          </button>

          <button
            onClick={() => setShowModal(true)}
            className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Importar Automático
          </button>
        </div>
      </div>

      {/* TABLA */}
      <div className="overflow-x-auto border rounded-xl shadow bg-white dark:bg-gray-900 dark:border-gray-700">
        <table className="min-w-full text-sm">
          <thead className="bg-[#1CBCE8]/90 dark:bg-[#1CBCE8]/30 text-white dark:text-[#1CBCE8] uppercase text-xs font-semibold">
            <tr>
              <th className="px-4 py-3 text-center">
                <input
                  type="checkbox"
                  checked={items.length > 0 && items.every(i => selectedIds.includes(i.id))}
                  onChange={e =>
                    setSelectedIds(e.target.checked ? items.map(i => i.id) : [])
                  }
                />
              </th>
              <th className="px-4 py-3 text-left">Acciones</th>
              <th className="px-4 py-3">Título</th>
              <th className="px-4 py-3">Empresa</th>
              <th className="px-4 py-3">País</th>
              <th className="px-4 py-3">Ciudad</th>
              <th className="px-4 py-3">Modalidad</th>
              <th className="px-4 py-3">Salario</th>
              <th className="px-4 py-3">Fuente</th>
              <th className="px-4 py-3">Publicado</th>
              <th className="px-4 py-3">Registrado</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
            {items.map(item => (
              <tr
                key={item.id}
                className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
              >
                <td className="px-4 py-2 text-center">
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(item.id)}
                    onChange={e =>
                      setSelectedIds(prev =>
                        e.target.checked
                          ? [...prev, item.id]
                          : prev.filter(x => x !== item.id)
                      )
                    }
                  />
                </td>

                <td className="px-4 py-2">
                  <button
                    onClick={() => removeOne(item.id, item.title)}
                    className="text-red-600 hover:text-red-700 flex items-center gap-1"
                  >
                    <Trash2 className="w-4 h-4" /> Eliminar
                  </button>
                </td>

                <td className="px-4 py-2 font-medium">
                  <button
                    onClick={() => openDetail(item.id)}
                    className="text-[#1CBCE8] hover:underline"
                  >
                    {item.title}
                  </button>
                </td>

                <td className="px-4 py-2">{item.company ?? '-'}</td>
                <td className="px-4 py-2">{item.country ?? '-'}</td>
                <td className="px-4 py-2">{item.city ?? '-'}</td>
                <td className="px-4 py-2">{item.modality ?? '-'}</td>

                <td className="px-4 py-2">
                  {item.salary_min
                    ? `${item.salary_min} - ${item.salary_max} ${item.currency ?? ''}`
                    : 'N/A'}
                </td>

                <td className="px-4 py-2">
                  <span className="px-2 py-1 text-xs bg-[#1CBCE8] text-white dark:bg-[#1CBCE8]/30 dark:text-[#1CBCE8] rounded">
                    {item.source}
                  </span>
                </td>

                <td className="px-4 py-2">{formatDate(item.published_at)}</td>
                <td className="px-4 py-2">{formatDate(item.created_at)}</td>
              </tr>
            ))}

            {items.length === 0 && (
              <tr>
                <td colSpan={11} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                  No hay ofertas registradas.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* PAGINACIÓN */}
      {pagination.last_page > 1 && (
        <div className="flex justify-center mt-6 gap-1">
          {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
            .filter(
              page =>
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
                  {isGap && <span className="px-2 py-1 text-gray-400">…</span>}

                  <button
                    onClick={() => goToPage(page)}
                    className={`px-3 py-1 rounded-md text-sm transition ${
                      pagination.current_page === page
                        ? 'bg-[#1CBCE8] text-white shadow'
                        : 'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200'
                    }`}
                  >
                    {page}
                  </button>
                </span>
              );
            })}
        </div>
      )}
    </div>

    {/* MODAL IMPORT AUTOMATICO */}
    {showModal && (
      <JobOfferModal
        open={showModal}
        onClose={() => setShowModal(false)}
        onImported={() => router.get('/job-offers')}
      />
    )}

    {/* MODAL DE DETALLE */}
    {showDetail && (
      <JobOfferDetailModal
        open={showDetail}
        item={detailItem}
        onClose={() => setShowDetail(false)}
      />
    )}

    {/* MODAL CSV */}
    {showCsvModal && (
      <JobOfferCsvModal
        open={showCsvModal}
        onClose={() => setShowCsvModal(false)}
        onImported={() => router.get('/job-offers')}
      />
    )}
  </AppLayout>
);


}
