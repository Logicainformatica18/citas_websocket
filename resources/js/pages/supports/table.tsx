import { Paintbrush, Trash2 } from 'lucide-react';
import HourglassLoader from '@/components/HourglassLoader';
import { useState } from 'react';
import axios from 'axios';
import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import AreaModal from './AreaModal'; // asegúrate de que el path sea correcto
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import React from 'react';
import { Wrench } from 'lucide-react';

interface SupportDetail {
    id: number;
    subject: string;
    description?: string;
    priority?: string;
    type?: string;
    status?: string;
    reservation_time?: string;
    attended_at?: string;
    derived?: string;
    attachment?: string;
    Manzana?: string;
    Lote?: string;
    project?: { descripcion: string };
    area?: { descripcion: string };
    motivo_cita?: { nombre_motivo: string };
    tipo_cita?: { tipo: string };
    dia_espera?: { dias: string };
    internal_state?: { description: string };
    external_state?: { description: string };
    supportType?: { description: string };
}

interface Support {
    id: number;
    state: string;
    status_global: string;
    created_at?: string;
    client?: { Razon_Social: string, telefono?: string, email?: string };
    creator?: { names: string };
    details: SupportDetail[];
}


interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    supports: Support[];
    setSupports: React.Dispatch<React.SetStateAction<Support[]>>;
    pagination: Pagination<Support>;
    fetchPage: (url: string) => Promise<void>;
    fetchSupport: (id: number) => Promise<void>;
    setShowAreaModal: React.Dispatch<React.SetStateAction<boolean>>;
    setSelectedSupportId: React.Dispatch<React.SetStateAction<number | null>>;
    areas: Array<{ id_area: number; descripcion: string }>;
    motives: Array<{ id: number; nombre_motivo: string }>;
    internalStates: Array<{ id: number; description: string }>; // ✅ AÑADIR ESTO
    highlightedIds: number[];
    expanded: number[];
    toggleExpand: (id: number) => void;
    setEditSupport: React.Dispatch<React.SetStateAction<Support | null>>;
    setShowModal: React.Dispatch<React.SetStateAction<boolean>>;

}


type PageProps = {
    permissions: string[];
};

export default function SupportTable({
    supports,
    setSupports,
    pagination,
    fetchPage,
    fetchSupport,
    setShowAreaModal,
    setSelectedSupportId,
    areas,
    motives,
    internalStates, // ✅ AÑADIR ESTO
    highlightedIds,
    expanded,
    toggleExpand,
    setEditSupport,
    setShowModal,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const { permissions } = usePage<PageProps>().props;
    const canEdit = permissions.includes('administrar') || permissions.includes('atc');
    const [supportToEdit, setSupportToEdit] = useState<Support | null>(null);
    const [showSupportModal, setShowSupportModal] = useState(false);
    const [supportDetailToEdit, setSupportDetailToEdit] = useState<SupportDetail | null>(null);


    return (
        <div className="overflow-x-auto mt-4">
            <>
                {selectedIds.length > 0 && (
                    <button
                        onClick={async () => {
                            if (confirm(`¿Eliminar ${selectedIds.length} tickets?`)) {
                                try {
                                    await axios.post('/supports/bulk-delete', { ids: selectedIds });
                                    setSupports((prev) => prev.filter((s) => !selectedIds.includes(s.id)));
                                    setSelectedIds([]);
                                } catch (e) {
                                    alert('Error al eliminar en Lote');
                                    console.error(e);
                                }
                            }
                        }}
                        className="mb-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
                    >
                        Eliminar seleccionados
                    </button>
                )}

                <div className="w-full overflow-x-auto">
                    <table className="min-w-[1200px] text-sm divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
                        <thead className="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th className="px-2 py-1">
                                    <input
                                        type="checkbox"
                                        checked={selectedIds.length === supports.length}
                                        onChange={(e) =>
                                            setSelectedIds(e.target.checked ? supports.map((s) => s.id) : [])
                                        }
                                    />
                                </th>
                                {canEdit && (
                                    <th className="px-2 py-1 text-center" title="Acciones">
                                        <Wrench className="w-4 h-4 mx-auto text-gray-600" />
                                    </th>
                                )}



                                <th className="px-2 py-1">Ver más</th>
                                <th className="px-2 py-1">Reporte</th>
                                <th className="px-2 py-1">ID</th>
                                <th className="px-2 py-1">Cliente</th>
                                <th className="px-2 py-1">Celular</th>
                                <th className="px-2 py-1">Email</th>
                                <th className="px-2 py-1">Área</th>
                                <th className="px-2 py-1">Asunto</th>
                                <th className="px-2 py-1">Proyecto</th>
                                <th className="px-2 py-1">Manzana</th>
                                <th className="px-2 py-1">Lote</th>
                                <th className="px-2 py-1">Prioridad</th>
                                <th className="px-2 py-1">Estado ATC</th>
                                <th className="px-2 py-1">Estado Interno</th>

                                <th className="px-2 py-1">Creación</th>
                                <th className="px-2 py-1">Estado Global</th>
                            </tr>
                        </thead>



                        <tbody>
                            {supports.map((support) => (
                                <React.Fragment key={support.id}>
                                    {/* Fila principal */}
                                    <tr
                                        className={`border-t hover:bg-gray-50 dark:hover:bg-gray-700 ${highlightedIds.includes(support.id) ? 'animate-border-glow' : ''
                                            }`}
                                    >
                                        <td className="px-2 py-1">
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.includes(support.id)}
                                                onChange={(e) =>
                                                    setSelectedIds((prev) =>
                                                        e.target.checked
                                                            ? [...prev, support.id]
                                                            : prev.filter((id) => id !== support.id)
                                                    )
                                                }
                                            />
                                        </td>

                                        {canEdit && (
                                            <td className="px-2 py-1 text-sm space-x-2">
                                                {/* Botón Editar (si lo deseas habilitar en el futuro) */}

                                                <button
                                                    onClick={async () => {
                                                        setEditingId(support.id);
                                                        try {
                                                            const response = await axios.get(`/supports/${support.id}`);
                                                            const fullSupport = response.data; // Asegúrate que el backend retorne support + details

                                                            // Aquí puedes abrir tu modal y pasarle el soporte completo
                                                            // Por ejemplo:
                                                            setSelectedSupportId(support.id);
                                                            setEditSupport(fullSupport);
                                                            setShowModal(true);

                                                        } catch (error) {
                                                            console.error('Error al cargar soporte:', error);
                                                            toast.error('No se pudo cargar la atención');
                                                        } finally {
                                                            setEditingId(null);
                                                        }
                                                    }}
                                                    disabled={editingId === support.id}
                                                    className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                                                >
                                                    {editingId === support.id ? (
                                                        <HourglassLoader />
                                                    ) : (
                                                        <>
                                                            <Paintbrush className="w-4 h-4" />
                                                        </>
                                                    )}
                                                </button>



                                                {/* Botón Eliminar */}
                                                <button
                                                    onClick={async () => {
                                                        if (confirm(`¿Eliminar soporte "${support.details[0]?.subject}"?`)) {
                                                            try {
                                                                setDeletingId(support.id);
                                                                await axios.delete(`/supports/${support.id}`);
                                                                setSupports((prev) => prev.filter((s) => s.id !== support.id));
                                                            } catch (e) {
                                                                alert('Error al eliminar');
                                                                console.error(e);
                                                            } finally {
                                                                setDeletingId(null);
                                                            }
                                                        }
                                                    }}
                                                    disabled={deletingId === support.id}
                                                    className="text-red-600 hover:underline dark:text-red-400 flex items-center gap-1"
                                                >
                                                    {deletingId === support.id ? (
                                                        <HourglassLoader />
                                                    ) : (
                                                        <>
                                                            <Trash2 className="w-4 h-4" />
                                                        </>
                                                    )}
                                                </button>
                                            </td>
                                        )}



                                        <td className="px-2 py-1">
                                            <button
                                                onClick={() => toggleExpand(support.id)}
                                                className="text-blue-600 underline text-sm"
                                            >
                                                {expanded.includes(support.id) ? 'Ocultar' : 'Ver más'}
                                            </button>
                                        </td>

                                        <td className="px-2 py-1">
                                            <Link
                                                href={`/reports/${support.id}`}
                                                className="text-blue-600 underline hover:text-blue-800 text-sm"
                                            >
                                                Ver
                                            </Link>
                                        </td>

                                        <td className="px-2 py-1">Ticket-{String(support.id).padStart(5, '0')}</td>
                                        <td className="px-2 py-1">{support.client?.Razon_Social || '-'}</td>
                                        <td className="px-2 py-1">{support.client?.telefono || '-'}</td>
                                        <td className="px-2 py-1">{support.client?.email || '-'}</td>
                                        <td className="px-2 py-1">{support.details[0]?.area?.descripcion || '-'}</td>
                                        <td className="px-2 py-1 max-w-[150px] truncate">
                                            {support.details[0]?.subject ?? '-'}
                                        </td>
                                        <td className="px-2 py-1">{support.details[0]?.project?.descripcion ?? '-'}</td>
                                        <td className="px-2 py-1">{support.details[0]?.Manzana ?? '-'}</td>
                                        <td className="px-2 py-1">{support.details[0]?.Lote ?? '-'}</td>
                                        <td className="px-2 py-1">{support.details[0]?.priority ?? '-'}</td>



                                        <td className="px-2 py-1">
                                            {support.details[0]?.external_state?.description ?? (
                                                <span className="text-red-500">⚠️ No cargado</span>
                                            )}
                                        </td>
                                        <td className="px-2 py-1">{support.details[0]?.internal_state?.description ?? '-'}</td>

                                        <td className="px-2 py-1">
                                            {support.created_at
                                                ? new Date(support.created_at).toLocaleString('es-PE', {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                    hour12: false,
                                                }).replace(',', ' -')
                                                : '—'}
                                        </td>
                                            <td className="px-2 py-1">{support.status_global ?? '-'}</td>
                                    </tr>

                                    {/* Fila expandida (si aplica) */}
                                    {expanded.includes(support.id) && (
                                        <tr className="bg-gray-50 dark:bg-gray-900">
                                            <td colSpan={16}>
                                                <div className="p-3 text-sm">
                                                    <strong className="block mb-2 text-gray-700 dark:text-gray-200">
                                                        Detalles adicionales:
                                                    </strong>

                                                    <table className="w-full text-sm text-left border border-gray-300 dark:border-gray-700">
                                                        <thead className="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                                            <tr>
                                                                <th className="px-2 py-1 border">#</th>
                                                                <th className="px-2 py-1 border">Asunto</th>

                                                                <th className="px-2 py-1 border">Estado</th>
                                                                <th className="px-2 py-1 border">Prioridad</th>
                                                                <th className="px-2 py-1 border">Área</th>
                                                                <th className="px-2 py-1 border">Proyecto</th>
                                                                <th className="px-2 py-1 border">Motivo</th>
                                                                <th className="px-2 py-1 border">Tipo Cita</th>
                                                        
                                                                <th className="px-2 py-1 border">Estado Interno</th>
                                                                <th className="px-2 py-1 border">Estado ATC</th>
                                                                <th className="px-2 py-1 border">Adjunto</th>
                                                                <th className="px-2 py-1 border">Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {support.details.map((detail, index) => (
                                                                <tr key={detail.id} className="border-t dark:border-gray-600">
                                                                    <td className="px-2 py-1 border">{index + 1}</td>
                                                                    <td className="px-2 py-1 border">{detail.subject}</td>

                                                                    <td className="px-2 py-1 border">{detail.status}</td>
                                                                    <td className="px-2 py-1 border">{detail.priority}</td>
                                                                    <td className="px-2 py-1 border">{detail.area?.descripcion || '-'}</td>
                                                                    <td className="px-2 py-1 border">{detail.project?.descripcion || '-'}</td>
                                                                    <td className="px-2 py-1 border">{detail.motivo_cita?.nombre_motivo || '-'}</td>
                                                                    <td className="px-2 py-1 border">{detail.tipo_cita?.tipo || '-'}</td>
                                                                    {/* <td className="px-2 py-1 border">{detail.dia_espera?.dias || '-'}</td> */}
                                                                    <td className="px-2 py-1 border">{detail.internal_state?.description || '-'}</td>
                                                                    <td className="px-2 py-1 border">{detail.external_state?.description || '-'}</td>
                                                                    <td className="px-2 py-1 whitespace-nowrap">
                                                                        {detail.attachment && (
                                                                            <a
                                                                                href={`/uploads/${detail.attachment}`}
                                                                                download
                                                                                className="text-blue-600 underline dark:text-blue-400"
                                                                            >
                                                                                {detail.attachment}
                                                                            </a>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-2 py-1 border">
                                                                        <button
                                                                            onClick={() => {
                                                                                if (confirm('¿Estás seguro de eliminar este detalle?')) {
                                                                                    router.delete(`/support-details/${detail.id}`, {
                                                                                        onSuccess: () => toast.success('Detalle eliminado'),
                                                                                        onError: () => toast.error('Error al eliminar'),
                                                                                        preserveScroll: true,
                                                                                    });
                                                                                }
                                                                            }}
                                                                            className="text-red-600 hover:text-red-800 text-xs"
                                                                        >
                                                                            🗑 Eliminar
                                                                        </button>
                                                                        {canEdit && (

                                                                            <button
                                                                                onClick={() => {
                                                                                    setSelectedSupportId(support.id);
                                                                                    setSupportDetailToEdit(detail); // 👈 Nuevo estado
                                                                                    setShowAreaModal(true);
                                                                                }}
                                                                                className="text-blue-600 ml-5 text-sm underline hover:text-blue-800 transition"
                                                                            >
                                                                                Área/Motivo
                                                                            </button>


                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </React.Fragment>
                            ))}
                        </tbody>

                    </table>
                </div>

                <div className="flex justify-center mt-6 space-x-2">
                    {[...Array(pagination.last_page)].map((_, index) => {
                        const page = index + 1;
                        return (
                            <button
                                key={page}
                                onClick={() => fetchPage(`/supports/fetch?page=${page}`)}
                                className={`px-3 py-1 rounded text-sm font-medium transition ${pagination.current_page === page
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                                    }`}
                                disabled={pagination.current_page === page}
                            >
                                {page}
                            </button>
                        );
                    })}
                </div>
            </>
        </div>
    );


}
