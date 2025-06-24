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
import AreaModal from './AreaModal';



const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Solicitudes', href: '/supports' },
];

type SupportDetail = {
    id: number;
    subject: string;
    description?: string;
    priority?: string;
    type?: string;
    status?: string;
    reservation_time?: string;
    attended_at?: string;
    derived?: string;
    project?: { descripcion: string };
    area?: { descripcion: string };
    motivoCita?: { nombre_motivo: string };
    tipoCita?: { tipo: string };
    diaEspera?: { dias: string };
    internalState?: { description: string };
    externalState?: { description: string };
    supportType?: { description: string };
};

type Support = {
    id: number;
    client_id: number;
    created_by: number;
    cellphone?: string;
    state: string;
    status_global: string;
    created_at?: string;
    updated_at?: string;
    client?: { Razon_Social: string; telefono: string; email: string; };
    creator?: { names: string };
    details: SupportDetail[];
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
        areas,
    } = usePage<{
        supports: Pagination<Support>;
        motives: { id: number; nombre_motivo: string }[]; // 👈 más específico
        appointmentTypes: Option[];
        waitingDays: Option[];
        internalStates: { id: number; description: string }[];
        externalStates: Option[];
        types: Option[];
        projects: Option[];
        areas: { id_area: number; descripcion: string }[]; // 👈 más específico

    }>().props;


    const [supports, setSupports] = useState<Support[]>(initialPagination.data);
    const [pagination, setPagination] = useState(initialPagination);
    const [showModal, setShowModal] = useState(false);
    const [editSupport, setEditSupport] = useState<Support | null>(null);
  //  const [selectedSupportId, setSelectedSupportId] = useState<number | null>(null);
   // const [selectedDetailSupportId, setSelectedDetailSupportId] = useState<number | null>(null);

// index.tsx
const [selectedSupportId, setSelectedSupportId] = useState<number | null>(null);
const [selectedDetailSupportId, setSelectedDetailSupportId] = useState<number | null>(null);
const [supportDetailToEdit, setSupportDetailToEdit] = useState<SupportDetail | null>(null);
const [showAreaModal, setShowAreaModal] = useState(false);


   // const [supportDetailToEdit, setSupportDetailToEdit] = useState<SupportDetail | null>(null);

   // const [showAreaModal, setShowAreaModal] = useState(false);
    const [highlightedIds, setHighlightedIds] = useState<number[]>([]);
    const [expanded, setExpanded] = useState<number[]>([]);

    const toggleExpand = (id: number) => {
        setExpanded((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
        );
    };



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

                        setHighlightedIds((prev) => {
                            if (!prev.includes(e.data.id)) return [...prev, e.data.id];
                            return prev;
                        });

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
    const exportSupports = () => {
        window.open(route('supports.export'), '_blank');
    };


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="flex justify-end items-center gap-4">
                <NotificationBell />
                {/* otros elementos como el usuario */}
            </div>
            <div className="p-2">
                <h1 className="text-2xl font-bold mb-4">Listado de Solicitudes</h1>

                <button
                    onClick={() => {
                        setEditSupport(null);
                        setShowModal(true);
                    }}
                    className="mb-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                >
                    Nuevo Registro
                </button>

                <button onClick={exportSupports} className="ml-4 mb-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-red-700 transition"
                >Exportar Todo</button>


                <SupportTable
                    supports={supports}
                    setSupports={setSupports}
                    pagination={pagination}
                    fetchPage={fetchPage}
                    fetchSupport={fetchSupport}
                    areas={areas}
                    motives={motives}
                    internalStates={internalStates}
                    highlightedIds={highlightedIds}
                    expanded={expanded}
                    toggleExpand={toggleExpand}
                    setEditSupport={setEditSupport}
                    setShowModal={setShowModal}
                    // setSelectedSupportId={setSelectedSupportId} // 👈
                    // setSupportDetailToEdit={setSupportDetailToEdit} // 👈
                    // setShowAreaModal={setShowAreaModal} // 👈

                      setSelectedSupportId={setSelectedSupportId}
  setSelectedDetailSupportId={setSelectedDetailSupportId}
  setSupportDetailToEdit={setSupportDetailToEdit}
  setShowAreaModal={setShowAreaModal}
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
                    areas={areas}
                />
            )}

<AreaModal
  open={showAreaModal}
  onClose={() => setShowAreaModal(false)}
  supportId={selectedSupportId}
  supportDetailId={selectedDetailSupportId} // 👈 AQUÍ SE RECIBE
  detail={supportDetailToEdit}
  areas={areas}
  motives={motives}
  internalStates={internalStates}
 onUpdated={() => {
  if (selectedSupportId) {
    fetchSupport(selectedSupportId)
      .catch((err) => {
        console.error('Error al actualizar soporte:', err);
       // toast.error('Error al actualizar el soporte.');
      });
  }
}}
/>







            <ChatWidget />
        </AppLayout>
    );
}
