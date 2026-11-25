import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage, router, Link } from '@inertiajs/react';   // <--- OK
import { useState, useEffect } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Trash2, Plus, Briefcase, ShieldOff, Download, Sparkles } from 'lucide-react';
import { ProcessBar, useProcess, type JobOfferProcess } from './ProcessStatus';
import JobOfferModal from './JobOfferModal';
import JobOfferCsvModal from './JobOfferCsvModal';
import JobOfferFilters from './JobOfferFilters';
import JobOfferDetailModal from "./JobOfferDetailModal";

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ofertas de Empleo', href: '/job-offers' },
];

// Clasificación TI (estado único de los indicadores: solo "No TI" deja de contar)
const TI_LABEL: Record<string, string> = { pendiente: 'pendiente', en_proceso: 'en proceso', ti: 'TI', no_ti: 'No TI', dudosa: 'dudosa' };
const TI_BADGE: Record<string, string> = {
    en_proceso: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    ti: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    no_ti: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    dudosa: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
};

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
    const { offers: initialPagination, filters, combos, lastExport } = usePage().props as any;

    // Export en cola: el proceso se sondea y al terminar se descarga el archivo
    const downloadUrl = (p: JobOfferProcess) => `/job-offers/exports/${p.id}/download`;
    const [exportProc, setExportProc] = useProcess(lastExport ?? null, (p) => {
        if (p.status === 'done') {
            window.location.href = downloadUrl(p);
        }
    });
    const exporting = !!exportProc && (exportProc.status === 'queued' || exportProc.status === 'running');

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
            const { data } = await axios.post('/job-offers/export-excel', cleanFilters(filters));
            setExportProc(data.process);
        } catch (error: any) {
            if (error.response?.status === 409 && error.response.data?.process) {
                setExportProc(error.response.data.process);
                return;
            }
            Swal.fire({ icon: 'error', title: 'No se pudo exportar', text: error.response?.data?.message ?? '' });
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

      {/* 📊 Exportar (en cola, con los filtros aplicados) */}
      <div className="flex flex-wrap items-center gap-3 my-4">
        <button
          disabled={exporting}
          onClick={exportExcel}
          className="px-4 py-2 rounded-md shadow flex items-center gap-2 bg-yellow-500 hover:bg-yellow-600 text-white disabled:opacity-50"
        >
          {exporting ? (
            <>
              <span className="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4"></span>
              Generando...
            </>
          ) : (
            <>📊 Exportar Excel ({(initialPagination.total ?? 0).toLocaleString('es-PE')} registros con los filtros)</>
          )}
        </button>
        {exportProc?.status === 'done' && exportProc.result && (
          <a href={downloadUrl(exportProc)} className="text-sm text-[#0C647A] dark:text-[#1CBCE8] hover:underline flex items-center gap-1">
            <Download className="w-4 h-4" /> Descargar {exportProc.result.download_name} ({exportProc.result.rows.toLocaleString('es-PE')} filas, {exportProc.result.size_mb} MB)
            {exportProc.result.truncated_rows > 0 && ` · ${exportProc.result.truncated_rows.toLocaleString('es-PE')} filas no caben en Excel`}
          </a>
        )}
        <Link href="/ai-classification" className="ml-auto px-4 py-2 rounded-md shadow flex items-center gap-2 border">
          <Sparkles className="w-4 h-4" /> Clasificación con IA
        </Link>
        <Link href="/job-offers/title-exclusions" className="px-4 py-2 rounded-md shadow flex items-center gap-2 bg-[#0C647A] hover:bg-[#0a5466] text-white">
          <ShieldOff className="w-4 h-4" /> Exclusión por título (roles no TI)
        </Link>
      </div>
      {exporting && <div className="mb-4"><ProcessBar process={exportProc} /></div>}

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
                  {item.ti_status && item.ti_status !== 'pendiente' && !item.title_exclusion && (
                    <span
                      className={`ml-2 px-1.5 py-0.5 text-[10px] rounded ${TI_BADGE[item.ti_status] ?? ''}`}
                      title={`Clasificación TI: ${TI_LABEL[item.ti_status] ?? item.ti_status} (origen: ${item.ti_origin ?? '-'})`}
                    >
                      {TI_LABEL[item.ti_status] ?? item.ti_status}
                    </span>
                  )}
                  {item.title_exclusion && (
                    <span
                      className="ml-2 px-1.5 py-0.5 text-[10px] rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300"
                      title={`Excluida de los indicadores por el patrón "${item.title_exclusion.pattern}" (${item.title_exclusion.category ?? 'sin categoría'})`}
                    >
                      excluida
                    </span>
                  )}
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
