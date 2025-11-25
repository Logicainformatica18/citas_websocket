import React, { useState } from "react";
import axios from "axios";
import { Loader2, PlayCircle } from "lucide-react";
import { toast } from "sonner";

export default function TabWeb({
    form,
    setForm,
    isEdit,
    sourceId,
}: {
    form: any;
    setForm: (data: any) => void;
    isEdit: boolean;
    sourceId: number | null;
}) {
    const [processing, setProcessing] = useState(false);

 const processSource = async () => {
    if (!sourceId) return;

    try {
        setProcessing(true);

        await axios.post(`/scraping-sources/${sourceId}/process`);
        toast.success("Scraping iniciado…");

        // 🔥 Esperar unos segundos a que el Job termine
        setTimeout(async () => {
            const res = await axios.get(`/scraping-sources/${sourceId}`);
            setForm(prev => ({
                ...prev,
                scrape_status: res.data.source.scrape_status,
                scrape_message: res.data.source.scrape_message,
                scrape_result: res.data.source.scrape_result,
                last_scraped_at: res.data.source.last_scraped_at,
            }));
        }, 3000); // 3 segundos para que termine el Job

    } catch (e) {
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

            {/* ESTADO */}
           {isEdit && (
    <div className="p-3 rounded-lg border bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
        <div className="text-sm font-semibold">Estado actual:</div>
        <div className="mt-1 text-blue-600 dark:text-blue-400">
            {form.scrape_status ?? "Sin procesar"}
        </div>
    </div>
)}


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
        </div>
    );
}
