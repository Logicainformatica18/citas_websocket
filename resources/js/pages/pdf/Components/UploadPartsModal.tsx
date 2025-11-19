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
    const [files, setFiles] = useState<File[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);

    if (!open) return null;

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setFiles([...files, ...Array.from(e.dataTransfer.files)]);
    };

    const handleUpload = async () => {
        if (files.length === 0) {
            Swal.fire("Aviso", "Debe seleccionar al menos 1 PDF.", "warning");
            return;
        }

        for (const file of files) {
            const form = new FormData();
            form.append("part_pdf", file);

            await axios.post(`/pdf/${pdfId}/parts`, form, {
                headers: { "Content-Type": "multipart/form-data" }
            });
        }

        Swal.fire("Listo", "Partes cargadas correctamente.", "success");
        onClose();
        location.reload();
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 w-full max-w-lg">

                {/* Header */}
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-xl font-bold">Cargar partes del PDF</h2>
                    <button onClick={onClose}>
                        <X className="w-6 h-6" />
                    </button>
                </div>

                {/* Dropzone */}
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
                        onChange={(e) => {
                            if (e.target.files)
                                setFiles([...files, ...Array.from(e.target.files)]);
                        }}
                    />
                </div>

                {/* Selected Files */}
                {files.length > 0 && (
                    <div className="mt-4">
                        <h3 className="font-semibold mb-2">Archivos seleccionados:</h3>
                        <ul className="text-sm space-y-1">
                            {files.map((f, idx) => (
                                <li key={idx} className="text-gray-700 dark:text-gray-300">
                                    • {f.name}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Buttons */}
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
