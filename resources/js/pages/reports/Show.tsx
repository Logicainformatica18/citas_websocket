import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types';

export default function ReportShow({ support }: PageProps<{ support: any }>) {
  const breadcrumbs = [
    { title: 'Soportes', href: '/supports' },
    { title: `Reporte #${support.id}`, href: `/reports/${support.id}` },
  ];

  return (
    <AppLayout title={`Reporte de Soporte #${support.id}`} breadcrumbs={breadcrumbs}>
      <div className="p-8 bg-white rounded shadow space-y-6">
        <h1 className="text-2xl font-bold">Reporte de Soporte #{support.id}</h1>

        {/* Grupo: Información del Soporte */}
        <div className="border rounded p-4 space-y-2">
          <h2 className="font-semibold text-lg mb-2">Información del Soporte</h2>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div><strong>Asunto:</strong> {support.subject}</div>
            <div><strong>Descripción:</strong> {support.description}</div>
            <div><strong>Prioridad:</strong> {support.priority}</div>
            <div><strong>Tipo:</strong> {support.support_type?.description}</div>
            <div><strong>Derivado:</strong> {support.derived || '-'}</div>
            <div><strong>Fecha de Reserva:</strong> {support.reservation_time}</div>
            <div><strong>Atendido en:</strong> {support.attended_at}</div>
          </div>
        </div>

        {/* Grupo: Información del Cliente */}
        <div className="border rounded p-4 space-y-2">
          <h2 className="font-semibold text-lg mb-2">Información del Cliente</h2>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div><strong>Nombre:</strong> {support.client?.names}</div>
            <div><strong>DNI:</strong> {support.client?.dni}</div>
            <div><strong>Email:</strong> {support.client?.email}</div>
            <div><strong>Teléfono:</strong> {support.client?.cellphone}</div>
            <div><strong>Dirección:</strong> {support.client?.address}</div>
          </div>
        </div>

        {/* Grupo: Información del Proyecto y Ubicación */}
        <div className="border rounded p-4 space-y-2">
          <h2 className="font-semibold text-lg mb-2">Proyecto y Ubicación</h2>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div><strong>Proyecto:</strong> {support.project?.descripcion}</div>
            <div><strong>Área:</strong> {support.area?.descripcion}</div>
            <div><strong>Manzana:</strong> {support.Manzana}</div>
            <div><strong>Lote:</strong> {support.Lote}</div>
          </div>
        </div>

        {/* Grupo: Estados */}
        <div className="border rounded p-4 space-y-2">
          <h2 className="font-semibold text-lg mb-2">Estados</h2>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div><strong>Estado Interno:</strong> {support.internal_state?.description}</div>
            <div><strong>Estado Externo:</strong> {support.external_state?.description}</div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
