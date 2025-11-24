import React, { useState } from "react";
import { Link } from "@inertiajs/react";
import {
    Layers,
    ChevronLeft,
    PlusCircle,
    Hourglass,
    CheckCircle,
    Eye,
    Trash2,
    FileText
} from "lucide-react";
import axios from "axios";
import Swal from "sweetalert2";
import UploadPartsModal from "./Components/UploadPartsModal";

interface Props {
    pdf: any;
}

export default function Show({ pdf }: Props) {
    const [openModal, setOpenModal] = useState(false);

    // 🔧 Determinar estado según flag real
    const getFlagStatus = (flag: number) => {
        if (flag === 1) {
            return {
                label: "Listo",
                icon: <CheckCircle className="w-3 h-3" />,
                color: "text-green-600",
            };
        }
        return {
            label: "Procesando…",
            icon: <Hourglass className="w-3 h-3" />,
            color: "text-yellow-600",
        };
    };

    // 🗑 Eliminar parte
    const deletePart = async (partId: number) => {
        const confirm = await Swal.fire({
            title: "¿Eliminar parte?",
            text: "Se eliminará el archivo y todos los datos procesados.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar",
        });

        if (!confirm.isConfirmed) return;

        await axios.delete(`/pdf/${pdf.id}/parts/${partId}`);

        Swal.fire("Eliminado", "La parte fue eliminada correctamente.", "success");
        location.reload();
    };

    return (
        <div className="p-8 max-w-6xl mx-auto text-gray-900 dark:text-gray-100">

            {/* 🔙 VOLVER */}
            <div className="mb-4">
                <Link
                    href="/pdf"
                    className="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline"
                >
                    <ChevronLeft className="w-4 h-4 mr-1" />
                    Volver
                </Link>
            </div>

            {/* 📄 HEADER */}
            <div className="mb-8 pb-5 border-b border-gray-200 dark:border-gray-700">
                <h1 className="text-3xl font-bold flex items-center gap-3">
                    <Layers className="w-9 h-9 text-blue-600 dark:text-blue-400" />
                    {pdf.title}
                </h1>

                <p className="text-gray-600 dark:text-gray-400 mt-1">
                    Año: <span className="font-semibold">{pdf.year ?? "No especificado"}</span>
                </p>

                <div className="mt-3">
                    {pdf.processed >= 1 ? (
                        <span className="px-3 py-1 text-xs bg-green-600 text-white rounded-full shadow">
                            Procesado ✓
                        </span>
                    ) : (
                        <span className="px-3 py-1 text-xs bg-yellow-600 text-white rounded-full shadow animate-pulse">
                            Procesando partes…
                        </span>
                    )}
                </div>

                {/* 📤 SUBIR PARTES */}
                <div className="mt-6">
                    <button
                        onClick={() => setOpenModal(true)}
                        className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500
                                   dark:hover:bg-blue-600 text-white rounded-lg shadow inline-flex
                                   items-center gap-2 transition"
                    >
                        <PlusCircle className="w-5 h-5" />
                        Cargar partes (PDF 20 páginas)
                    </button>
                </div>
            </div>

            {/* 📚 LISTA DE PARTES */}
            <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                            rounded-xl shadow-lg overflow-hidden">

                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs uppercase">
                        <tr>
                            <th className="px-4 py-3 text-left">Parte</th>
                            <th className="px-4 py-3 text-left">Progreso</th>
                            <th className="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        {pdf.parts.length === 0 && (
                            <tr>
                                <td colSpan={3} className="py-8 text-center text-gray-500 dark:text-gray-400">
                                    No se han subido partes aún.
                                </td>
                            </tr>
                        )}

                        {pdf.parts.map((part: any) => {
                            const ocr = getFlagStatus(part.ocr_done);
                            const tables = getFlagStatus(part.tables_done);
                            const graphs = getFlagStatus(part.graphs_done);
                            const summary = getFlagStatus(part.summary_done);

                            const allComplete =
                                part.ocr_done &&
                                part.tables_done &&
                                part.graphs_done &&
                                part.summary_done;

                            const percent = Math.round((part.processed ?? 0) * 100);

                            // 🔥 URL del archivo
                            const fileUrl = part.file_url
                                ? part.file_url
                                : `/storage/${part.file_path}`;

                            return (
                                <tr
                                    key={part.id}
                                    className="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        Parte {part.part_number}
                                    </td>

                                    {/* 🔁 ESTADO */}
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col gap-1 text-xs">
                                            <div className="font-semibold text-blue-600 dark:text-blue-400">
                                                {percent}% — {part.step ?? "processing"}
                                            </div>

                                            <div className={`flex items-center gap-1 ${ocr.color}`}>
                                                {ocr.icon} OCR: {ocr.label}
                                            </div>

                                            <div className={`flex items-center gap-1 ${tables.color}`}>
                                                {tables.icon} Tablas: {tables.label}
                                            </div>

                                            <div className={`flex items-center gap-1 ${graphs.color}`}>
                                                {graphs.icon} Gráficos: {graphs.label}
                                            </div>

                                            <div className={`flex items-center gap-1 ${summary.color}`}>
                                                {summary.icon} Resumen: {summary.label}
                                            </div>
                                        </div>
                                    </td>

                                    {/* 🔗 ACCIONES */}
                                    <td className="px-4 py-3 text-right flex justify-end gap-2">

                                        {/* 📄 Ver PDF */}
                                        <a
                                            href={fileUrl}
                                            target="_blank"
                                            className="px-3 py-1.5 rounded-lg inline-flex items-center gap-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 shadow text-xs"
                                        >
                                            <FileText className="w-4 h-4" />
                                            Ver PDF
                                        </a>

                                        {/* 👁 Ver detalle */}
                                        <Link
                                            href={route("pdf.parts.show", { pdf: pdf.id, part: part.id })}
                                            className={`px-3 py-1.5 rounded-lg inline-flex items-center gap-2 shadow text-xs
                                                ${
                                                    allComplete
                                                        ? "bg-blue-600 hover:bg-blue-700 text-white"
                                                        : "bg-gray-300 text-gray-700 hover:bg-gray-400 dark:bg-gray-700 dark:text-gray-200"
                                                }`}
                                        >
                                            <Eye className="w-4 h-4" />
                                            Ver detalle
                                        </Link>

                                        {/* ❌ Eliminar */}
                                        <button
                                            onClick={() => deletePart(part.id)}
                                            className="px-3 py-1.5 rounded-lg inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white shadow text-xs"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {/* 🪟 MODAL */}
            <UploadPartsModal
                open={openModal}
                onClose={() => setOpenModal(false)}
                pdfId={pdf.id}
            />
        </div>
    );
}
