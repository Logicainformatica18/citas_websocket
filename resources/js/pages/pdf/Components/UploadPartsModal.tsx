// resources/js/Pages/Pdf/Components/UploadPartsModal.tsx
import React, { useRef, useState } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { X, UploadCloud } from "lucide-react";

interface Props {
    open: boolean;
    onClose: () => void;
    pdfId: number;
}

export default function UploadPartsModal({ open, onClose, pdfId }: Props) {

    // ⚠️ Los File no deben ir en useState (React los rompe)
    const filesRef = useRef<File[]>([]);

    // Solo para mostrar la lista en pantalla
    const [renderList, setRenderList] = useState<File[]>([]);

    const inputRef = useRef<HTMLInputElement>(null);

    if (!open) return null;

    /* -----------------------------------------------------------
     * 1) DRAG & DROP
     * ----------------------------------------------------------- */
    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();

        const dropped = Array.from(e.dataTransfer.files);
        filesRef.current.push(...dropped);

        // mostrar
        setRenderList([...filesRef.current]);
    };

    /* -----------------------------------------------------------
     * 2) Selección con input file
     * ----------------------------------------------------------- */
    const handleSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files) return;

        const selected = Array.from(e.target.files);
        filesRef.current.push(...selected);

        setRenderList([...filesRef.current]);
    };

    /* -----------------------------------------------------------
     * 3) SUBIR PARTES
     * ----------------------------------------------------------- */
    const handleUpload = async () => {
        if (filesRef.current.length === 0) {
            Swal.fire("Aviso", "Debe seleccionar al menos 1 PDF.", "warning");
            return;
        }

        try {
            for (const file of filesRef.current) {
                console.log("SUBIENDO:", file.name, file.size);

                const form = new FormData();
                form.append("part_pdf", file);

                await axios.post(`/pdf/${pdfId}/parts/`, form, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                });
            }

            Swal.fire("Listo", "Partes cargadas correctamente.", "success");
            onClose();
            location.reload();

        } catch (error) {
            console.error(error);
            Swal.fire("Error", "No se pudieron subir las partes.", "error");
        }
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 w-full max-w-lg">

                {/* HEADER */}
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-xl font-bold">Cargar partes del PDF</h2>
                    <button onClick={onClose}>
                        <X className="w-6 h-6" />
                    </button>
                </div>

                {/* DROPZONE */}
                <div
                    onDrop={handleDrop}
                    onDragOver={(e) => e.preventDefault()}
                    className="border-2 border-dashed rounded-lg p-8 text-center cursor-pointer"
                    onClick={() => inputRef.current?.click()}
                >
                    <UploadCloud className="w-10 h-10 mx-auto mb-2 text-blue-500" />
                    <p className="text-gray-600 dark:text-gray-300">
                        Arrastra aquí tus PDFs (20 páginas)
                    </p>
                    <p className="text-sm text-gray-400 mt-1">
                        o haz clic para buscarlos
                    </p>

                    <input
                        type="file"
                        accept="application/pdf"
                        multiple
                        ref={inputRef}
                        className="hidden"
                        onChange={handleSelect}
                    />
                </div>

                {/* LISTA DE ARCHIVOS */}
                {renderList.length > 0 && (
                    <div className="mt-4">
                        <h3 className="font-semibold mb-2">Archivos seleccionados:</h3>
                        <ul className="text-sm space-y-1">
                            {renderList.map((f, idx) => (
                                <li key={idx} className="text-gray-700 dark:text-gray-300">
                                    • {f.name} ({(f.size / 1024 / 1024).toFixed(2)} MB)
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* BOTONES */}
                <div className="mt-6 flex justify-end gap-3">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 bg-gray-300 dark:bg-gray-700 rounded"
                    >
                        Cancelar
                    </button>

                    <button
                        onClick={handleUpload}
                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded"
                    >
                        Subir partes
                    </button>
                </div>
            </div>
        </div>
    );
}
