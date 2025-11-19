import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { Link, router, usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import {
    FileText,
    PlusCircle,
    Eye,
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
     * CREAR SOLO EL REGISTRO DEL DOCUMENTO
     * ----------------------------------- */
    const createDocument = async (e: any) => {
        e.preventDefault();

        if (!title.trim()) {
            Swal.fire("Aviso", "El título es obligatorio", "warning");
            return;
        }

        await axios.post("/pdf", {
            title: title,
        });

        Swal.fire("Listo", "Documento creado. Ahora puedes cargar partes.", "success");

        setTitle("");
        router.reload();
    };

    /* -------- PAGINATION -------- */
    const fetchPage = async (page: number) => {
        const res = await axios.get(`/pdf?page=${page}`);
        setItems(res.data.documents.data);
        setPagination(res.data.documents);
    };

    const getStatusBadge = (processed: boolean) => {
        if (processed) {
            return (
                <span className="px-3 py-1 bg-green-600 text-white text-xs rounded-full">
                    Procesado
                </span>
            );
        }
        return (
            <span className="px-3 py-1 bg-yellow-500 text-white text-xs rounded-full animate-pulse">
                Incompleto
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">

                {/* HEADER */}
                <div className="flex items-center justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
                    <h1 className="text-3xl font-semibold flex items-center gap-2">
                        <FileText className="w-7 h-7 text-blue-600 dark:text-blue-400" />
                        Documentos PDF
                    </h1>
                </div>

                {/* CARD NUEVO DOCUMENTO */}
                <form
                    onSubmit={createDocument}
                    className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 mb-6 rounded-lg shadow space-y-4"
                >
                    <h2 className="font-semibold text-lg flex items-center gap-2">
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
                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md"
                        >
                            Crear documento
                        </button>
                    </div>
                </form>

                {/* TABLA PRINCIPAL */}
                <div className="overflow-x-auto rounded-lg shadow border border-gray-200 dark:border-gray-800">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm uppercase">
                            <tr>
                                <th className="px-4 py-2">Título</th>
                                <th className="px-4 py-2 text-center">Estado</th>
                                <th className="px-4 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                            {items.map((item) => (
                                <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
                                    <td className="px-4 py-2 font-semibold">{item.title}</td>
                                    <td className="px-4 py-2 text-center">
                                        {getStatusBadge(item.processed)}
                                    </td>

                                    <td className="px-4 py-2 text-right">
                                        <Link
                                            href={route("pdf.show", item.id)}
                                            className="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg inline-flex items-center gap-1"
                                        >
                                            <Eye className="w-4 h-4" /> Ver partes
                                        </Link>
                                    </td>
                                </tr>
                            ))}

                            {items.length === 0 && (
                                <tr>
                                    <td className="px-4 py-6 text-center text-gray-500 dark:text-gray-400" colSpan={3}>
                                        No hay documentos registrados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* PAGINATION */}
                <div className="flex justify-center mt-6 gap-1">
                    {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                        <button
                            key={page}
                            onClick={() => fetchPage(page)}
                            className={`px-3 py-1 rounded-md text-sm font-medium transition ${
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
