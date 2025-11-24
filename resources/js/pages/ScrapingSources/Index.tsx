import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import axios from "axios";
import { PlusCircle, Pencil, Trash2, Globe, FileText, Database } from "lucide-react";
import CreateModal from "./Components/CreateModal";
import EditModal from "./Components/EditModal";

export default function Index({ sources, filters }: any) {
    const [openCreate, setOpenCreate] = useState(false);
    const [editData, setEditData] = useState<any | null>(null);
    const [search, setSearch] = useState(filters?.search || "");

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get("/scraping-sources", { search }, { preserveState: true });
    };

    const toggleField = async (id: number, field: string, value: boolean) => {
        await axios.put(`/scraping-sources/${id}`, { [field]: !value });
    };

    const deleteSource = async (id: number) => {
        if (!confirm("¿Eliminar este portal definitivamente?")) return;

        await axios.delete(`/scraping-sources/${id}`);
        router.reload();
    };

    return (
        <>
            <Head title="Fuentes de Scraping" />

            <div className="p-8 max-w-6xl mx-auto">
                <h1 className="text-3xl font-bold mb-6">Fuentes de Scraping</h1>

                {/* 🔎 SEARCH */}
                <form onSubmit={handleSearch} className="mb-6 flex gap-3">
                    <input
                        type="text"
                        placeholder="Buscar portal..."
                        className="rounded-lg border-gray-300 w-64"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />

                    <button className="px-4 py-2 bg-blue-600 text-white rounded-lg">
                        Buscar
                    </button>

                    <button
                        type="button"
                        onClick={() => setOpenCreate(true)}
                        className="px-4 py-2 bg-green-600 text-white rounded-lg inline-flex items-center gap-2"
                    >
                        <PlusCircle className="w-5 h-5" />
                        Nuevo
                    </button>
                </form>

                {/* 📋 TABLE */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            <tr>
                                <th className="px-4 py-3">Portal</th>
                                <th className="px-4 py-3">Frecuencia</th>
                                <th className="px-4 py-3">PDF</th>
                                <th className="px-4 py-3">Web</th>
                                <th className="px-4 py-3">API</th>
                                <th className="px-4 py-3">Scrapable</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sources.data.map((item: any) => (
                                <tr key={item.id} className="border-t dark:border-gray-700">
                                    <td className="px-4 py-3">
                                        <div className="font-semibold">{item.name}</div>
                                        <a
                                            href={item.url}
                                            target="_blank"
                                            className="text-blue-500 text-xs hover:underline"
                                        >
                                            {item.url}
                                        </a>
                                    </td>

                                    <td className="px-4 py-3">{item.frequency ?? "-"}</td>

                                    {/* SWITCHES */}
                                    <td className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={item.has_pdf}
                                            onChange={() => toggleField(item.id, "has_pdf", item.has_pdf)}
                                        />
                                    </td>

                                    <td className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={item.web_only}
                                            onChange={() => toggleField(item.id, "web_only", item.web_only)}
                                        />
                                    </td>

                                    <td className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={item.has_api}
                                            onChange={() => toggleField(item.id, "has_api", item.has_api)}
                                        />
                                    </td>

                                    <td className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={item.scrapable}
                                            onChange={() => toggleField(item.id, "scrapable", item.scrapable)}
                                        />
                                    </td>

                                    <td className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => setEditData(item)}
                                            className="text-blue-600 hover:text-blue-800 mr-3"
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
                        </tbody>
                    </table>

                    {/* PAGINATION */}
                    <div className="p-4 flex justify-between text-sm text-gray-600">
                        <span>
                            Página {sources.current_page} de {sources.last_page}
                        </span>

                        <div className="flex gap-2">
                            {sources.links.map((link: any, i: number) => (
                                <button
                                    key={i}
                                    disabled={!link.url}
                                    onClick={() => router.get(link.url)}
                                    className={`px-3 py-1 rounded ${
                                        link.active ? "bg-blue-600 text-white" : "bg-gray-200"
                                    }`}
                                >
                                    {link.label.replace("&laquo;", "«").replace("&raquo;", "»")}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {/* MODALES */}
            {openCreate && <CreateModal onClose={() => setOpenCreate(false)} />}
            {editData && <EditModal data={editData} onClose={() => setEditData(null)} />}
        </>
    );
}
