import React, { useState, useEffect } from "react";
import axios from "axios";
import { Loader2, Link as LinkIcon, PlayCircle, ListChecks } from "lucide-react";
import { toast } from "sonner";
import { router } from "@inertiajs/react";

export default function TabWeb({ form, setForm, isEdit, sourceId }) {
    const [processingLinks, setProcessingLinks] = useState(false);
    const [processingData, setProcessingData] = useState(false);
    const [hasPendingLinks, setHasPendingLinks] = useState(false);

    // 📌 Cargar estado de enlaces pendientes
    useEffect(() => {
        if (!sourceId) return;

        axios.get(`/scraping/${sourceId}/pending-count`)
            .then((res) => setHasPendingLinks(res.data.pending > 0))
            .catch(() => setHasPendingLinks(false));
    }, [sourceId]);

    // ▶️ BOTÓN 1 — OBTENER ENLACES
    const getLinks = async () => {
        setProcessingLinks(true);
        try {
            await axios.post(`/scraping-sources/${sourceId}/extract-links`);
            toast.success("Extracción de enlaces iniciada…");

            setTimeout(() => router.reload(), 1500);
        } catch {
            toast.error("Error al extraer enlaces");
        } finally {
            setProcessingLinks(false);
        }
    };

    // ▶️ BOTÓN 2 — PROCESAR DATOS
    const processData = async () => {
        setProcessingData(true);
        try {
            await axios.post(`/scraping-sources/${sourceId}/process-data`);
            toast.success("Procesamiento iniciado…");

            setTimeout(() => {
                router.visit(`/scraping/${sourceId}/results`);
            }, 2000);
        } catch {
            toast.error("Error al procesar enlaces");
        } finally {
            setProcessingData(false);
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

            {/* BOTONES */}
            {isEdit && (
                <div className="space-y-3">

                    {/* 🔵 BOTÓN 1 — Obtener enlaces */}
                    <button
                        onClick={getLinks}
                        disabled={processingLinks}
                        className="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold flex items-center justify-center gap-2"
                    >
                        {processingLinks ? (
                            <Loader2 className="animate-spin w-5 h-5" />
                        ) : (
                            <LinkIcon className="w-5 h-5" />
                        )}
                        Obtener Enlaces Iniciales
                    </button>

                    {/* 🟣 BOTÓN 2 — Procesar Enlaces */}
                    <button
                        onClick={processData}
                        disabled={!hasPendingLinks || processingData}
                        className={`
                            w-full px-4 py-2 rounded-lg font-semibold flex items-center justify-center gap-2
                            ${hasPendingLinks
                                ? "bg-purple-600 hover:bg-purple-700 text-white"
                                : "bg-gray-400 text-white cursor-not-allowed opacity-50"}
                        `}
                    >
                        {processingData ? (
                            <Loader2 className="animate-spin w-5 h-5" />
                        ) : (
                            <ListChecks className="w-5 h-5" />
                        )}
                        Procesar Datos de Enlaces
                    </button>

                    {/* 🟦 BOTÓN 3 — Ver Resultados */}
                    <a
                        href={`/scraping/${sourceId}/results`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block text-center w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold"
                    >
                        Ver Resultados Web
                    </a>
                </div>
            )}
        </div>
    );
}
