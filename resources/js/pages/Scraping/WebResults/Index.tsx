import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import axios from "axios";
import { toast } from "sonner";

import {
    Eye,
    Pencil,
    Trash2,
    ListChecks,
} from "lucide-react";

import ViewModal from "./Components/ViewModal";
import EditModal from "./Components/EditModal";

interface WebResult {
    id: number;
    url: string;
    status: string;
    category?: string | null;
    created_at: string;
    ai_json?: any;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    links: any[];
}

export default function WebResultsIndex({
    source,
    results,
}: {
    source: any;
    results: Pagination<WebResult>;
}) {
    // ===========================================
    // Estados
    // ===========================================
    const [items, setItems] = useState<WebResult[]>(results.data);
    const [pagination, setPagination] = useState(results);

    const [search, setSearch] = useState("");
    const [timer, setTimer] = useState<any>(null);

    const [viewModal, setViewModal] = useState<WebResult | null>(null);
    const [editModal, setEditModal] = useState<WebResult | null>(null);


    // ===========================================
    // Utils
    // ===========================================
    const formatDate = (str: string) => {
        const d = new Date(str);
        return d.toLocaleString("es-PE", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    };

    const statusBadge = (status: string) => {
        const colors: any = {
            pending: "bg-yellow-100 text-yellow-800",
            completed: "bg-green-100 text-green-800",
            error: "bg-red-100 text-red-800",
        };

        return (
            <span
                className={`px-2 py-1 rounded text-xs font-semibold ${
                    colors[status] ?? "bg-gray-100"
                }`}
            >
                {status}
            </span>
        );
    };

    // ===========================================
    // Buscador Live
    // ===========================================
    const searchLive = (value: string) => {
        setSearch(value);

        if (timer) clearTimeout(timer);

        const t = setTimeout(async () => {
            try {
                const res = await axios.get(
                    `/scraping/${source.id}/results/fetch?search=${value}`
                );
                setItems(res.data.results.data);
                setPagination(res.data.results);
            } catch {
                toast.error("Error buscando");
            }
        }, 400);

        setTimer(t);
    };

    // ===========================================
    // Paginación AJAX
    // ===========================================
    const fetchPage = async (url: string) => {
        if (!url) return;

        const query = url.includes("?") ? url.split("?")[1] : "";

        try {
            const res = await axios.get(
                `/scraping/${source.id}/results/fetch?${query}`
            );
            setItems(res.data.results.data);
            setPagination(res.data.results);
        } catch {
            toast.error("Error cargando página");
        }
    };

    // ===========================================
    // Eliminar
    // ===========================================
    const deleteItem = async (id: number) => {
        if (!confirm("¿Eliminar este resultado?")) return;

        try {
            await axios.delete(`/scraping/results/${id}`);
            setItems((prev) => prev.filter((x) => x.id !== id));
            toast.success("Eliminado");
        } catch {
            toast.error("No se pudo eliminar");
        }
    };

    // ===========================================
    // Guardar edición
    // ===========================================
    const saveEdit = async () => {
        if (!editModal) return;

        try {
            await axios.put(`/scraping/results/${editModal.id}`, {
                url: editModal.url,
                category: editModal.category,
                status: editModal.status,
            });

            setItems((prev) =>
                prev.map((it) =>
                    it.id === editModal.id ? { ...it, ...editModal } : it
                )
            );

            setEditModal(null);
            toast.success("Actualizado");
        } catch {
            toast.error("Error al actualizar");
        }
    };

    // ===========================================
    // Render
    // ===========================================
    return (
        <AppLayout
            breadcrumbs={[
                { title: "Scraping", href: "/scraping-sources" },
                {
                    title: source.name,
                    href: `/scraping/${source.id}/results`,
                },
                { title: "Resultados Web" },
            ]}
        >
            <div className="p-8">

                {/* HEADER */}
                <div className="flex justify-between mb-6">
                    <h1 className="text-3xl font-semibold flex items-center gap-2">
                        <ListChecks className="w-7 h-7 text-blue-600" />
                        Resultados Web – {source.name}
                    </h1>
                </div>

                {/* BUSCADOR */}
                <div className="flex gap-3 mb-6 bg-white dark:bg-gray-800 p-4 rounded-xl border dark:border-gray-700 shadow">
                    <input
                        type="text"
                        placeholder="Buscar enlace..."
                        className="rounded-md border w-64 px-2 py-1"
                        value={search}
                        onChange={(e) => searchLive(e.target.value)}
                    />
                </div>

                {/* TABLA */}
                <div className="overflow-x-auto rounded-xl shadow border bg-white dark:bg-gray-900 dark:border-gray-700">
                    <table className="min-w-full divide-y text-sm">
                        <thead className="bg-gray-100 dark:bg-gray-700 uppercase text-xs">
                            <tr>
                                <th className="px-4 py-3 text-left">#</th>
                                <th className="px-4 py-3 text-left">Enlace</th>
                                <th className="px-4 py-3 text-center">Estado</th>
                                <th className="px-4 py-3 text-center">Fecha</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            {items.map((item, i) => (
                                <tr
                                    key={item.id}
                                    className="hover:bg-gray-200 dark:hover:bg-gray-800 transition"
                                >
                                    <td className="px-4 py-3 font-semibold text-gray-700 dark:text-gray-200">
                                        {i + 1}
                                    </td>

                                    <td className="px-4 py-3">
                                        <div className="font-medium text-blue-600 underline break-all">
                                            <a href={item.url} target="_blank">
                                                {item.url}
                                            </a>
                                        </div>
                                        {item.category && (
                                            <div className="text-xs text-gray-600">
                                                {item.category}
                                            </div>
                                        )}
                                    </td>

                                    <td className="px-4 py-3 text-center">
                                        {statusBadge(item.status)}
                                    </td>

                                    <td className="px-4 py-3 text-center text-gray-700 dark:text-gray-200">
                                        {formatDate(item.created_at)}
                                    </td>

                                    <td className="px-4 py-3 text-right flex justify-end gap-3">
                                        <button
                                            onClick={() => setViewModal(item)}
                                            className="text-blue-600 hover:text-blue-800"
                                        >
                                            <Eye className="w-5 h-5" />
                                        </button>

                                        <button
                                            onClick={() => setEditModal(item)}
                                            className="text-green-600 hover:text-green-800"
                                        >
                                            <Pencil className="w-5 h-5" />
                                        </button>

                                        <button
                                            onClick={() => deleteItem(item.id)}
                                            className="text-red-600 hover:text-red-800"
                                        >
                                            <Trash2 className="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                            ))}

                            {items.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-6 text-center">
                                        No hay resultados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* PAGINACIÓN */}
                <div className="flex justify-center mt-6 gap-2">
                    {pagination.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => fetchPage(link.url)}
                            className={`px-3 py-1.5 rounded-md text-sm font-semibold ${
                                link.active
                                    ? "bg-blue-600 text-white"
                                    : "bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600"
                            }`}
                        >
                            {link.label.replace("&laquo;", "«").replace("&raquo;", "»")}
                        </button>
                    ))}
                </div>

                {/* MODALES */}
                <ViewModal
                    visible={viewModal !== null}
                    onClose={() => setViewModal(null)}
                    data={viewModal}
                    formatDate={formatDate}
                    statusBadge={statusBadge}
                />

                <EditModal
                    visible={editModal !== null}
                    onClose={() => setEditModal(null)}
                    data={editModal}
                    setData={setEditModal}
                    onSave={saveEdit}
                />

            </div>
        </AppLayout>
    );
}
