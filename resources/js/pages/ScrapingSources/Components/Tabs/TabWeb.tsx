import React, { useState } from "react";
import axios from "axios";
import { Loader2, PlayCircle } from "lucide-react";
import { toast } from "sonner";
import { router } from "@inertiajs/react";
import { Link } from "@inertiajs/react";


export default function TabWeb({
    form,
    setForm,
    isEdit,
    sourceId,
}) {
    const [processing, setProcessing] = useState(false);

    const processSource = async () => {
        if (!sourceId) return;

        try {
            setProcessing(true);

            await axios.post(`/scraping-sources/${sourceId}/process`);

            toast.success("Scraping iniciado…");

            // 🔥 Esperar unos segundos a que el Job avance
            setTimeout(() => {
                // 👉 Redirigir al listado de resultados web
                router.visit(`/scraping/${sourceId}/results`);
            }, 2000);

        } catch (e) {
            console.error(e);
            toast.error("Error al iniciar el scraping");
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div className="space-y-5">

            {/* PROMPT */}
            <div>
                <label className="text-sm font-medium">Prompt WEB</label>
                <textarea
                    className="mt-1 w-full h-40 px-3 py-2 rounded-md border dark:bg-gray-800 dark:text-white"
                    value={form.web_prompt}
                    onChange={(e) =>
                        setForm({ ...form, web_prompt: e.target.value })
                    }
                />
            </div>

            {/* BOTÓN PROCESAR */}
            {isEdit && (
                <button
                    onClick={processSource}
                    disabled={processing}
                    className="
                        px-4 py-2 w-full
                        bg-purple-600 hover:bg-purple-700
                        text-white rounded-lg
                        flex items-center justify-center gap-2
                        font-semibold
                    "
                >
                    {processing ? (
                        <Loader2 className="animate-spin w-5 h-5" />
                    ) : (
                        <PlayCircle className="w-5 h-5" />
                    )}
                    Procesar Fuente
                </button>
            )}
            {isEdit && sourceId && (
    <Link
        href={`/scraping/${sourceId}/results`}
        className="block text-center px-4 py-2 mt-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold"
    >
        Ver Resultados Web
    </Link>
)}

        </div>
    );
}
