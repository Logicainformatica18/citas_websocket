import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import SourceModal from './modal';
import { Pencil, Trash2, Play } from 'lucide-react';

const breadcrumbs = [{ title: 'Fuentes', href: '/sources' }];

type Source = {
    id: number;
    name: string;
    base_url: string;
    last_run_at?: string;
    last_status?: string;
    api_status?: string;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

export default function Sources() {
    const { sources: initialPagination } = usePage<{
        sources: Pagination<Source>;
    }>().props;

    const [sources, setSources] = useState<Source[]>(initialPagination?.data || []);
    const [pagination, setPagination] = useState(initialPagination);
    const [showModal, setShowModal] = useState(false);
    const [editSource, setEditSource] = useState<Source | null>(null);

    const fetchPage = async (url: string) => {
        const res = await axios.get(url);
        setSources(res.data.sources.data);
        setPagination(res.data.sources);
    };

    // 🔥 BADGES
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

                {/* CARDS (datos falsos por ahora) */}
                <div className="grid grid-cols-4 gap-5">
                    <div className="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Total fuentes</p>
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">6</h2>
                        <span className="text-green-500 text-xs">+2 este mes</span>
                    </div>

                    <div className="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Activas</p>
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">4</h2>
                        <span className="text-green-500 text-xs">98% uptime</span>
                    </div>

                    <div className="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Registros totales</p>
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">31,655</h2>
                        <span className="text-green-500 text-xs">+1,204 hoy</span>
                    </div>

                    <div className="bg-white dark:bg-gray-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Con errores</p>
                        <h2 className="text-2xl font-bold text-red-500">1</h2>
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
                                <th className="px-4 py-3">Scraping</th>
                                <th className="px-4 py-3">API</th>
                                <th className="px-4 py-3">Registros</th>
                            </tr>
                        </thead>

                        <tbody>
                            {sources.map((s) => (
                                <tr
                                    key={s.id}
                                    className="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                                >
                                    {/* ACCIONES */}
                                    <td className="px-4 py-3 flex gap-3 items-center">

                                        {/* RUN */}
                                        <Play
                                            className="w-4 text-green-500 cursor-pointer hover:scale-110 transition"
                                            onClick={async () => {
                                                try {
                                                    await axios.post(`/sources/${s.id}/run`);
                                                    alert('Ejecutado');
                                                } catch {
                                                    alert('Error');
                                                }
                                            }}
                                        />

                                        {/* EDIT */}
                                        <Pencil
                                            className="w-4 text-blue-500 cursor-pointer hover:scale-110 transition"
                                            onClick={() => {
                                                setEditSource(s);
                                                setShowModal(true);
                                            }}
                                        />

                                        {/* DELETE */}
                                        <Trash2
                                            className="w-4 text-red-500 cursor-pointer hover:scale-110 transition"
                                            onClick={async () => {
                                                if (!confirm(`¿Eliminar ${s.name}?`)) return;

                                                try {
                                                    await axios.delete(`/sources/${s.id}`);
                                                    setSources(prev => prev.filter(x => x.id !== s.id));
                                                } catch {
                                                    alert('Error');
                                                }
                                            }}
                                        />
                                    </td>

                                    <td className="px-4 py-3 font-medium text-gray-800 dark:text-white">
                                        {s.name}
                                    </td>

                                    <td className="px-4 py-3 text-blue-600 dark:text-blue-400">
                                        {s.base_url}
                                    </td>

                                    <td className="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        {s.last_run_at || '—'}
                                    </td>

                                    <td className="px-4 py-3">
                                        {statusBadge(s.last_status)}
                                    </td>

                                    <td className="px-4 py-3">
                                        {statusBadge(s.api_status)}
                                    </td>

                                    <td className="px-4 py-3 font-semibold text-gray-800 dark:text-gray-200">
                                        {Math.floor(Math.random() * 15000)}
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

                {/* MODAL */}
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
            </div>
        </AppLayout>
    );
}