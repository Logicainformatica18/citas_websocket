import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import SourceModal from './modal';
import {
    Pencil,
    Trash2,
    Play,
    Eye
} from 'lucide-react';
import dayjs from 'dayjs';
import SourceDetailsModal from './SourceDetailsModal';
const breadcrumbs = [{ title: 'Fuentes', href: '/sources' }];

type Source = {
    id: number;
    name: string;
    api_url?: string;
    last_run_at?: string;
    last_status?: string;
    api_status?: string;
    registros?: number; // 🔥 FIX
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

export default function Sources() {
  const { sources: initialPagination, metrics } = usePage<{
    sources: Pagination<Source>;
    metrics: {
        total_fuentes: number;
        activas: number;
        registros_totales: number;
        con_errores: number;
        uptime: number;
    };
}>().props;

    const [sources, setSources] = useState<Source[]>(initialPagination?.data || []);
    const [pagination, setPagination] = useState(initialPagination);
    const [showModal, setShowModal] = useState(false);
    const [editSource, setEditSource] = useState<Source | null>(null);
const [detailsOpen, setDetailsOpen] =
    useState(false);

const [selectedSource, setSelectedSource] =
    useState<any>(null);
  const fetchPage = async (url: string) => {
    try {
        const res = await axios.get(url);

        console.log('RESPONSE:', res.data); // 🔥 DEBUG

        if (!res.data || !res.data.sources) {
            console.error('Respuesta inválida', res);
            return;
        }

        setSources(res.data.sources.data || []);
        setPagination(res.data.sources);

    } catch (error) {
        console.error('Error fetchPage', error);
    }
};

    // 🔥 BADGES (igual que tu diseño)
    const statusBadge = (status?: string) => {
        if (status === 'success')
            return (
                <span className="px-3 py-1 text-xs font-medium rounded-full
                bg-green-100 text-green-700
                dark:bg-green-900/30 dark:text-green-400">
                    Activo
                </span>
            );

        if (status === 'failed')
            return (
                <span className="px-3 py-1 text-xs font-medium rounded-full
                bg-red-100 text-red-600
                dark:bg-red-900/30 dark:text-red-400">
                    Error
                </span>
            );

        return (
            <span className="px-3 py-1 text-xs font-medium rounded-full
            bg-yellow-100 text-yellow-600
            dark:bg-yellow-900/30 dark:text-yellow-400">
                Pendiente
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8 min-h-screen space-y-6 bg-gray-50 dark:bg-gray-950">

                {/* HEADER */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            Fuentes (APIs)
                        </h1>
                        <p className="text-gray-500 dark:text-gray-400 text-sm mt-1">
                            Gestiona las fuentes de datos para scraping y APIs.
                        </p>
                    </div>

                    <button
                        onClick={() => {
                            setEditSource(null);
                            setShowModal(true);
                        }}
                        className="px-5 py-2 rounded-lg text-white font-medium
                        bg-gradient-to-r from-blue-600 to-blue-500
                        shadow-md hover:opacity-90 transition"
                    >
                        + Nueva fuente
                    </button>
                </div>

                {/* CARDS (puedes luego hacerlas dinámicas) */}
                <div className="grid grid-cols-2 gap-5">
                    <div className="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Total fuentes</p>
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white"> {metrics.total_fuentes}</h2>

                    </div>



                    <div className="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Registros totales</p>
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">31,655</h2>

                    </div>


                </div>

                {/* TABLA */}
                <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">

                    <div className="p-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                        <div>
                            <h3 className="font-semibold text-gray-800 dark:text-white">
                                Listado de fuentes
                            </h3>
                            <p className="text-sm text-gray-400">
                                {sources.length} registros
                            </p>
                        </div>

                        <input
                            placeholder="Buscar fuente..."
                            className="border border-gray-200 dark:border-gray-700
                            bg-white dark:bg-gray-800
                            text-gray-800 dark:text-gray-200
                            rounded-lg px-3 py-2 text-sm
                            focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <table className="min-w-full text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th className="px-4 py-3">Acciones</th>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">URL</th>
                                <th className="px-4 py-3">Última ejecución</th>


                                <th className="px-4 py-3">Registros</th>
                            </tr>
                        </thead>

                        <tbody>
                            {sources.map((s) => (
                                <tr
                                    key={s.id}
                                    className="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                                >
                                    <td className="px-4 py-3 flex gap-3 items-center">

                                        {/* <Play
                                            className="w-4 text-green-500 cursor-pointer hover:scale-110 transition"
                                            onClick={async () => {
                                                await axios.post(`/sources/${s.id}/run`);
                                                fetchPage(`/sources/fetch?page=${pagination.current_page}`);
                                            }}
                                        /> */}
<Eye
    className="
        w-4
        text-gray-500
        cursor-pointer
        hover:scale-110
        transition
    "
    onClick={async () => {

        try {

            const res = await axios.get(
                `/sources/${s.id}/details`
            );

            setSelectedSource(
                res.data.source
            );

            setDetailsOpen(true);

        } catch (e) {

            console.error(e);

            alert(
                'Error cargando detalles'
            );
        }
    }}
/>
                                        <Pencil
                                            className="w-4 text-blue-500 cursor-pointer hover:scale-110 transition"
                                            onClick={() => {
                                                setEditSource(s);
                                                setShowModal(true);
                                            }}
                                        />

                                        <Trash2
                                            className="w-4 text-red-500 cursor-pointer hover:scale-110 transition"
                                            onClick={async () => {
                                                if (!confirm(`¿Eliminar ${s.name}?`)) return;

                                                await axios.delete(`/sources/${s.id}`);
                                                fetchPage(`/sources/fetch?page=${pagination.current_page}`);
                                            }}
                                        />
                                    </td>

                                    <td className="px-4 py-3 font-medium text-gray-800 dark:text-white">
                                        {s.name}
                                    </td>

                                    <td className="px-4 py-3 text-blue-600 dark:text-blue-400">
                                        {s.api_url || '—'}
                                    </td>

                                    <td className="px-4 py-3 text-gray-500 dark:text-gray-400">
                                       {s.last_run_at
    ? dayjs(s.last_run_at).format('DD/MM/YYYY HH:mm')
    : 'Nunca'}
                                    </td>



                                    <td className="px-4 py-3 font-semibold text-gray-800 dark:text-gray-200">
                                        {s.registros ?? 0}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {/* PAGINACIÓN */}
                    <div className="flex justify-end p-4 space-x-2">
                        {[...Array(pagination.last_page)].map((_, i) => {
                            const page = i + 1;
                            return (
                                <button
                                    key={page}
                                    onClick={() => fetchPage(`/sources/fetch?page=${page}`)}
                                    className={`px-3 py-1 rounded-md text-sm ${
                                        pagination.current_page === page
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                                    }`}
                                >
                                    {page}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {showModal && (
                    <SourceModal
                        open={showModal}
                        onClose={() => setShowModal(false)}
                        onSaved={() =>
                            fetchPage(`/sources/fetch?page=${pagination.current_page}`)
                        }
                        source={editSource}
                    />
                )}
                {detailsOpen && (
    <SourceDetailsModal
        open={detailsOpen}
        onClose={() =>
            setDetailsOpen(false)
        }
        source={selectedSource}
    />
)}
            </div>
        </AppLayout>
    );
}
