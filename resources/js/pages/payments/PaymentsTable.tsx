import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';
import { Trash2, Search, RefreshCcw, Eye, Pencil, Paperclip } from 'lucide-react';
import OcrTextModal from './OcrTextModal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Pagos', href: '/payments/table' },
];

type ProjectMini = { id_proyecto: number; descripcion: string };

type Payment = {
  id: number;
  email: string;
  dni: string;
  full_name: string;
  receipt_number: string | null;
  amount: number | string;
  project_id: number | null;
  mz_lote: string | null;
  date: string | null;
  code_client: string | null;
  file_1?: string | null; // voucher
  created_at: string;
  project?: ProjectMini;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
  total: number;
  per_page: number;
};

type PageProps = {
  payments: Pagination<Payment>;
  filters?: { q?: string; per_page?: number };
};

export default function PaymentsIndex() {
  const { payments: initialPagination, filters } = usePage<PageProps>().props;

  const [rows, setRows] = useState<Payment[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [q, setQ] = useState<string>(filters?.q ?? '');
  const [perPage, setPerPage] = useState<number>(filters?.per_page ?? initialPagination.per_page ?? 10);

  const allChecked = useMemo(
    () => rows.length > 0 && selectedIds.length === rows.length,
    [rows, selectedIds]
  );

  const csrf =
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

  const fetchPage = async (url: string) => {
    try {
      const u = new URL(url, window.location.origin);
      if (q) u.searchParams.set('q', q);
      if (perPage) u.searchParams.set('per_page', String(perPage));

      const res = await axios.get(u.pathname + u.search, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      setRows(res.data.data);
      setPagination(res.data);
      setSelectedIds([]);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

  const reloadFirstPage = () => {
    fetchPage(`/payments/table/paginate?page=1`);
  };

  const doSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    await fetchPage(`/payments/table/paginate?page=1`);
  };

  const clearSearch = async () => {
    setQ('');
    await fetchPage(`/payments/table/paginate?page=1`);
  };

  const toggleSelectAll = () => {
    if (allChecked) setSelectedIds([]);
    else setSelectedIds(rows.map((r) => r.id));
  };

  const toggleSelect = (id: number) => {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  };

  const deleteOne = async (id: number, label?: string) => {
    if (!confirm(`¿Eliminar el pago${label ? ` de "${label}"` : ''}?`)) return;
    try {
      await axios.delete(`/payments/table/${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      reloadFirstPage();
    } catch (e) {
      alert('Error al eliminar');
      console.error(e);
    }
  };

  const bulkDelete = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`¿Eliminar ${selectedIds.length} pago(s)?`)) return;
    try {
      await axios.post(
        `/payments/table/bulk-delete`,
        { ids: selectedIds },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
        }
      );
      reloadFirstPage();
    } catch (e) {
      alert('Error al eliminar en lote');
      console.error(e);
    }
  };

  // Ver OCR (file_3) en modal externo: dispara un CustomEvent
  const viewOCR = async (id: number) => {
    try {
      const res = await axios.get(`/payments/table/${id}/edit`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      });
      const text: string = res.data?.payment?.file_3 ?? '';
      window.dispatchEvent(new CustomEvent('open-ocr-modal', { detail: { paymentId: id, text } }));
    } catch (e) {
      alert('No se pudo obtener el OCR.');
      console.error(e);
    }
  };

  const goEdit = (id: number) => {
    // Solo vista de edición (no hay update)
    window.location.href = `/payments/table/${id}/edit`;
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <h1 className="text-2xl font-bold">Pagos registrados</h1>

          <form onSubmit={doSearch} className="flex items-center gap-2">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                className="w-72 rounded-md border border-gray-300 pl-9 pr-3 py-2 text-sm"
                placeholder="Buscar: DNI, nombre, email, operación, código cliente"
                value={q}
                onChange={(e) => setQ(e.target.value)}
              />
            </div>

            <select
              className="rounded-md border border-gray-300 px-2 py-2 text-sm"
              value={perPage}
              onChange={async (e) => {
                const n = Number(e.target.value);
                setPerPage(n);
                await fetchPage(`/payments/table/paginate?page=1`);
              }}
            >
              {[10, 20, 50, 100].map((n) => (
                <option key={n} value={n}>
                  {n} / pág.
                </option>
              ))}
            </select>

            <button
              type="submit"
              className="rounded-md bg-blue-600 px-3 py-2 text-white text-sm"
            >
              Buscar
            </button>
            <button
              type="button"
              onClick={clearSearch}
              className="rounded-md bg-gray-100 px-3 py-2 text-sm inline-flex items-center gap-2"
            >
              <RefreshCcw className="h-4 w-4" /> Limpiar
            </button>
          </form>
        </div>

        {selectedIds.length > 0 && (
          <button
            onClick={bulkDelete}
            className="mb-3 inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-2 text-white text-sm"
          >
            <Trash2 className="h-4 w-4" /> Eliminar seleccionados ({selectedIds.length})
          </button>
        )}

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-3 py-2">
                  <input type="checkbox" checked={allChecked} onChange={toggleSelectAll} />
                </th>
                <th className="px-3 py-2">Acciones</th>
                <th className="px-3 py-2 text-left">ID</th>
                <th className="px-3 py-2 text-left">Fecha</th>
                <th className="px-3 py-2 text-left">Código cliente</th>
                <th className="px-3 py-2 text-left">Titular</th>
                <th className="px-3 py-2 text-left">DNI</th>
                <th className="px-3 py-2 text-left">Email</th>
                <th className="px-3 py-2 text-right">Importe</th>
                <th className="px-3 py-2 text-left">Operación</th>
                <th className="px-3 py-2 text-left">Proyecto</th>
                <th className="px-3 py-2 text-left">MZ/Lote</th>
                <th className="px-3 py-2 text-left">Voucher</th>
                <th className="px-3 py-2 text-left">Creado</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr>
                  <td colSpan={14} className="px-3 py-6 text-center text-gray-500">
                    Sin resultados
                  </td>
                </tr>
              )}

              {rows.map((p) => (
                <tr key={p.id} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td className="px-3 py-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(p.id)}
                      onChange={() => toggleSelect(p.id)}
                    />
                  </td>

                  {/* Acciones: Editar, Ver OCR, Eliminar */}
                  <td className="px-3 py-2 text-sm space-x-2 whitespace-nowrap">
                    <button
                      onClick={() => goEdit(p.id)}
                      className="text-blue-600 hover:underline dark:text-blue-400 inline-flex items-center gap-1"
                      title="Editar (vista sin update)"
                    >
                      <Pencil className="w-4 h-4" /> Editar
                    </button>
                    <button
                      onClick={() => viewOCR(p.id)}
                      className="text-emerald-700 hover:underline inline-flex items-center gap-1"
                      title="Ver OCR (file_3)"
                    >
                      <Eye className="w-4 h-4" /> Ver OCR
                    </button>
                    <button
                      onClick={() => deleteOne(p.id, p.full_name)}
                      className="text-red-600 hover:underline dark:text-red-400 inline-flex items-center gap-1"
                      title="Eliminar"
                    >
                      <Trash2 className="w-4 h-4" /> Eliminar
                    </button>
                  </td>

                  <td className="px-3 py-2">{p.id}</td>
                  <td className="px-3 py-2">{p.date ?? '-'}</td>
                  <td className="px-3 py-2">{p.code_client ?? '-'}</td>
                  <td className="px-3 py-2">{p.full_name}</td>
                  <td className="px-3 py-2">{p.dni}</td>
                  <td className="px-3 py-2">{p.email}</td>
                  <td className="px-3 py-2 text-right">S/ {Number(p.amount || 0).toFixed(2)}</td>
                  <td className="px-3 py-2">{p.receipt_number ?? '-'}</td>
                  <td className="px-3 py-2">{p.project?.descripcion ?? '-'}</td>
                  <td className="px-3 py-2">{p.mz_lote ?? '-'}</td>

                  {/* Voucher link */}
                  <td className="px-3 py-2">
                    {p.file_1 ? (
                      <a
                        href={`/uploads/payments/${p.file_1}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                        title={p.file_1 ?? 'Voucher'}
                      >
                        <Paperclip className="w-4 h-4" /> Descargar
                      </a>
                    ) : (
                      '-'
                    )}
                  </td>

                  <td className="px-3 py-2">{new Date(p.created_at).toLocaleString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Paginación numérica */}
        {pagination.last_page > 1 && (
          <div className="flex justify-center mt-6 space-x-2">
            {[...Array(pagination.last_page)].map((_, i) => {
              const page = i + 1;
              const url = `/payments/table/paginate?page=${page}`;
              const active = pagination.current_page === page;
              return (
                <button
                  key={page}
                  onClick={() => fetchPage(url)}
                  className={`px-3 py-1 rounded text-sm font-medium transition ${
                    active ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                  }`}
                  disabled={active}
                >
                  {page}
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* Modal global para ver file_3 (OCR) */}
      <OcrTextModal />
    </AppLayout>
  );
}
