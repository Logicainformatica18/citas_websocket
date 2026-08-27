import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import SelectionModal from './modal';
import axios from 'axios';
import { Paintbrush, Trash2, ListTree } from 'lucide-react';

type Selection = {
    id: number;
    description: string;
    detail?: string | null;
    state?: string | null;
    associate_id?: number | null;
    associate?: { id: number; description: string } | null;
};

type SelectionDetail = {
    id: number;
    description: string;
    detail?: string | null;
    selection_id: number;
    associate_detail_id?: number | null;
    associateDetail?: { id: number; description: string } | null;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

export default function Selections() {
    const { selections: initialPagination, allSelections } = usePage<{
        selections: Pagination<Selection>;
        allSelections: Selection[];
    }>().props;

    const [selections, setSelections] = useState<Selection[]>(initialPagination?.data || []);
    const [pagination, setPagination] = useState(initialPagination);
    const [showModal, setShowModal] = useState(false);
    const [editSelection, setEditSelection] = useState<Selection | null>(null);
    const [showDetails, setShowDetails] = useState(false);
    const [selectedSelection, setSelectedSelection] = useState<Selection | null>(null);
    const [details, setDetails] = useState<SelectionDetail[]>([]);
    const [detailsPagination, setDetailsPagination] = useState<Pagination<SelectionDetail> | null>(null);

    const handleSelectionSaved = (saved: Selection) => {
        setSelections((prev) => {
            const exists = prev.find((s) => s.id === saved.id);
            return exists
                ? prev.map((s) => (s.id === saved.id ? saved : s))
                : [saved, ...prev];
        });
        setEditSelection(null);
    };

    const fetchSelection = async (id: number) => {
        const res = await axios.get(`/selections/${id}`);
        setEditSelection(res.data.selection);
        setShowModal(true);
    };

    const fetchPage = async (url: string) => {
        try {
            const res = await axios.get(url);
            setSelections(res.data.selections.data);
            setPagination(res.data.selections);
        } catch (e) {
            console.error('Error al cargar página', e);
        }
    };

    const fetchDetails = async (selection: Selection) => {
        try {
            const res = await axios.get(`/selections/${selection.id}/details`);
            setSelectedSelection(selection);
            setDetails(res.data.details.data);
            setDetailsPagination(res.data.details);
            setShowDetails(true);
        } catch (e) {
            console.error('Error al cargar detalles', e);
        }
    };

    const handleDeleteSelection = async (selection: Selection) => {
        if (confirm(`¿Eliminar la selección ${selection.description}?`)) {
            try {
                await axios.delete(`/selections/${selection.id}`);
                setSelections((prev) => prev.filter((s) => s.id !== selection.id));
            } catch (e: any) {
                alert(e?.response?.data?.message || 'Error al eliminar');
            }
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Selecciones', href: '/selections' }]}>
            <div className="p-8">
                <h1 className="text-2xl font-bold mb-4">Listado de Selecciones</h1>

                <button
                    onClick={() => {
                        setEditSelection(null);
                        setShowModal(true);
                    }}
                    className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                >
                    Nueva Selección
                </button>

                <div className="overflow-x-auto mt-4">
                    <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
                        <thead className="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-2 text-black dark:text-white">Acciones</th>
                                <th className="px-4 py-2 text-black dark:text-white">ID</th>
                                <th className="px-4 py-2 text-black dark:text-white">Descripción</th>
                                <th className="px-4 py-2 text-black dark:text-white">Detalle</th>
                                <th className="px-4 py-2 text-black dark:text-white">Estado</th>
                                <th className="px-4 py-2 text-black dark:text-white">Asociada a</th>
                            </tr>
                        </thead>
                        <tbody>
                            {selections.map((selection) => (
                                <tr key={selection.id} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white">
                                    <td className="px-4 py-2 space-x-2 text-sm">
                                        <button onClick={() => fetchSelection(selection.id)} className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1">
                                            <Paintbrush className="w-4 h-4" /> Editar
                                        </button>
                                        <button onClick={() => handleDeleteSelection(selection)} className="text-red-600 hover:underline dark:text-red-400 flex items-center gap-1">
                                            <Trash2 className="w-4 h-4" /> Eliminar
                                        </button>
                                        <button onClick={() => fetchDetails(selection)} className="text-indigo-600 hover:underline dark:text-indigo-400 flex items-center gap-1">
                                            <ListTree className="w-4 h-4" /> Detalles
                                        </button>
                                    </td>
                                    <td className="px-4 py-2">{selection.id}</td>
                                    <td className="px-4 py-2">{selection.description}</td>
                                    <td className="px-4 py-2">{selection.detail || <span className="text-gray-400 italic">Sin detalle</span>}</td>
                                    <td className="px-4 py-2">{selection.state || <span className="text-gray-400 italic">Sin estado</span>}</td>
                                    <td className="px-4 py-2">{selection.associate?.description || <span className="text-gray-400 italic">Ninguna</span>}</td>
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
                                onClick={() => fetchPage(`/selections/fetch?page=${page}`)}
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
                <SelectionModal
                    open={showModal}
                    onClose={() => {
                        setShowModal(false);
                        setEditSelection(null);
                    }}
                    onSaved={handleSelectionSaved}
                    selectionToEdit={editSelection}
                    availableSelections={allSelections}
                />
            )}

            {showDetails && selectedSelection && (
                <div className="p-8 pt-0">
                    <div className="rounded border border-slate-200 bg-white p-4 dark:bg-black dark:border-slate-700">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-xl font-bold">Detalles de {selectedSelection.description}</h2>
                            <button onClick={() => setShowDetails(false)} className="text-sm text-gray-500 underline">Cerrar</button>
                        </div>

                        <div className="space-y-3">
                            {details.length === 0 ? (
                                <p className="text-gray-400 italic">Sin detalles</p>
                            ) : (
                                details.map((detail) => (
                                    <div key={detail.id} className="rounded border p-3 dark:border-slate-700">
                                        <div className="flex justify-between">
                                            <strong>{detail.description}</strong>
                                            <span className="text-sm text-gray-500">ID {detail.id}</span>
                                        </div>
                                        <div className="text-sm text-gray-600 dark:text-gray-300">{detail.detail || 'Sin detalle'}</div>
                                        {detail.associateDetail && (
                                            <div className="mt-2 text-xs text-indigo-600 dark:text-indigo-400">
                                                Asociado a: {detail.associateDetail.description}
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>

                        {detailsPagination && detailsPagination.last_page > 1 && (
                            <div className="mt-4 flex justify-center gap-2">
                                {[...Array(detailsPagination.last_page)].map((_, index) => {
                                    const page = index + 1;
                                    return (
                                        <button
                                            key={page}
                                            onClick={() => fetchDetails({ ...selectedSelection, id: selectedSelection.id })}
                                            className="px-2 py-1 rounded text-xs bg-gray-200 text-gray-800"
                                        >
                                            {page}
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
