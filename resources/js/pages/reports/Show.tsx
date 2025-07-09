import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function ReportShow({ support }: PageProps<{ support: any }>) {
  const [loading, setLoading] = useState(true);

  // Para navegación directa con Inertia
  useEffect(() => {
    if (support && support.details) {
      setLoading(false);
    }
  }, [support]);

  const breadcrumbs = [
    { title: 'Soportes', href: '/supports' },
    { title: `Reporte #${support.id}`, href: `/reports/${support.id}` },
  ];

  return (
    <AppLayout title={`Reporte de Soporte #${support.id}`} breadcrumbs={breadcrumbs}>
      <Head title={`Reporte #${support.id}`} />

      <div className="p-8 bg-white rounded shadow space-y-6 print:p-0 print:shadow-none print:bg-white">

        {/* ✅ Cargando... */}
        {loading ? (
          <div className="text-center text-gray-600">Cargando reporte...</div>
        ) : (
          <>
            {/* ✅ Botón de Imprimir */}
            <div className="flex justify-end print:hidden">
              <button
                onClick={() => window.print()}
                className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                🖨 Imprimir
              </button>
            </div>

            {/* ✅ Título */}
            <h1 className="text-2xl font-bold">Reporte de Solicitud #{support.id}</h1>

            {/* ✅ Datos del Cliente */}
            <div className="border rounded p-4 space-y-2">
              <h2 className="font-semibold text-lg mb-2">Información del Cliente</h2>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div><strong>Razón Social:</strong> {support.client?.names}</div>
                <div><strong>Teléfono:</strong> {support.client?.cellphone}</div>
                <div><strong>Estado Global:</strong> {support.status_global}</div>
                <div><strong>Creado por:</strong> {support.creator?.names}</div>
              </div>
            </div>

            {/* ✅ Detalles uno por uno */}
            {support.details.map((detail: any, index: number) => (
              <div
                key={detail.id}
                className="border p-6 space-y-4 bg-white shadow mb-6 break-before-page print:shadow-none print:mb-0"
              >
                <h2 className="text-xl font-bold mb-2">Solicitud 0{index + 1}</h2>

                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div><strong>Asunto:</strong> {detail.subject}</div>
                  <div><strong>Descripción:</strong> {detail.description}</div>
                  <div><strong>Prioridad:</strong> {detail.priority}</div>
                  <div><strong>Estado:</strong> {detail.status}</div>
                  <div><strong>Área:</strong> {detail.area?.descripcion || '-'}</div>
                  <div><strong>Proyecto:</strong> {detail.project?.descripcion || '-'}</div>
                  <div><strong>Motivo de Cita:</strong> {detail.motivo_cita?.nombre_motivo || '-'}</div>
                  <div><strong>Tipo de Cita:</strong> {detail.tipo_cita?.tipo || '-'}</div>
                  <div><strong>Día de Espera:</strong> {detail.dia_espera?.dias || '-'}</div>
                  <div><strong>Estado Interno:</strong> {detail.internal_state?.description || '-'}</div>
                  <div><strong>Estado Externo:</strong> {detail.external_state?.description || '-'}</div>
                  <div><strong>Tipo de Solicitud:</strong> {detail.support_type?.description || '-'}</div>
                  <div><strong>Fecha de Reserva:</strong> {detail.reservation_time ?? '-'}</div>
                  <div><strong>Derivado:</strong> {detail.derived ?? '-'}</div>
                  <div><strong>Manzana:</strong> {detail.Manzana ?? '-'}</div>
                  <div><strong>comment:</strong> {detail.comment ?? '-'}</div>

                  {detail.attachment && (
                    <div className="col-span-2">
                      <strong>Archivo:</strong>{' '}
                      <a
                        href={`/uploads/${detail.attachment}`}
                        className="text-blue-600 underline"
                        target="_blank"
                      >
                        {detail.attachment}
                      </a>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </>
        )}
      </div>
    </AppLayout>
  );
}
