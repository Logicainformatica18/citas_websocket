import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { Link, router, usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import {
    FileText,
    PlusCircle,
    Eye,
    Trash2,
} from "lucide-react";
import Swal from "sweetalert2";

const breadcrumbs: BreadcrumbItem[] = [
    { title: "Documentos PDF", href: "/pdf" },
];

type PdfDoc = {
    id: number;
    title: string;
    source?: string | null;
    processed: boolean;
    created_at?: string;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

export default function PDFIndex() {
    const { documents: initialPagination } =
        usePage<{ documents: Pagination<PdfDoc> }>().props;

    const [items, setItems] = useState<PdfDoc[]>([]);
    const [pagination, setPagination] = useState(initialPagination);

    const [title, setTitle] = useState("");

    useEffect(() => {
        setItems(initialPagination.data);
        setPagination(initialPagination);
    }, [initialPagination]);

    /* -----------------------------------
     * CREAR DOCUMENTO
     * ----------------------------------- */
    const createDocument = async (e: any) => {
        e.preventDefault();

        if (!title.trim()) {
            Swal.fire("Aviso", "El título es obligatorio.", "warning");
            return;
        }

        await axios.post("/pdf", { title });

        Swal.fire({
            title: "Documento creado",
            text: "Ya puedes cargar las partes (PDF 20 páginas)",
            icon: "success",
            confirmButtonColor: "#2563eb",
        });

        setTitle("");
        router.reload();
    };

    /* -----------------------------------
     * ELIMINAR DOCUMENTO
     * ----------------------------------- */
    const deleteDocument = (id: number) => {
        Swal.fire({
            title: "¿Eliminar documento?",
            text: "Esto eliminará todas las partes, OCR, tablas y gráficos.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#6b7280",
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(`/pdf/${id}`, {
                    onSuccess: () => {
                        Swal.fire(
                            "Eliminado",
                            "El documento fue eliminado correctamente.",
                            "success"
                        );
                    },
                });
            }
        });
    };

    /* -----------------------------------
     * PAGINACIÓN AJAX
     * ----------------------------------- */
    const fetchPage = async (page: number) => {
        const res = await axios.get(`/pdf?page=${page}`);
        setItems(res.data.documents.data);
        setPagination(res.data.documents);
    };

    /* -----------------------------------
     * BADGE DE ESTADO
     * ----------------------------------- */
    const getStatusBadge = (processed: boolean) => {
        if (processed) {
            return (
                <span className="px-3 py-1 bg-green-600 text-white text-xs rounded-full shadow-sm">
                    Procesado
                </span>
            );
        }
        return (
            <span className="px-3 py-1 bg-yellow-500 text-white text-xs rounded-full shadow-sm animate-pulse">
                Incompleto
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition">

                {/* HEADER */}
                <div className="flex items-center justify-between mb-6 pb-4">
                    <h1 className="text-3xl font-semibold flex items-center gap-2">
                        <FileText className="w-7 h-7 text-blue-600 dark:text-blue-400" />
                        Documentos PDF
                    </h1>
                </div>

                {/* CARD NUEVO DOCUMENTO */}
                <form
                    onSubmit={createDocument}
                    className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5 mb-8 rounded-xl shadow-lg space-y-4"
                >
                    <h2 className="font-semibold text-lg flex items-center gap-2 text-gray-700 dark:text-gray-300">
                        <PlusCircle className="w-5 h-5 text-blue-500" />
                        Crear nuevo documento
                    </h2>

                    <div>
                        <label className="block font-semibold text-sm mb-1">Título</label>
                        <input
                            type="text"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            className="w-full border p-2 rounded-md dark:bg-gray-900 dark:border-gray-700"
                            placeholder="Ejemplo: Reporte Empleabilidad Tech 2024"
                        />
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow transition"
                        >
                            Crear documento
                        </button>
                    </div>
                </form>

                {/* TABLA */}
                <div className="overflow-x-auto rounded-xl shadow border border-gray-200 dark:border-gray-800">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3">Título</th>
                                <th className="px-4 py-3 text-center">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            {items.map((item) => (
                                <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                    <td className="px-4 py-3 font-medium">{item.title}</td>

                                    <td className="px-4 py-3 text-center">
                                        {getStatusBadge(item.processed)}
                                    </td>

                                    <td className="px-4 py-3 text-right flex gap-2 justify-end">

                                        {/* Ver partes */}
                                        <Link
                                            href={route("pdf.show", item.id)}
                                            className="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-lg inline-flex items-center gap-1 shadow"
                                        >
                                            <Eye className="w-4 h-4" /> Ver partes
                                        </Link>

                                        {/* Eliminar */}
                                        <button
                                            onClick={() => deleteDocument(item.id)}
                                            className="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs rounded-lg inline-flex items-center gap-1 shadow"
                                        >
                                            <Trash2 className="w-4 h-4" /> Eliminar
                                        </button>

                                    </td>
                                </tr>
                            ))}

                            {items.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="px-4 py-6 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        No hay documentos registrados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* PAGINACIÓN */}
                <div className="flex justify-center mt-6 gap-2">
                    {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                        <button
                            key={page}
                            onClick={() => fetchPage(page)}
                            className={`px-3 py-1.5 rounded-md text-sm font-semibold transition ${
                                page === pagination.current_page
                                    ? "bg-blue-600 text-white shadow"
                                    : "bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            }`}
                        >
                            {page}
                        </button>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
