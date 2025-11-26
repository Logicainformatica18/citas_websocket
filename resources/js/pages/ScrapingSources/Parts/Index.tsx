import AppLayout from "@/layouts/app-layout";
import { Link } from "@inertiajs/react";
import { useState } from "react";
import { FileText, UploadCloud, Trash2 } from "lucide-react";
import Swal from "sweetalert2";
import axios from "axios";
import UploadPartsModal from "./UploadPartsModal";

export default function PartsIndex({ source, parts }) {
    const [openModal, setOpenModal] = useState(false);

    /**
     * 🗑️ ELIMINAR PARTE (axios → JSON → Swal)
     */
    const deletePart = (partId) => {
        Swal.fire({
            title: "¿Eliminar parte?",
            text: "Esta acción no se puede deshacer.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#d33",
        }).then(async (result) => {
            if (!result.isConfirmed) return;

            try {
                const res = await axios.delete(`/pdf/parts/${partId}`);

                Swal.fire("Eliminado", res.data.message, "success");

                // 🔄 Recargar lista
                location.reload();

            } catch (err) {
                Swal.fire("Error", "No se pudo eliminar la parte.", "error");
            }
        });
    };

    return (
        <AppLayout
            title={`PDF's — ${source.name}`}
            breadcrumbs={[
                { title: "Scraping Sources", href: "/scraping-sources" },
                { title: source.name, href: `/scraping-sources/${source.id}` },
                { title: "PDF's", href: "#" },
            ]}
        >
            <div className="p-8">

                {/* HEADER */}
                <div className="flex items-center justify-between mb-8 border-b pb-4">
                    <h1 className="text-3xl font-bold flex items-center gap-2">
                        <FileText className="w-7 h-7 text-blue-600" />
                        PDF's : {source.name}
                    </h1>

                    <button
                        onClick={() => setOpenModal(true)}
                        className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition"
                    >
                        <UploadCloud className="w-5 h-5" />
                        Subir partes
                    </button>
                </div>

                {/* INFO */}
                <div className="mb-6 text-gray-600 dark:text-gray-300">
                    <p className="text-sm">
                        Aquí puedes cargar las partes del PDF (20 páginas cada una) asociadas a esta fuente.
                    </p>
                </div>

                {/* LISTA */}
                <div className="bg-white dark:bg-gray-800 rounded-xl shadow border dark:border-gray-700 p-6">
                    {parts.length === 0 ? (
                        <p className="text-gray-500 text-sm">
                            No hay partes subidas aún.
                        </p>
                    ) : (
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b dark:border-gray-600">
                                    <th className="py-2"># Parte</th>
                                    <th>Nombre archivo</th>
                                    <th>Páginas detectadas</th>
                                    <th>Estado</th>
                                    <th className="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {parts.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                    >
                                        <td className="py-2 font-semibold">{p.part_number}</td>
                                        <td>{p.original_name}</td>
                                        <td>{p.end_page ? p.end_page - p.start_page + 1 : "-"}</td>

                                        <td>
                                            <span
                                                className={`px-3 py-1 rounded text-xs ${
                                                    p.processed
                                                        ? "bg-green-100 text-green-700"
                                                        : "bg-yellow-100 text-yellow-700"
                                                }`}
                                            >
                                                {p.processed ? "Procesado" : "Pendiente"}
                                            </span>
                                        </td>

                                        <td className="text-right flex items-center justify-end gap-4">

                                            {/* Ver detalle */}
                                            <Link
                                                href={`/pdf/parts/${p.id}`}
                                                className="text-blue-600 hover:underline"
                                            >
                                                Ver detalle →
                                            </Link>

                                            {/* Eliminar */}
                                            <button
                                                onClick={() => deletePart(p.id)}
                                                className="text-red-600 hover:text-red-800"
                                            >
                                                <Trash2 className="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* MODAL */}
                <UploadPartsModal
                    open={openModal}
                    onClose={() => setOpenModal(false)}
                    sourceId={source.id}
                />
            </div>
        </AppLayout>
    );
}
