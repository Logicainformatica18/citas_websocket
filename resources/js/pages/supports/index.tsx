import Echo from '@/lib/echo';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import SupportModal from './modal';
import SupportTable from './table';
import ChatWidget from '@/components/ChatWidget';
import NotificationBell from '@/components/NotificationBell';



const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Atenciones', href: '/supports' },
];

type Support = {
  id: number;
  subject: string;
  description?: string;
  priority: string;
  type: string;
  status: string;
  attachment?: string;
  reservation_time?: string;
  attended_at?: string;
  derived?: string;
  cellphone?: string;
  created_at?: string;
  updated_at?: string;
  area_id?: number;
  created_by?: number;
  client_id?: number;
  project_id?: number;
  Manzana?: string;
  Lote?: string;

  // Campos adicionales del modal (si los necesitas para edición)
  dni?: string;
  email?: string;
  address?: string;
  id_motivos_cita?: number;
  id_tipo_cita?: number;
  id_dia_espera?: number;
  internal_state_id?: number;
  external_state_id?: number;
  type_id?: number;
};

type Option = {
  id: number;
  descripcion?: string;
  nombre_motivo?: string;
  tipo?: string;
  dias?: string;
  description?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
};

export default function Supports() {
  const {
    supports: initialPagination,
    motives,
    appointmentTypes,
    waitingDays,
    internalStates,
    externalStates,
    types,
    projects,
  } = usePage<{
    supports: Pagination<Support>;
    motives: Option[];
    appointmentTypes: Option[];
    waitingDays: Option[];
    internalStates: Option[];
    externalStates: Option[];
    types: Option[];
    projects: Option[];
  }>().props;

  const [supports, setSupports] = useState<Support[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editSupport, setEditSupport] = useState<Support | null>(null);

  useEffect(() => {
    const channel = Echo.channel('supports');

    channel.listen('.record.changed', (e: any) => {
      console.log('📡 Evento recibido:', e);
      if (e.model === 'Support') {
        switch (e.action) {
          case 'created':
            setSupports((prev) => {
              const exists = prev.some((s) => s.id === e.data.id);
              return exists ? prev : [e.data, ...prev];
            });
            break;
          case 'updated':
            setSupports((prev) => prev.map((s) => (s.id === e.data.id ? e.data : s)));
            break;
          case 'deleted':
            setSupports((prev) => prev.filter((s) => s.id !== e.data.id));
            break;
        }
      }
    });

    return () => {
      Echo.leave('supports');
    };
  }, []);

  const handleSupportSaved = (saved: Support) => {
    setSupports((prev) => {
      const exists = prev.find((p) => p.id === saved.id);
      return exists ? prev.map((p) => (p.id === saved.id ? saved : p)) : [saved, ...prev];
    });
    setEditSupport(null);
  };

  const fetchSupport = async (id: number) => {
    const res = await axios.get(`/supports/${id}`);
    setEditSupport(res.data.support);
    setShowModal(true);
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setSupports(res.data.supports.data);
      setPagination(res.data.supports);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
        <div className="flex justify-end items-center gap-4">
  <NotificationBell />
  {/* otros elementos como el usuario */}
</div>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">Listado de Atenciones</h1>

        <button
          onClick={() => {
            setEditSupport(null);
            setShowModal(true);
          }}
          className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          Nuevo Soporte
        </button>

        <SupportTable
          supports={supports}
          setSupports={setSupports}
          pagination={pagination}
          fetchPage={fetchPage}
          fetchSupport={fetchSupport}
        />
      </div>

      {showModal && (
        <SupportModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditSupport(null);
          }}
          onSaved={handleSupportSaved}
          supportToEdit={editSupport}
          motives={motives}
          appointmentTypes={appointmentTypes}
          waitingDays={waitingDays}
          internalStates={internalStates}
          externalStates={externalStates}
          types={types}
          projects={projects}
        />
      )}
      <ChatWidget />
    </AppLayout>
  );
}
