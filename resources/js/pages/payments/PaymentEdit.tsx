import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

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
  file_1?: string | null;
  file_3?: string | null;   // OCR guardado como texto
  created_at: string;
  project?: ProjectMini;
};

type PageProps = {
  payment: Payment;
  projects: ProjectMini[];
};

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Pagos', href: '/payments/table' },
  { title: 'Editar', href: '#' },
];

export default function PaymentEdit({ payment, projects }: PageProps) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 space-y-6">
        <h1 className="text-2xl font-bold">Detalle del pago #{payment.id}</h1>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="Titular" value={payment.full_name} />
          <Field label="DNI" value={payment.dni} />
          <Field label="Email" value={payment.email} />
          <Field label="Importe" value={`S/ ${Number(payment.amount || 0).toFixed(2)}`} />
          <Field label="N° Operación" value={payment.receipt_number ?? '-'} />
          <Field label="Fecha" value={payment.date ?? '-'} />
          <Field label="Código cliente" value={payment.code_client ?? '-'} />
          <Field label="Proyecto" value={payment.project?.descripcion ?? '-'} />
          <Field label="MZ/Lote" value={payment.mz_lote ?? '-'} />
          <Field label="Creado" value={new Date(payment.created_at).toLocaleString()} />

          {/* Voucher */}
          <div className="flex flex-col">
            <span className="text-sm font-medium text-gray-600">Voucher</span>
            {payment.file_1 ? (
              <a
                href={`/uploads/payments/${payment.file_1}`}
                target="_blank"
                rel="noopener noreferrer"
                className="text-blue-600 hover:underline"
              >
                Descargar
              </a>
            ) : (
              <span className="text-gray-500">-</span>
            )}
          </div>
        </div>

        {/* Texto OCR */}
        <div>
          <h2 className="text-lg font-semibold mt-6 mb-2">Texto OCR (file_3)</h2>
          <pre className="whitespace-pre-wrap bg-gray-50 p-4 rounded-md border border-gray-200 text-sm">
            {payment.file_3 ?? 'Sin texto OCR'}
          </pre>
        </div>

        <div className="flex gap-3 mt-6">
          <button
            type="button"
            onClick={() => window.history.back()}
            className="rounded-md bg-gray-100 px-4 py-2 text-sm"
          >
            Volver
          </button>
        </div>
      </div>
    </AppLayout>
  );
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col">
      <span className="text-sm font-medium text-gray-600">{label}</span>
      <span className="text-gray-900">{value}</span>
    </div>
  );
}
