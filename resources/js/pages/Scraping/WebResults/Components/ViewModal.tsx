import { useEffect } from "react";

export default function ViewModal({ visible, onClose, data, formatDate, statusBadge }: any) {
    if (!visible || !data) return null;

    // ===============================
    // Cerrar con ESC
    // ===============================
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === "Escape") onClose();
        };
        window.addEventListener("keydown", handler);
        return () => window.removeEventListener("keydown", handler);
    }, []);

    // ===============================
    // Cerrar haciendo clic fuera
    // ===============================
    const closeIfOutside = (e: any) => {
        if (e.target.classList.contains("modal-overlay")) onClose();
    };

    return (
        <div
            className="modal-overlay fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 animate-fadeIn"
            onClick={closeIfOutside}
        >
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-6 w-full max-w-2xl relative animate-scaleIn">

                {/* Botón cerrar */}
                <button
                    onClick={onClose}
                    className="absolute top-2 right-2 text-gray-500 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white text-2xl"
                >
                    ×
                </button>

                <h2 className="text-xl font-bold mb-4">
                    Detalle del enlace
                </h2>

                <p className="mb-2">
                    <strong>URL:</strong>{" "}
                    <a
                        href={data.url}
                        target="_blank"
                        className="text-blue-600 underline break-all"
                    >
                        {data.url}
                    </a>
                </p>

                <p className="mb-2">
                    <strong>Estado:</strong> {statusBadge(data.status)}
                </p>

                <p className="mb-2">
                    <strong>Fecha:</strong> {formatDate(data.created_at)}
                </p>

                <div className="mt-4">
                    <strong>JSON procesado:</strong>
                    <pre className="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs max-h-72 overflow-auto mt-2">
                        {data.ai_json ? JSON.stringify(data.ai_json, null, 2) : "Sin datos"}
                    </pre>
                </div>
            </div>
        </div>
    );
}
