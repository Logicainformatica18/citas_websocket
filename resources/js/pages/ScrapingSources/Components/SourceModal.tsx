import React, { useState, useRef } from "react";
import axios from "axios";
import {
    FileText,
    Globe,
    KeyRound,
    Database,
    FileSpreadsheet,
    XCircle,
    Loader2,
} from "lucide-react";
import { toast } from "sonner";

import TabPdf from "./Tabs/TabPdf";
import TabWeb from "./Tabs/TabWeb";
import TabApi from "./Tabs/TabApi";
import TabExcel from "./Tabs/TabExcel";

export default function SourceModal({
    data,
    onClose,
    onSaved,
}: {
    data: any;
    onClose: () => void;
    onSaved: (item: any) => void;
}) {
    const isEdit = !!data?.id;
    const modalRef = useRef<HTMLDivElement>(null);

    const DEFAULT_PROMPT = `Extrae todas las tecnologías, herramientas, lenguajes, metodologías y tendencias relevantes del contenido.
Devuélvelas en un JSON organizado.`;

    const [activeTab, setActiveTab] = useState("pdf");
    const [loading, setLoading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);

   const [form, setForm] = useState({
    name: data?.name || "",
    url: data?.url || "",
    frequency: data?.frequency || "",
    notes: data?.notes || "",

    web_prompt: data?.web_prompt || DEFAULT_PROMPT,

    api_url: data?.api_url || "",
    api_key: data?.api_key || "",
    pdf_file: null,
    excel_file: null,

    // 🔥 Campos que faltaban
    scrape_status: data?.scrape_status || null,
    scrape_message: data?.scrape_message || null,
    scrape_result: data?.scrape_result || null,
    last_scraped_at: data?.last_scraped_at || null,
});


    const handleFile = (e: React.ChangeEvent<HTMLInputElement>, field: string) => {
        const file = e.target.files?.[0] || null;
        setForm({ ...form, [field]: file });
    };

    /* ======================================================
       SUBMIT — con spinner y barra de progreso
    ====================================================== */
    const submit = async () => {
        try {
            setLoading(true);
            setUploadProgress(0);

            const fd = new FormData();
            Object.entries(form).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== "") {
                    fd.append(k, v as any);
                }
            });

            const url = isEdit
                ? `/scraping-sources/${data.id}`
                : `/scraping-sources`;

            if (isEdit) fd.append("_method", "PUT");

            const res = await axios.post(url, fd, {
                headers: { "Content-Type": "multipart/form-data" },
                onUploadProgress: (event) => {
                    if (event.total) {
                        const percent = Math.round((event.loaded * 100) / event.total);
                        setUploadProgress(percent);
                    }
                },
            });

            toast.success(isEdit ? "Fuente actualizada" : "Fuente creada");

            if (res.data.source) onSaved(res.data.source);

            onClose();
        } catch (err) {
            console.error(err);
            toast.error("Error al guardar");
        } finally {
            setLoading(false);
            setUploadProgress(0);
        }
    };

    /* ======================================================
       CERRAR AL HACER CLICK AFUERA
    ====================================================== */
    const handleOutside = (e: any) => {
        if (modalRef.current && !modalRef.current.contains(e.target)) {
            onClose();
        }
    };

    return (
        <div
            className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-6"
            onClick={handleOutside}
        >
            <div
                ref={modalRef}
                className="
                    bg-white dark:bg-gray-900
                    p-7 rounded-2xl
                    w-full max-w-7xl     /* MÁS ANCHO */
                    shadow-2xl border dark:border-gray-700
                    animate-fadeIn
                    max-h-[90vh] overflow-hidden
                "
            >
                {/* HEADER */}
                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-3xl font-bold flex items-center gap-2 text-gray-900 dark:text-gray-100">
                        <Database className="w-7 h-7 text-blue-600" />
                        {isEdit ? "Editar Fuente" : "Nueva Fuente"}
                    </h2>

                    <button onClick={onClose} className="text-gray-500 hover:text-red-500">
                        <XCircle className="w-8 h-8" />
                    </button>
                </div>

                {/* === LAYOUT EN 2 COLUMNAS === */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {/* COLUMNA IZQUIERDA — DATOS GENERALES */}
                    <div className="pr-4 border-r dark:border-gray-700 overflow-y-auto max-h-[60vh]">

                        <div className="space-y-5">
                            <div>
                                <label className="text-sm font-medium">Nombre</label>
                                <input
                                    className="mt-1 w-full px-3 py-2 rounded-md border dark:bg-gray-800 dark:text-white"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium">URL Principal</label>
                                <input
                                    className="mt-1 w-full px-3 py-2 rounded-md border dark:bg-gray-800 dark:text-white"
                                    value={form.url}
                                    onChange={(e) => setForm({ ...form, url: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium">Frecuencia</label>
                                <select
                                    className="mt-1 w-full px-3 py-2 rounded-md border dark:bg-gray-800 dark:text-white"
                                    value={form.frequency}
                                    onChange={(e) => setForm({ ...form, frequency: e.target.value })}
                                >
                                    <option value="">Seleccione…</option>
                                    <option value="Anual">Anual</option>
                                    <option value="Semestral">Semestral</option>
                                    <option value="Trimestral">Trimestral</option>
                                    <option value="VARIABLE">VARIABLE</option>
                                </select>
                            </div>

                            <div>
                                <label className="text-sm font-medium">Notas</label>
                                <textarea
                                    className="mt-1 w-full h-28 px-3 py-2 rounded-md border dark:bg-gray-800 dark:text-white"
                                    value={form.notes}
                                    onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                />
                            </div>
                        </div>
                    </div>

                    {/* COLUMNA DERECHA — TABS */}
                    <div className="pl-4 overflow-y-auto max-h-[60vh]">

                        {/* TABS */}
                        <div className="border-b mb-4 border-gray-300 dark:border-gray-700">
                            <div className="flex gap-6 text-sm font-semibold">
                                {[
                                    { key: "pdf", label: "PDF", icon: FileText },
                                    { key: "web", label: "WEB", icon: Globe },
                                    { key: "excel", label: "Excel / CSV", icon: FileSpreadsheet },
                                    { key: "api", label: "API", icon: KeyRound },
                                ].map((tab) => (
                                    <button
                                        key={tab.key}
                                        onClick={() => setActiveTab(tab.key)}
                                        className={`
                                            pb-2 flex items-center gap-2 transition
                                            ${
                                                activeTab === tab.key
                                                    ? "text-blue-600 border-b-2 border-blue-600"
                                                    : "text-gray-600 dark:text-gray-300 hover:text-blue-400"
                                            }
                                        `}
                                    >
                                        <tab.icon className="w-4 h-4" />
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* CONTENIDO DEL TAB */}
                        <div className="pr-2">
                            {activeTab === "pdf" && (
                                <TabPdf
                                existingPdf={data?.pdf_path}
                                handleFile={handleFile}
                                  sourceId={data?.id}
                                />
                            )}

                            {activeTab === "web" && (
    <TabWeb
        form={form}
        setForm={setForm}
        isEdit={isEdit}
        sourceId={data?.id}

    />
)}


                            {activeTab === "api" && (
                                <TabApi form={form} setForm={setForm} />
                            )}

                            {activeTab === "excel" && (
                                <TabExcel handleFile={handleFile} />
                            )}
                        </div>

                    </div>

                </div>

                {/* FOOTER */}
                <div className="mt-6 pt-4 border-t border-gray-300 dark:border-gray-700 flex justify-end gap-3 items-center">
                    {uploadProgress > 0 && (
                        <div className="w-48 h-2 bg-gray-300 dark:bg-gray-700 rounded-lg overflow-hidden">
                            <div
                                className="h-full bg-blue-600 transition-all"
                                style={{ width: `${uploadProgress}%` }}
                            />
                        </div>
                    )}

                    <button
                        onClick={onClose}
                        disabled={loading}
                        className="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg"
                    >
                        Cancelar
                    </button>

                    <button
                        onClick={submit}
                        disabled={loading}
                        className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold flex items-center gap-2"
                    >
                        {loading && <Loader2 className="animate-spin w-5 h-5" />}
                        {isEdit ? "Actualizar" : "Guardar"}
                    </button>
                </div>

            </div>
        </div>
    );
}
