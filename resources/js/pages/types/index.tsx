import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import TypeModal from './modal';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';

const breadcrumbs = [{ title: 'Tipos', href: '/types' }];

type Type = {
    id: number;
    description: string;
    detail?: string | null;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

export default function Types() {
    const { types: initialPagination } = usePage<{
        types: Pagination<Type>;
    }>().props;

    const [types, setTypes] = useState<Type[]>(initialPagination?.data || []);
    const [pagination, setPagination] = useState(initialPagination);
    const [showModal, setShowModal] = useState(false);
    const [editType, setEditType] = useState<Type | null>(null);

    const handleTypeSaved = (saved: Type) => {
        setTypes((prev) => {
            const exists = prev.find((t) => t.id === saved.id);
            return exists
                ? prev.map((t) => (t.id === saved.id ? saved : t))
                : [saved, ...prev];
        });
        setEditType(null);
    };

    const fetchType = async (id: number) => {
        const res = await axios.get(`/types/${id}`);
        setEditType(res.data.type);
        setShowModal(true);
    };

    const fetchPage = async (url: string) => {
        try {
            const res = await axios.get(url);
            setTypes(res.data.types.data);
            setPagination(res.data.types);
        } catch (e) {
            console.error('Error al cargar página', e);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8">
                <h1 className="text-2xl font-bold mb-4">Listado de Tipos</h1>

                <button
                    onClick={() => {
                        setEditType(null);
                        setShowModal(true);
                    }}
                    className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                >
                    Nuevo Tipo
                </button>

                <div className="overflow-x-auto mt-4">
                    <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
                        <thead className="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-2 text-black dark:text-white">Acciones</th>
                                <th className="px-4 py-2 text-black dark:text-white">ID</th>
                                <th className="px-4 py-2 text-black dark:text-white">Descripción</th>
                                <th className="px-4 py-2 text-black dark:text-white">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            {types.map((type) => (
                                <tr
                                    key={type.id}
                                    className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white"
                                >
                                    <td className="px-4 py-2 space-x-2 text-sm">
                                        <button
                                            onClick={() => fetchType(type.id)}
                                            className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                                        >
                                            <Paintbrush className="w-4 h-4" />
                                            Editar
                                        </button>
                                        <button
                                            onClick={async () => {
                                                if (confirm(`¿Eliminar el tipo ${type.description}?`)) {
                                                    try {
                                                        await axios.delete(`/types/${type.id}`);
                                                        setTypes((prev) =>
                                                            prev.filter((t) => t.id !== type.id)
                                                        );
                                                    } catch (e) {
                                                        alert('Error al eliminar');
                                                        console.error(e);
                                                    }
                                                }
                                            }}
                                            className="text-red-600 hover:underline dark:text-red-400 flex items-center gap-1"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                            Eliminar
                                        </button>
                                    </td>

                                    <td className="px-4 py-2">{type.id}</td>
                                    <td className="px-4 py-2">{type.description}</td>
                                    <td className="px-4 py-2">
                                        {type.detail ? (
                                            type.detail
                                        ) : (
                                            <span className="text-gray-400 italic">Sin detalle</span>
                                        )}
                                    </td>
                                </tr>
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
                                onClick={() => fetchPage(`/types/fetch?page=${page}`)}
                                className={`px-3 py-1 rounded text-sm font-medium transition ${
                                    pagination.current_page === page
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
            </div>

            {showModal && (
                <TypeModal
                    open={showModal}
                    onClose={() => {
                        setShowModal(false);
                        setEditType(null);
                    }}
                    onSaved={handleTypeSaved}
                    typeToEdit={editType}
                />
            )}
        </AppLayout>
    );
}
