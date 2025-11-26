import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import axios from "axios";

import {
    PlusCircle,
    Pencil,
    Trash2,
    Database,
    CheckCircle,
    XCircle,
} from "lucide-react";

import { toast } from "sonner";
import SourceModal from "./Components/SourceModal";

const breadcrumbs: BreadcrumbItem[] = [
    { title: "Scraping", href: "/scraping-sources" },
];

type Source = {
    id: number;
    name: string;
    url: string;
    frequency: string | null;
    pdf_path?: string | null;
    web_prompt?: string | null;
    api_url?: string | null;
    api_key?: string | null;
    excel_path?: string | null;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    links: any[];
};

export default function ScrapingSourcesIndex({
    sources,
    filters,
}: {
    sources: Pagination<Source>;
    filters: any;
}) {

    const [search, setSearch] = useState(filters?.search || "");
    const [timer, setTimer] = useState<any>(null);

    const [modalData, setModalData] = useState<Source | null>(null);

    const [items, setItems] = useState<Source[]>(sources.data);
    const [pagination, setPagination] = useState(sources);
const searchLive = (value: string) => {
    setSearch(value);

    // limpiar el timer anterior
    if (timer) clearTimeout(timer);

    // crear nuevo timer
    const newTimer = setTimeout(() => {
        axios
            .get(`/scraping-sources/fetch?search=${value}`)
            .then((r) => {
                setItems(r.data.sources.data);
                setPagination(r.data.sources);
            })
            .catch(() => toast.error("Error buscando"));
    }, 400);

    setTimer(newTimer);
};

    /* =====================================================
        BUSCADOR
    ===================================================== */
const handleSearch = (e: any) => {
    e.preventDefault();

    axios
        .get(`/scraping-sources/fetch?search=${search}`)
        .then((r) => {
            setItems(r.data.sources.data);
            setPagination(r.data.sources);
        })
        .catch(() => toast.error("Error buscando"));
};



    /* =====================================================
        DELETE — estilo USERS (axios)
    ===================================================== */
    const deleteSource = async (id: number) => {
        if (!confirm("¿Eliminar este portal definitivamente?")) return;

        try {
            await axios.delete(`/scraping-sources/${id}`);
            setItems((prev) => prev.filter((x) => x.id !== id));
            toast.success("Fuente eliminada");
        } catch {
            toast.error("No se pudo eliminar");
        }
    };

    /* =====================================================
        PAGINACIÓN AJAX
    ===================================================== */
    const fetchPage = async (url: string) => {
        if (!url) return;

        const query = url.includes("?") ? url.split("?")[1] : "";

        try {
            const res = await axios.get(`/scraping-sources/fetch?${query}`);
            setItems(res.data.sources.data);
            setPagination(res.data.sources);
        } catch {
            toast.error("Error cargando página");
        }
    };

    /* =====================================================
        BADGES ✔✖
    ===================================================== */
    const statusBadge = (exists: boolean) =>
        exists ? (
            <span className="flex items-center justify-center text-green-600">
                <CheckCircle size={18} />
            </span>
        ) : (
            <span className="flex items-center justify-center text-red-500">
                <XCircle size={18} />
            </span>
        );

    const isPossible = (item: Source) =>
        !!item.pdf_path ||
        !!item.web_prompt ||
        !!item.api_url ||
        !!item.api_key ||
        !!item.excel_path;

    /* =====================================================
        RENDER
    ===================================================== */
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8">

                {/* HEADER */}
                <div className="flex justify-between mb-6">
                    <h1 className="text-3xl font-semibold flex items-center gap-2">
                        <Database className="w-7 h-7 text-blue-600" />
                        TENDENCIAS TECNOLÓGICAS
                    </h1>
                </div>

                {/* BUSCADOR */}
             {/* BUSCADOR */}
<form
    onSubmit={handleSearch}
    className="flex gap-3 mb-6 bg-white dark:bg-gray-800 p-4 rounded-xl border dark:border-gray-700 shadow"
>
    <input
        type="text"
        placeholder="Buscar portal..."
        className="rounded-md border w-64 px-2 py-1"
        value={search}
        onChange={(e) => searchLive(e.target.value)}   // <<–– BUSQUEDA AUTOMÁTICA
    />

    <button className="px-4 py-2 bg-blue-600 text-white rounded-lg">
        Buscar
    </button>

    <button
        type="button"
        onClick={() => setModalData({} as Source)}
        className="px-4 py-2 bg-green-600 text-white rounded-lg flex items-center gap-2"
    >
        <PlusCircle className="w-5 h-5" />
        Nuevo
    </button>
</form>


                {/* TABLA */}
              <div className="overflow-x-auto rounded-xl shadow border bg-white dark:bg-gray-900 dark:border-gray-700">

                    <table className="min-w-full divide-y text-sm">
          <thead className="bg-gray-100 dark:bg-gray-700 uppercase text-xs text-gray-800 dark:text-gray-100">

                            <tr>
                                <th className="px-4 py-3 text-left">Portal</th>
                                <th className="px-4 py-3">Frecuencia</th>
                                <th className="px-4 py-3 text-center">PDF</th>
                                <th className="px-4 py-3 text-center">Web</th>
                                <th className="px-4 py-3 text-center">API</th>
                                <th className="px-4 py-3 text-center">Excel</th>
                                <th className="px-4 py-3 text-center">¿Posible?</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            {items.map((item) => (
                             <tr
  key={item.id}
  className="hover:bg-gray-200 dark:hover:bg-gray-800 transition text-gray-900 dark:text-gray-100"
>

                                   <td className="px-4 py-3 text-gray-900 dark:text-gray-100">

                                        <div className="font-semibold">{item.name}</div>
                                        {item.url && (
                                            <a
                                                href={item.url}
                                                target="_blank"
                                                className="text-blue-500 text-xs underline"
                                            >
                                                {item.url}
                                            </a>
                                        )}
                                    </td>

                                    <td className="px-4 py-3">
                                        {item.frequency ?? "-"}
                                    </td>

                                    <td className="px-4 py-3 text-center">
                                        {statusBadge(!!item.pdf_path)}
                                    </td>

                                    <td className="px-4 py-3 text-center">
                                        {statusBadge(!!item.web_prompt)}
                                    </td>

                                    <td className="px-4 py-3 text-center">
                                        {statusBadge(!!item.api_url || !!item.api_key)}
                                    </td>

                                    <td className="px-4 py-3 text-center">
                                        {statusBadge(!!item.excel_path)}
                                    </td>

                                    <td className="px-4 py-3 text-center">
                                        {statusBadge(isPossible(item))}
                                    </td>

                                    <td className="px-4 py-3 text-right flex justify-end gap-3">
                                        <button
                                            onClick={() => setModalData(item)}
                                            className="text-blue-600 hover:text-blue-800"
                                        >
                                            <Pencil className="w-5 h-5" />
                                        </button>

                                        <button
                                            onClick={() => deleteSource(item.id)}
                                            className="text-red-600 hover:text-red-800"
                                        >
                                            <Trash2 className="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                            ))}

                            {items.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-6 text-center">
                                        No se encontraron resultados.
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
                                    : "bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100"
                            }`}
                        >
                            {link.label.replace("&laquo;", "«").replace("&raquo;", "»")}
                        </button>
                    ))}
                </div>

                {/* MODAL */}
                {modalData && (
                    <SourceModal
                        data={modalData}
                        onClose={() => setModalData(null)}
                        onSaved={(saved) => {
                            if (!saved) return;

                            setItems((prev) => {
                                const exists = prev.find((s) => s.id === saved.id);
                                return exists
                                    ? prev.map((s) => (s.id === saved.id ? saved : s))
                                    : [saved, ...prev];
                            });

                          //  toast.success("Fuente guardada");
                        }}
                    />
                )}
            </div>
        </AppLayout>
    );
}
