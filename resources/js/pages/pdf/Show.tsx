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
        <div className="p-6 max-w-5xl mx-auto">

            <div className="mb-4">
                <Link href="/pdf" className="inline-flex items-center text-blue-600 hover:underline">
                    <ChevronLeft className="w-4 h-4 mr-1" />
                    Volver
                </Link>
            </div>

            <div className="mb-6">
                <h1 className="text-3xl font-bold flex items-center gap-2">
                    <Layers className="w-8 h-8 text-blue-600" />
                    {pdf.title}
                </h1>

                <div className="text-sm text-gray-500">
                    Año: {pdf.year ?? "No especificado"}
                </div>

                <div className="mt-3">
                    {pdf.processed ? (
                        <span className="px-2 py-1 text-xs bg-green-600 text-white rounded">
                            Procesado ✓
                        </span>
                    ) : (
                        <span className="px-2 py-1 text-xs bg-yellow-600 text-white rounded animate-pulse">
                            Procesando…
                        </span>
                    )}
                </div>

                <div className="mt-6">
                    <button
                        onClick={() => setOpenModal(true)}
                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded shadow inline-flex items-center gap-2"
                    >
                        <PlusCircle className="w-5 h-5" />
                        Cargar partes (PDF 20 páginas)
                    </button>
                </div>
            </div>

            {/* LISTA DE PARTES */}
            <div className="bg-white dark:bg-gray-800 border rounded shadow overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th className="px-4 py-3 text-left">Parte</th>
                            <th className="px-4 py-3 text-center">Estado</th>
                            <th className="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        {pdf.parts.length === 0 && (
                            <tr>
                                <td colSpan={3} className="text-center py-6 text-gray-500">
                                    No se han subido partes aún.
                                </td>
                            </tr>
                        )}

                        {pdf.parts.map((part: any) => (
                            <tr key={part.id}>
                                <td className="px-4 py-3">Parte {part.part_number}</td>

                                <td className="px-4 py-3 text-center">
                                    {part.processed ? (
                                        <span className="px-2 py-1 text-xs bg-green-600 text-white rounded">
                                            Procesado
                                        </span>
                                    ) : (
                                        <span className="px-2 py-1 text-xs bg-yellow-500 text-white rounded animate-pulse">
                                            Procesando…
                                        </span>
                                    )}
                                </td>

                                <td className="px-4 py-3 text-right">
                                    {part.processed && (
                                        <Link
                                            href={route("pdf.parts.show", { pdf: pdf.id, part: part.id })}
                                            className="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded inline-flex items-center gap-2"
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

            <UploadPartsModal
                open={openModal}
                onClose={() => setOpenModal(false)}
                pdfId={pdf.id}
            />
        </div>
    );
}
