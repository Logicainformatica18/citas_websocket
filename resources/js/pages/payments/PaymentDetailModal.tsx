import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';
import { X, Mail, Calendar, CreditCard, Hash, User, FileText, Image as Img, BadgeCheck } from 'lucide-react';

type Props = {
  open: boolean;
  paymentId: number | null;
  onClose: () => void;
  onUpdated?: () => void;
};

type PaymentFull = {
  id: number;
  email: string;
  dni: string;
  full_name: string;
  amount: number | string;
  date?: string | null;
  code_client?: string | null;
  channel?: string | null;
  operation_number?: string | null;
  receipt_number?: string | null;
  transaction_code?: string | null;
  sale_id?: string | null;
  company_name?: string | null;
  commerce_name?: string | null;
  currency?: string | null;
  mz_lote?: string | null;
  project_id?: number | null;
  project?: { id_proyecto: number; descripcion: string };
  file_1?: string | null;
  file_3?: string | null; // OCR
  state?: 'registrado' | 'validado' | 'observado' | string;
  created_at?: string;
};

export default function PaymentDetailModal({ open, paymentId, onClose }: Props) {
  const [loading, setLoading] = useState(false);
  const [p, setP] = useState<PaymentFull | null>(null);

  useEffect(() => {
    if (!open || !paymentId) return;
    (async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/payments/table/${paymentId}/edit`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        setP(res.data?.payment ?? res.data ?? null);
      } catch (e) {
        console.error(e);
        setP(null);
      } finally {
        setLoading(false);
      }
    })();
  }, [open, paymentId]);

  const isImage = (name?: string | null) => !!name && /\.(jpe?g|png|webp|gif)$/i.test(name);
  const amountFmt = useMemo(() => (p ? `S/ ${Number(p.amount ?? 0).toFixed(2)}` : '—'), [p]);

  const stateBadge = (state?: string) => {
    const s = (state || 'registrado').toLowerCase();
    const map: Record<string, string> = {
      validado: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      observado: 'bg-amber-50 text-amber-700 border-amber-200',
      registrado: 'bg-gray-100 text-gray-700 border-gray-200',
    };
    return (
      <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium ${map[s] ?? map['registrado']}`}>
        <BadgeCheck className="h-3 w-3" /> {s}
      </span>
    );
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div className="absolute inset-0 flex items-center justify-center p-4">
        <div className="w-full max-w-3xl rounded-xl bg-white shadow-2xl ring-1 ring-gray-100">
          <div className="flex items-center justify-between border-b px-4 py-3">
            <h3 className="text-lg font-semibold">Detalle del pago {p ? `#${p.id}` : ''}</h3>
            <button onClick={onClose} className="rounded-md p-1 hover:bg-gray-100">
              <X className="h-5 w-5" />
            </button>
          </div>

          <div className="max-h-[75vh] overflow-auto p-4">
            {loading && <div className="py-10 text-center text-gray-500">Cargando…</div>}
            {!loading && !p && <div className="py-10 text-center text-red-600">No se pudo cargar el pago.</div>}

            {!loading && p && (
              <div className="space-y-6">
                {/* Cabecera */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <div className="text-sm text-gray-500">Cliente</div>
                    <div className="flex items-center gap-2 font-medium"><User className="h-4 w-4" /> {p.full_name}</div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500">DNI</div>
                    <div className="font-medium">{p.dni}</div>
                  </div>
                  <div className="flex items-center gap-2 justify-between md:justify-start">
                    {stateBadge(p.state)}
                    <div className="text-lg font-semibold">{amountFmt}</div>
                  </div>
                </div>

                {/* Info principal */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <div className="text-sm text-gray-500">Email</div>
                    <div className="flex items-center gap-2"><Mail className="h-4 w-4" /> {p.email}</div>
                  </div>

                    <div className="space-y-2">
                      <div className="text-sm text-gray-500">Fecha de pago</div>
                      <div className="flex items-center gap-2"><Calendar className="h-4 w-4" /> {p.date ?? '—'}</div>
                    </div>

                    <div className="space-y-2">
                      <div className="text-sm text-gray-500">Medio de pago</div>
                      <div className="flex items-center gap-2"><CreditCard className="h-4 w-4" /> {p.channel ?? '—'}</div>
                    </div>

                    <div className="space-y-2">
                      <div className="text-sm text-gray-500">Proyecto</div>
                      <div>{p.project?.descripcion ?? (p.project_id ? `#${p.project_id}` : '—')}</div>
                    </div>

                    <div className="space-y-2">
                      <div className="text-sm text-gray-500">Operación / Códigos</div>
                      <div className="flex flex-wrap gap-2">
                        {p.operation_number && <span className="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs"><Hash className="h-3 w-3" /> OP: {p.operation_number}</span>}
                        {p.receipt_number && <span className="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs">REC: {p.receipt_number}</span>}
                        {p.transaction_code && <span className="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs">TX: {p.transaction_code}</span>}
                      </div>
                    </div>

                    <div className="space-y-2">
                      <div className="text-sm text-gray-500">Moneda</div>
                      <div>{p.currency ?? 'PEN'}</div>
                    </div>

                    <div className="space-y-2 md:col-span-2">
                      <div className="text-sm text-gray-500">MZ / Lote</div>
                      <div>{p.mz_lote ?? '—'}</div>
                    </div>

                    {/* Voucher */}
                    <div className="md:col-span-2">
                      <div className="text-sm text-gray-500 mb-1">Voucher</div>
                      {p.file_1 ? (
                        isImage(p.file_1) ? (
                          <img
                            src={`/uploads/payments/${p.file_1}`}
                            alt="Voucher"
                            className="max-h-80 w-full object-contain rounded-md border"
                          />
                        ) : (
                          <a
                            href={`/uploads/payments/${p.file_1}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 text-blue-600 hover:underline"
                          >
                            <Img className="h-4 w-4" /> Abrir archivo
                          </a>
                        )
                      ) : (
                        <div className="text-gray-500">—</div>
                      )}
                    </div>

                    {/* OCR */}
                    <div className="md:col-span-2">
                      <div className="text-sm text-gray-500 mb-1">Texto OCR</div>
                      <div className="max-h-40 overflow-auto rounded border bg-gray-50 p-3 text-xs whitespace-pre-wrap">
                        {p.file_3 ?? '—'}
                      </div>
                    </div>
                </div>
              </div>
            )}
          </div>

          <div className="flex justify-end gap-2 border-t px-4 py-3">
            <button onClick={onClose} className="rounded-md bg-gray-100 px-4 py-2 text-gray-800 hover:bg-gray-200">
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
