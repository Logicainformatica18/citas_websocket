import { useState } from "react";
import axios from "axios";
import { X, Search } from "lucide-react";
import PreviewView, { type Preview } from "./PreviewView";

export type Keyword = {
    id: number;
    pattern: string;
    type: "exclude" | "exception";
    match_mode: "text" | "regex";
    whole_word: boolean;
    category: string | null;
    is_active: boolean;
    origin: "seed" | "manual" | "ai";
    notes: string | null;
    matches_count: number | null;
    counted_at: string | null;
    updated_at: string | null;
};

type Props = {
    keyword: Keyword | null; // null = nuevo
    categories: string[];
    onClose: () => void;
    onSaved: (k: Keyword, message: string) => void;
};

/** Agregar o editar un patrón, con "Probar" para ver coincidencias antes de guardar. */
export default function KeywordModal({ keyword, categories, onClose, onSaved }: Props) {
    const [form, setForm] = useState({
        pattern: keyword?.pattern ?? "",
        type: keyword?.type ?? "exclude",
        match_mode: keyword?.match_mode ?? "text",
        whole_word: keyword?.whole_word ?? false,
        category: keyword?.category ?? "",
        is_active: keyword?.is_active ?? true,
        notes: keyword?.notes ?? "",
    });
    const [preview, setPreview] = useState<Preview | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const set = (k: string, v: any) => {
        setForm((f) => ({ ...f, [k]: v }));
        setPreview(null);
    };

    const test = async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.post("/job-offers/title-exclusions/preview", { ...form, keyword_id: keyword?.id });
            setPreview(data);
        } catch (e: any) {
            setError(e.response?.data?.message ?? "No se pudo probar el patrón.");
        }
        setLoading(false);
    };

    const save = async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = keyword
                ? await axios.put(`/job-offers/title-exclusions/${keyword.id}`, form)
                : await axios.post("/job-offers/title-exclusions", form);
            onSaved(data.keyword, data.message);
        } catch (e: any) {
            setError(e.response?.data?.message ?? "No se pudo guardar.");
        }
        setLoading(false);
    };

    const input = "border rounded-lg px-3 py-2 w-full dark:bg-gray-800 dark:border-gray-700";

    return (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-6 w-full max-w-4xl max-h-[92vh] overflow-y-auto border dark:border-gray-700">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-xl font-bold">{keyword ? "Editar patrón" : "Agregar patrón"}</h2>
                    <button onClick={onClose} className="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full"><X className="w-5 h-5" /></button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <label className="md:col-span-2 flex flex-col gap-1">
                        <span className="font-semibold">Patrón</span>
                        <input className={`${input} font-mono`} value={form.pattern} onChange={(e) => set("pattern", e.target.value)}
                            placeholder={form.match_mode === "regex" ? "p. ej. \\bsales?\\b" : "p. ej. call center"} />
                        <span className="text-xs text-gray-500">
                            Se compara con el título en minúsculas y sin tildes. Texto: literal. Regex: expresión PCRE (\b = borde de palabra).
                        </span>
                    </label>
                    <label className="flex flex-col gap-1">
                        <span className="font-semibold">Tipo</span>
                        <select className={input} value={form.type} onChange={(e) => set("type", e.target.value)}>
                            <option value="exclude">Excluir (rol no TI)</option>
                            <option value="exception">Excepción (protege títulos de TI; gana sobre las exclusiones)</option>
                        </select>
                    </label>
                    <label className="flex flex-col gap-1">
                        <span className="font-semibold">Comparación</span>
                        <select className={input} value={form.match_mode} onChange={(e) => set("match_mode", e.target.value)}>
                            <option value="text">Texto simple</option>
                            <option value="regex">Expresión regular</option>
                        </select>
                    </label>
                    <label className="flex flex-col gap-1">
                        <span className="font-semibold">Categoría</span>
                        <input className={input} list="exclusion-categories" value={form.category} onChange={(e) => set("category", e.target.value)} />
                        <datalist id="exclusion-categories">{categories.map((c) => <option key={c} value={c} />)}</datalist>
                    </label>
                    <div className="flex items-end gap-6 pb-2">
                        <label className="flex items-center gap-2"><input type="checkbox" checked={form.whole_word} onChange={(e) => set("whole_word", e.target.checked)} /> Palabra completa</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={form.is_active} onChange={(e) => set("is_active", e.target.checked)} /> Activo</label>
                    </div>
                    <label className="md:col-span-2 flex flex-col gap-1">
                        <span className="font-semibold">Notas</span>
                        <textarea className={input} rows={2} value={form.notes} onChange={(e) => set("notes", e.target.value)} />
                    </label>
                </div>

                {error && <div className="mt-3 text-sm text-red-600">{error}</div>}

                <div className="flex gap-3 mt-4">
                    <button onClick={test} disabled={loading || !form.pattern.trim()}
                        className="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 flex items-center gap-2 disabled:opacity-50">
                        <Search className="w-4 h-4" /> Probar (coincidencias y ejemplos)
                    </button>
                    <button onClick={save} disabled={loading || !form.pattern.trim()}
                        className="px-4 py-2 rounded-lg bg-[#1CBCE8] hover:bg-[#17A8D0] text-white disabled:opacity-50">
                        {loading ? "…" : "Guardar"}
                    </button>
                </div>

                {preview && <div className="mt-4"><PreviewView preview={preview} type={form.type as any} /></div>}
            </div>
        </div>
    );
}
