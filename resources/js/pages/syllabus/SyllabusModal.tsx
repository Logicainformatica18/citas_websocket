import { useState, useCallback } from "react";
import axios from "axios";

type Props = {
  open: boolean;
  onClose: () => void;
  onUploaded: () => void;
};

type UploadFile = {
  file: File;
  progress: number;
  status: "pending" | "uploading" | "done" | "error";
};

export default function SyllabusModal({ open, onClose, onUploaded }: Props) {
  const [uploads, setUploads] = useState<UploadFile[]>([]);
  const [isDragging, setIsDragging] = useState(false);

  if (!open) return null;

  const handleFiles = useCallback((files: FileList | File[]) => {
    const pdfs = Array.from(files).filter((f) => f.type === "application/pdf");
    if (pdfs.length === 0) return alert("Solo se admiten archivos PDF");
    setUploads((prev) => [
      ...prev,
      ...pdfs.map((file) => ({ file, progress: 0, status: "pending" })),
    ]);
  }, []);

  const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
    handleFiles(e.dataTransfer.files);
  };

  const handleUpload = async () => {
    for (const [index, up] of uploads.entries()) {
      if (up.status !== "pending") continue;
      const fd = new FormData();
      fd.append("file", up.file);

      setUploads((prev) =>
        prev.map((u, i) =>
          i === index ? { ...u, status: "uploading", progress: 0 } : u
        )
      );

      try {
        await axios.post("/syllabus/upload", fd, {
          headers: { "Content-Type": "multipart/form-data" },
          onUploadProgress: (evt) => {
            const percent = Math.round((evt.loaded * 100) / (evt.total ?? 1));
            setUploads((prev) =>
              prev.map((u, i) => (i === index ? { ...u, progress: percent } : u))
            );
          },
        });

        setUploads((prev) =>
          prev.map((u, i) =>
            i === index ? { ...u, progress: 100, status: "done" } : u
          )
        );
      } catch {
        setUploads((prev) =>
          prev.map((u, i) => (i === index ? { ...u, status: "error" } : u))
        );
      }
    }

    // 🔹 Espera medio segundo y actualiza lista + cierra modal
    setTimeout(() => {
      onUploaded();
      onClose();
    }, 600);
  };

  const removeFile = (idx: number) => {
    setUploads((prev) => prev.filter((_, i) => i !== idx));
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-gray-900 rounded-lg w-[600px] p-6 space-y-4 shadow-lg border border-gray-700 relative">
        <h2 className="text-xl font-bold mb-2 text-white">
          Carga Masiva de Sílabos
        </h2>

        {/* 🟦 Área de Drop */}
        <label
          htmlFor="syllabus-input"
          onDrop={handleDrop}
          onDragOver={(e) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDragging(true);
          }}
          onDragLeave={(e) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDragging(false);
          }}
          className={`flex flex-col items-center justify-center p-6 border-2 border-dashed rounded-lg cursor-pointer transition ${
            isDragging
              ? "border-blue-400 bg-blue-900/30"
              : "border-gray-600 bg-gray-800 hover:border-blue-400"
          }`}
        >
          <p className="text-gray-300 text-center">
            Arrastra y suelta aquí tus archivos PDF
          </p>
          <p className="text-gray-500 text-sm mt-1">
            o haz clic para seleccionarlos
          </p>

          <input
            id="syllabus-input"
            type="file"
            accept="application/pdf"
            multiple
            className="hidden"
            onChange={(e) => handleFiles(e.target.files || [])}
          />
        </label>

        {/* Lista de archivos */}
        {uploads.length > 0 && (
          <div className="max-h-64 overflow-y-auto space-y-2 mt-3">
            {uploads.map((u, i) => (
              <div
                key={i}
                className="bg-gray-800 p-3 rounded border border-gray-700 flex flex-col gap-1"
              >
                <div className="flex justify-between text-sm text-gray-200">
                  <span>{u.file.name}</span>
                  <button
                    onClick={() => removeFile(i)}
                    className="text-red-400 hover:text-red-300"
                  >
                    ✕
                  </button>
                </div>

                <div className="w-full bg-gray-700 h-2 rounded mt-1">
                  <div
                    className={`h-2 rounded ${
                      u.status === "error"
                        ? "bg-red-500"
                        : u.status === "done"
                        ? "bg-green-500"
                        : "bg-blue-500"
                    }`}
                    style={{ width: `${u.progress}%` }}
                  ></div>
                </div>

                {u.status === "error" && (
                  <span className="text-xs text-red-400">Error al subir</span>
                )}
              </div>
            ))}
          </div>
        )}

        {/* Botones */}
        <div className="flex justify-end gap-2 mt-4">
          <button
            onClick={onClose}
            className="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded"
          >
            Cerrar
          </button>
          <button
            onClick={handleUpload}
            disabled={uploads.length === 0}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded disabled:opacity-50"
          >
            Subir Archivos
          </button>
        </div>
      </div>
    </div>
  );
}
