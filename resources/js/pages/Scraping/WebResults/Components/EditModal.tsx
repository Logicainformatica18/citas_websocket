import { useEffect } from "react";

export default function EditModal({ visible, onClose, data, setData, onSave }: any) {
    if (!visible || !data) return null;

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === "Escape") onClose();
        };
        window.addEventListener("keydown", handler);
        return () => window.removeEventListener("keydown", handler);
    }, []);

    const closeIfOutside = (e: any) => {
        if (e.target.classList.contains("modal-overlay")) onClose();
    };

    return (
        <div
            className="modal-overlay fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 animate-fadeIn"
            onClick={closeIfOutside}
        >
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-6 w-full max-w-lg relative animate-scaleIn">

                <button
                    onClick={onClose}
                    className="absolute top-2 right-2 text-gray-500 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white text-2xl"
                >
                    ×
                </button>

                <h2 className="text-xl font-bold mb-4">Editar Resultado</h2>

                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-semibold">URL</label>
                        <input
                            type="text"
                            className="w-full border rounded px-2 py-1 dark:bg-gray-800 dark:border-gray-700"
                            value={data.url}
                            onChange={(e) => setData({ ...data, url: e.target.value })}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-semibold">Categoría</label>
                        <input
                            type="text"
                            className="w-full border rounded px-2 py-1 dark:bg-gray-800 dark:border-gray-700"
                            value={data.category || ""}
                            onChange={(e) => setData({ ...data, category: e.target.value })}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-semibold">Estado</label>
                        <select
                            className="w-full border rounded px-2 py-1 dark:bg-gray-800 dark:border-gray-700"
                            value={data.status}
                            onChange={(e) => setData({ ...data, status: e.target.value })}
                        >
                            <option value="pending">pending</option>
                            <option value="completed">completed</option>
                            <option value="error">error</option>
                        </select>
                    </div>
                </div>

                <div className="mt-4 flex justify-end gap-2">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 bg-gray-300 dark:bg-gray-700 rounded"
                    >
                        Cancelar
                    </button>

                    <button
                        onClick={onSave}
                        className="px-4 py-2 bg-blue-600 text-white rounded"
                    >
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    );
}
