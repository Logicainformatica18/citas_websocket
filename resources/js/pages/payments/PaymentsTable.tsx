import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';

import OcrTextModal from './OcrTextModal';
import PaymentDetailModal from './PaymentDetailModal';

import { Search, RefreshCcw, Pencil, X } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pagos', href: '/payments/table' }];

type ProjectMini = { id_proyecto: number; descripcion: string };

type Payment = {
  id: number;
  dni: string;
  full_name: string;
  amount: number | string;
  operation_number?: string | null;
  receipt_number?: string | null;
  transaction_code?: string | null;
  project_id: number | null;
  file_1?: string | null;
  created_at: string;
  project?: ProjectMini;
  state?: 'registrado' | 'validado' | 'observado' | string;
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

// helpers fuera del componente
const isImage = (name?: string | null) => !!name && /\.(jpe?g|png|webp|gif)$/i.test(name);
const isPdf = (name?: string | null) => !!name && /\.pdf$/i.test(name);

export default function PaymentsIndex() {
  const { payments: initialPagination, filters } = usePage<PageProps>().props;

  const [rows, setRows] = useState<Payment[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [q, setQ] = useState<string>(filters?.q ?? '');
  const [perPage, setPerPage] = useState<number>(filters?.per_page ?? initialPagination.per_page ?? 10);

  // modal editar/detalle
  const [editOpen, setEditOpen] = useState(false);
  const [editPaymentId, setEditPaymentId] = useState<number | null>(null);

  // modal de previsualización grande
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewSrc, setPreviewSrc] = useState<string | null>(null);

  const fetchPage = async (url: string) => {
    try {
      const u = new URL(url, window.location.origin);
      if (q) u.searchParams.set('q', q);
      if (perPage) u.searchParams.set('per_page', String(perPage));

      const res = await axios.get(u.pathname + u.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      setRows(res.data.data);
      setPagination(res.data);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

  const doSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    await fetchPage(`/payments/table/paginate?page=1`);
  };

  const clearSearch = async () => {
    setQ('');
    await fetchPage(`/payments/table/paginate?page=1`);
  };

  const getOpNumber = (p: Payment) =>
    p.operation_number || p.receipt_number || p.transaction_code || '—';

  const formatAmount = (a: number | string) => `S/ ${Number(a ?? 0).toFixed(2)}`;

  const stateBadge = (state?: string) => {
    const s = (state || 'registrado').toLowerCase();
    const map: Record<string, string> = {
      validado: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      observado: 'bg-amber-50 text-amber-700 border-amber-200',
      registrado: 'bg-gray-100 text-gray-700 border-gray-200',
    };
    return (
      <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${map[s] ?? map.registrado}`}>
        {s}
      </span>
    );
  };

  const openEdit = (id: number) => {
    setEditPaymentId(id);
    setEditOpen(true);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      {/* contenedor a pantalla completa */}
      <div className="h-screen flex flex-col">
        {/* top bar */}
        <div className="shrink-0 p-4 md:p-6 border-b bg-white">
          <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
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

              <button type="submit" className="rounded-md bg-blue-600 px-3 py-2 text-white text-sm">
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
        </div>

        {/* tabla (ocupa todo el resto) */}
        <div className="flex-1 overflow-auto p-4 md:p-6">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 bg-white shadow rounded">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-3 py-2 text-left">Acciones</th>
                  <th className="px-3 py-2 text-left">DNI</th>
                  <th className="px-3 py-2 text-left">Cliente</th>
                  <th className="px-3 py-2 text-right">Monto</th>
                  <th className="px-3 py-2 text-left">N.º Operación</th>
                  <th className="px-3 py-2 text-left">Proyecto</th>
                  <th className="px-3 py-2 text-center">Voucher</th>
                  <th className="px-3 py-2 text-left">Estado</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-3 py-6 text-center text-gray-500">
                      Sin resultados
                    </td>
                  </tr>
                )}

                {rows.map((p) => (
                  <tr key={p.id} className="hover:bg-gray-50">
                    {/* acciones */}
                    <td className="px-3 py-2">
                      <button
                        onClick={() => openEdit(p.id)}
                        className="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2.5 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100"
                        title="Editar / Ver detalles"
                      >
                        <Pencil className="h-4 w-4" /> Editar
                      </button>
                    </td>

                    <td className="px-3 py-2">{p.dni}</td>
                    <td className="px-3 py-2">{p.full_name}</td>
                    <td className="px-3 py-2 text-right">{formatAmount(p.amount)}</td>
                    <td className="px-3 py-2">{getOpNumber(p)}</td>
                    <td className="px-3 py-2">{p.project?.descripcion ?? (p.project_id ? `#${p.project_id}` : '—')}</td>

                    {/* voucher: preview grande en hover + modal al clic */}
                    <td className="px-3 py-2 text-center">
                      {p.file_1 ? (
                        <div className="relative inline-block group">
                          <button
                            type="button"
                            onClick={() => {
                              setPreviewSrc(`/uploads/payments/${p.file_1}`);
                              setPreviewOpen(true);
                            }}
                            className="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-900 shadow hover:bg-gray-200"
                            title="Pasa el mouse para previsualizar. Clic para ampliar"
                          >
                            Ver
                          </button>

                          {/* hover preview grande con scroll */}
                          <div className="pointer-events-auto absolute left-1/2 top-full z-30 hidden -translate-x-1/2 pt-2 group-hover:block">
                            <div className="w-[420px] md:w-[520px] max-h-[70vh] overflow-auto rounded-lg border bg-white p-2 shadow-xl">
                              {isImage(p.file_1) ? (
                                <img
                                  src={`/uploads/payments/${p.file_1}`}
                                  alt="Voucher"
                                  className="max-h-[68vh] w-full object-contain rounded-md"
                                />
                              ) : isPdf(p.file_1) ? (
                                <div className="p-3 text-xs text-gray-600">
                                  Es un PDF. Haz clic en <strong>Ver</strong> para abrirlo grande.
                                </div>
                              ) : (
                                <div className="p-3 text-xs text-gray-600">Vista previa no disponible.</div>
                              )}
                            </div>
                          </div>
                        </div>
                      ) : (
                        <span className="text-gray-400">—</span>
                      )}
                    </td>

                    {/* estado */}
                    <td className="px-3 py-2">{stateBadge(p.state)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* paginación */}
          {pagination.last_page > 1 && (
            <div className="flex justify-center mt-4 gap-2">
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
      </div>

      {/* modal preview grande */}
      {previewOpen && previewSrc && (
        <div className="fixed inset-0 z-50">
          <div className="absolute inset-0 bg-black/50" onClick={() => setPreviewOpen(false)} />
          <div className="absolute inset-0 flex items-center justify-center p-4">
            <div className="relative w-full max-w-5xl rounded-xl bg-white shadow-2xl ring-1 ring-gray-100 p-3">
              <button
                onClick={() => setPreviewOpen(false)}
                className="absolute right-2 top-2 rounded-md p-1 hover:bg-gray-100"
                aria-label="Cerrar"
              >
                <X className="h-5 w-5" />
              </button>

              <div className="max-h-[85vh] overflow-auto">
                {isImage(previewSrc) ? (
                  <img src={previewSrc} alt="Voucher" className="max-h-[83vh] w-full object-contain rounded-md" />
                ) : isPdf(previewSrc) ? (
                  <object data={previewSrc} type="application/pdf" className="h-[83vh] w-full rounded-md">
                    <p className="p-4 text-sm">
                      Tu navegador no pudo mostrar el PDF.{' '}
                      <a href={previewSrc} className="text-blue-600 underline">Abrir en nueva pestaña</a>.
                    </p>
                  </object>
                ) : (
                  <div className="p-4">No se puede previsualizar este archivo.</div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* modal OCR existente (si lo usas desde otros botones) */}
      <OcrTextModal />

      {/* modal detalles/edición */}
      <PaymentDetailModal
        open={editOpen}
        paymentId={editPaymentId}
        onClose={() => setEditOpen(false)}
        onUpdated={() => fetchPage(`/payments/table/paginate?page=${pagination.current_page}`)}
      />
    </AppLayout>
  );
}
