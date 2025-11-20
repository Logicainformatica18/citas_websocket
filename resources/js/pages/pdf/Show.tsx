import React, { useState } from "react";
import { Link } from "@inertiajs/react";
import UploadPartsModal from "./Components/UploadPartsModal";
import { Layers, ChevronLeft, PlusCircle } from "lucide-react";

interface Props {
    pdf: any;
}

export default function Show({ pdf }: Props) {
    const [openModal, setOpenModal] = useState(false);

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
                    {pdf.processed ? (
                        <span className="px-3 py-1 text-xs bg-green-600 text-white rounded-full shadow">
                            Procesado ✓
                        </span>
                    ) : (
                        <span className="px-3 py-1 text-xs bg-yellow-600 text-white rounded-full shadow animate-pulse">
                            Procesando…
                        </span>
                    )}
                </div>

                {/* 📤 BOTÓN SUBIR PARTE */}
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

            {/* 📚 TABLA DE PARTES */}
            <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                            rounded-xl shadow-lg overflow-hidden">

                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs uppercase">
                        <tr>
                            <th className="px-4 py-3 text-left">Parte</th>
                            <th className="px-4 py-3 text-center">Estado</th>
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

                        {pdf.parts.map((part: any) => (
                            <tr
                                key={part.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition"
                            >
                                <td className="px-4 py-3 font-medium">
                                    Parte {part.part_number}
                                </td>

                                <td className="px-4 py-3 text-center">
                                    {part.processed ? (
                                        <span className="px-2 py-1 text-xs bg-green-600 text-white rounded-full shadow">
                                            Procesado
                                        </span>
                                    ) : (
                                        <span className="px-2 py-1 text-xs bg-yellow-500 text-white rounded-full shadow animate-pulse">
                                            Procesando…
                                        </span>
                                    )}
                                </td>

                                <td className="px-4 py-3 text-right">
                                    {part.processed && (
                                        <Link
                                            href={route("pdf.parts.show", { pdf: pdf.id, part: part.id })}
                                            className="px-3 py-1.5 bg-blue-600 hover:bg-blue-700
                                                       dark:bg-blue-500 dark:hover:bg-blue-600
                                                       text-white text-xs rounded-lg inline-flex
                                                       items-center gap-2 shadow transition"
                                        >
                                            Ver detalle
                                        </Link>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* 🪟 MODAL (solo en modo claro/oscuro automático) */}
            <UploadPartsModal
                open={openModal}
                onClose={() => setOpenModal(false)}
                pdfId={pdf.id}
            />
        </div>
    );
}
