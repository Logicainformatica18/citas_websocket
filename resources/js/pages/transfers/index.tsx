import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import TransferModal from './modal';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';
import { router } from '@inertiajs/react'; // al inicio del archivo


const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transfers', href: '/transfers' },
];

type Transfer = {
    id: number;
    description?: string;
    details?: string;
    sender_firstname?: string;
    sender_lastname?: string;
    sender_email?: string;
    receiver_firstname?: string;
    receiver_lastname?: string;
    receiver_email?: string;
    file_1?: string;
    confirmed_at?: string;
    received_at?: string;

};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

export default function Transfers() {
    const { transfers: initialPagination } = usePage<{ transfers: Pagination<Transfer> }>().props;
    const [transfers, setTransfers] = useState<Transfer[]>(initialPagination.data);
    const [pagination, setPagination] = useState(initialPagination);
    const [showModal, setShowModal] = useState(false);
    const [editTransfer, setEditTransfer] = useState<Transfer | null>(null);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    const handleTransferSaved = (saved: Transfer) => {
        setTransfers((prev) => {
            const exists = prev.find((a) => a.id === saved.id);
            return exists ? prev.map((a) => (a.id === saved.id ? saved : a)) : [saved, ...prev];
        });
        setEditTransfer(null);
    };

    const fetchTransfer = async (id: number) => {
        const res = await axios.get(`/transfers/${id}`);
        setEditTransfer(res.data.transfer);
        setShowModal(true);
    };

    const fetchPage = async (url: string) => {
        try {
            const res = await axios.get(url);
            setTransfers(res.data.transfers.data);
            setPagination(res.data.transfers);
        } catch (e) {
            console.error('Error al cargar página', e);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8">
                <h1 className="text-2xl font-bold mb-4">Listado de Transfers</h1>
                <p className="text-muted-foreground mb-6">Aquí puedes administrar tus transfers.</p>

                <button onClick={() => {
                    setEditTransfer(null);
                    setShowModal(true);
                }}
                    className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                >
                    Nuevo Transfer
                </button>

                {selectedIds.length > 0 && (
                    <button onClick={async () => {
                        if (confirm(`¿Eliminar ${selectedIds.length} transfers?`)) {
                            try {
                                await axios.post('/transfers/bulk-delete', { ids: selectedIds });
                                setTransfers((prev) => prev.filter((a) => !selectedIds.includes(a.id)));
                                setSelectedIds([]);
                            } catch (e) {
                                alert('Error al eliminar en Lote');
                                console.error(e);
                            }
                        }
                    }}
                        className="ml-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
                    >
                        Eliminar seleccionados
                    </button>
                )}

                {/* <a href="/transfers/export/excel"
                                className="px-4 ml-2 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition"
                                target="_blank">
                                Exportar a Excel
                            </a> */}

                <div className="overflow-x-auto mt-4">
                <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
    <thead className="bg-gray-100 dark:bg-gray-800">
        <tr>
            <th className="px-4 py-2 text-black dark:text-dark">
                <input type="checkbox" checked={selectedIds.length === transfers.length}
                    onChange={(e) => {
                        setSelectedIds(e.target.checked ? transfers.map((a) => a.id) : []);
                    }}
                />
            </th>
            <th className="px-4 py-2 text-black dark:text-white">Acciones</th>
            <th className="px-4 py-2 text-black dark:text-white">Artículos</th>
            <th className="px-4 py-2 text-black dark:text-white">ID</th>
            <th className="px-4 py-2 text-black dark:text-white">Descripción</th>
            <th className="px-4 py-2 text-black dark:text-white">Detalles</th>
            <th className="px-4 py-2 text-black dark:text-white">Emisor Email</th>
            <th className="px-4 py-2 text-black dark:text-white">Emisor Nombres</th>
            <th className="px-4 py-2 text-black dark:text-white">Receptor Email</th>
            <th className="px-4 py-2 text-black dark:text-white">Receptor Nombres</th>
            <th className="px-4 py-2 text-black dark:text-white">Archivo</th>
            <th className="px-4 py-2 text-black dark:text-white">Fecha de Recepción</th>
            <th className="px-4 py-2 text-black dark:text-white">¿Confirmado?</th>
        </tr>
    </thead>
    <tbody>
        {transfers.map((transfer) => (
            <tr key={transfer.id} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white">
                <td className="px-4 py-2">
                    <input type="checkbox" checked={selectedIds.includes(transfer.id)}
                        onChange={(e) => {
                            setSelectedIds((prev) =>
                                e.target.checked
                                    ? [...prev, transfer.id]
                                    : prev.filter((id) => id !== transfer.id)
                            );
                        }}
                    />
                </td>
                <td className="px-4 py-2 text-sm space-x-2">
                    <button onClick={() => fetchTransfer(transfer.id)}
                        className="flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
                    >
                        <Paintbrush className="w-4 h-4" />
                        Editar
                    </button>

                    <button onClick={async () => {
                        if (confirm(`¿Eliminar transfer ID ${transfer.id}?`)) {
                            try {
                                await axios.delete(`/transfers/${transfer.id}`);
                                setTransfers((prev) => prev.filter((a) => a.id !== transfer.id));
                            } catch (e) {
                                alert('Error al eliminar');
                                console.error(e);
                            }
                        }
                    }}
                        className="flex items-center gap-1 text-red-600 hover:underline dark:text-red-400"
                    >
                        <Trash2 className="w-4 h-4" />
                        Eliminar
                    </button>
                </td>
                <td className="px-4 py-2">
                    <button onClick={() =>
                        router.visit(`/transfers/${transfer.id}/articles`)}

                        className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        Ver artículos
                    </button>
                </td>
                <td className="px-4 py-2">{transfer.id}</td>
                <td className="px-4 py-2">{transfer.description}</td>
                <td className="px-4 py-2">{transfer.details}</td>
                <td className="px-4 py-2">{transfer.sender_firstname}{transfer.sender_lastname}</td>
                <td className="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{transfer.sender_email}</td>
                <td className="px-4 py-2">{transfer.receiver_firstname}{transfer.receiver_lastname}</td>
                <td className="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{transfer.receiver_email}</td>
                <td className="px-4 py-2">
                    {transfer.file_1 && (
                        <a href={`../../uploads/${transfer.file_1}`} download
                            className="text-blue-600 underline dark:text-blue-400">
                            {transfer.file_1}
                        </a>
                    )}
                </td>
                <td className="px-4 py-2">
                    {transfer.received_at ? new Date(transfer.received_at).toLocaleString() : '-'}
                </td>
                <td className="px-4 py-2">
                    {transfer.confirmed_at ? (
                        <span className="text-green-600 font-semibold dark:text-green-400">✅ Confirmado</span>
                    ) : (
                        <span className="text-red-500 dark:text-red-400">❌ Sin confirmar</span>
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
                            <button key={page} onClick={() => fetchPage(`/transfers/fetch?page=${page}`)}
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
            </div>

            {showModal && (
                <TransferModal open={showModal} onClose={() => {
                    setShowModal(false);
                    setEditTransfer(null);
                }}
                    onSaved={handleTransferSaved}
                    transferToEdit={editTransfer}
                />
            )}
        </AppLayout>
    );
}
