import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage, router, Link } from "@inertiajs/react";
import { useMemo, useState } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Plus, Pencil, Trash2, Eye, ShieldOff, Sparkles, X, AlertTriangle, ArrowLeft } from "lucide-react";
import KeywordModal, { type Keyword } from "./KeywordModal";
import PreviewView, { type Preview } from "./PreviewView";
import AnalysisPanel from "./AnalysisPanel";
import SuggestionsPanel from "./SuggestionsPanel";
import { ProcessBar, useProcess, isActive, type JobOfferProcess } from "../ProcessStatus";

const breadcrumbs: BreadcrumbItem[] = [
    { title: "Ofertas de Empleo", href: "/job-offers" },
    { title: "Exclusión por título", href: "/job-offers/title-exclusions" },
];

const fmt = (n: number | undefined | null) => (n ?? 0).toLocaleString("es-PE");
const ORIGIN = { seed: "semilla", manual: "manual", ai: "IA" } as const;

export default function TitleExclusionsIndex() {
    const props = usePage().props as any;
    const [keywords, setKeywords] = useState<Keyword[]>(props.keywords);
    const [summary, setSummary] = useState(props.summary);
    const [tab, setTab] = useState<"list" | "analysis" | "suggest" | "history">("list");
    const [search, setSearch] = useState("");
    const [typeFilter, setTypeFilter] = useState("");
    const [categoryFilter, setCategoryFilter] = useState("");
    const [activeFilter, setActiveFilter] = useState("");
    const [editing, setEditing] = useState<Keyword | null | undefined>(undefined); // undefined = cerrado, null = nuevo
    const [examples, setExamples] = useState<{ keyword: Keyword; preview: Preview } | null>(null);
    const [loadingExamples, setLoadingExamples] = useState<number | null>(null);

    const reload = () => router.reload({ only: ["keywords", "summary", "latest", "processes"], onSuccess: (p: any) => { setKeywords(p.props.keywords); setSummary(p.props.summary); } });
    const finished = (p: JobOfferProcess, s?: any) => {
        if (s) setSummary(s);
        if (p.status === "failed") Swal.fire({ icon: "error", title: `${p.label} falló`, text: p.message ?? "" });
        reload();
    };

    // Un proceso "exclusivo" a la vez (aplicar, analizar, eliminar, restaurar); el de IA va aparte
    const latestExclusive = [props.latest.apply, props.latest.analyze, props.latest.delete, props.latest.restore]
        .filter(Boolean).sort((a: any, b: any) => b.id - a.id)[0] ?? null;
    const [exclusive, setExclusive] = useProcess(latestExclusive, finished);
    const [suggestion, setSuggestion] = useProcess(props.latest.suggest, finished);
    const busy = !!exclusive && (exclusive.status === "queued" || exclusive.status === "running");

    const categories: string[] = useMemo(
        () => Array.from(new Set([...(props.categories ?? []), ...keywords.map((k) => k.category).filter(Boolean)])) as string[],
        [keywords],
    );

    const visible = keywords.filter((k) =>
        (!search || k.pattern.toLowerCase().includes(search.toLowerCase()) || (k.notes ?? "").toLowerCase().includes(search.toLowerCase())) &&
        (!typeFilter || k.type === typeFilter) &&
        (!categoryFilter || k.category === categoryFilter) &&
        (activeFilter === "" || String(k.is_active) === activeFilter),
    );

    const start = async (url: string, body: any, then: (p: JobOfferProcess) => void) => {
        try {
            const { data } = await axios.post(url, body);
            then(data.process);
            Swal.fire({ icon: "info", title: data.message, timer: 1800, showConfirmButton: false });
        } catch (e: any) {
            Swal.fire({ icon: "warning", title: "No se pudo iniciar", text: e.response?.data?.message ?? "Error" });
        }
    };

    const apply = async () => {
        const ok = await Swal.fire({
            icon: "question", title: "Excluir de los indicadores",
            html: "Marca las ofertas cuyo título coincide con la lista activa (no borra nada) y luego recalcula los rankings de Lenguajes, Tecnologías y Certificaciones. Corre en cola.",
            showCancelButton: true, confirmButtonText: "Aplicar", cancelButtonText: "Cancelar",
        });
        if (ok.isConfirmed) start("/job-offers/title-exclusions/apply", {}, setExclusive);
    };

    const toggle = async (k: Keyword) => {
        const { data } = await axios.patch(`/job-offers/title-exclusions/${k.id}/toggle`);
        setKeywords((list) => list.map((x) => (x.id === k.id ? data.keyword : x)));
        setSummary((s: any) => ({ ...s, pending_changes: true }));
    };

    const remove = async (k: Keyword) => {
        const ok = await Swal.fire({ icon: "warning", title: "¿Eliminar el patrón?", text: k.pattern, showCancelButton: true, confirmButtonText: "Eliminar", cancelButtonText: "Cancelar" });
        if (!ok.isConfirmed) return;
        const { data } = await axios.delete(`/job-offers/title-exclusions/${k.id}`);
        setKeywords((list) => list.filter((x) => x.id !== k.id));
        setSummary((s: any) => ({ ...s, pending_changes: true }));
        Swal.fire({ icon: "success", title: data.message, timer: 2500, showConfirmButton: false });
    };

    const showExamples = async (k: Keyword) => {
        setLoadingExamples(k.id);
        try {
            const { data } = await axios.get(`/job-offers/title-exclusions/${k.id}/examples`);
            setExamples({ keyword: k, preview: data });
        } catch (e: any) {
            Swal.fire({ icon: "error", title: "No se pudieron cargar los ejemplos", text: e.response?.data?.message ?? "" });
        }
        setLoadingExamples(null);
    };

    const decide = async (processId: number, keys: string[], approve: boolean) => {
        try {
            const { data } = await axios.post(`/job-offers/title-exclusions/suggestions/${processId}/${approve ? "approve" : "reject"}`, { keys });
            setSuggestion(data.process);
            if (approve) {
                Swal.fire({ icon: "success", title: data.message });
                reload();
                setSummary((s: any) => ({ ...s, pending_changes: true }));
            }
        } catch (e: any) {
            Swal.fire({ icon: "error", title: "Error", text: e.response?.data?.message ?? "" });
        }
    };

    const tabs = [["list", `Lista (${keywords.length})`], ["analysis", "Analizar basura"], ["suggest", "Sugerencias IA"], ["history", "Historial"]] as const;
    const pct = summary.total_offers ? ((summary.excluded_offers * 100) / summary.total_offers).toFixed(1) : "0";

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen space-y-5">
                <div className="flex flex-wrap items-center gap-3 border-b pb-4 dark:border-gray-700">
                    <Link href="/job-offers" className="text-gray-500 hover:text-gray-800"><ArrowLeft className="w-5 h-5" /></Link>
                    <h1 className="text-2xl font-semibold text-[#0C647A] dark:text-[#1CBCE8] flex items-center gap-2">
                        <ShieldOff className="w-6 h-6" /> Exclusión de ofertas por título (roles no TI)
                    </h1>
                    <div className="ml-auto flex gap-2">
                        <button onClick={() => setEditing(null)} className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md flex items-center gap-2">
                            <Plus className="w-4 h-4" /> Agregar patrón
                        </button>
                        <button disabled={isActive(suggestion)} onClick={() => { setTab("suggest"); start("/job-offers/title-exclusions/suggest", {}, setSuggestion); }}
                            className="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md flex items-center gap-2 disabled:opacity-50">
                            <Sparkles className="w-4 h-4" /> Sugerir con IA
                        </button>
                        <button disabled={busy} onClick={apply} className="px-4 py-2 bg-[#0C647A] hover:bg-[#0a5466] text-white rounded-md flex items-center gap-2 disabled:opacity-50">
                            <ShieldOff className="w-4 h-4" /> Excluir de los indicadores
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div className="rounded-lg border dark:border-gray-700 bg-white dark:bg-gray-800 p-3">
                        <div className="text-xs text-gray-500">Ofertas excluidas de los indicadores</div>
                        <div className="text-2xl font-semibold">{fmt(summary.excluded_offers)} <span className="text-sm text-gray-500">de {fmt(summary.total_offers)} ({pct} %)</span></div>
                        <Link href="/job-offers?excluded=excluded" className="text-xs text-[#1CBCE8] hover:underline">ver ofertas excluidas</Link>
                    </div>
                    <div className="rounded-lg border dark:border-gray-700 bg-white dark:bg-gray-800 p-3">
                        <div className="text-xs text-gray-500">Patrones activos</div>
                        <div className="text-2xl font-semibold">
                            {keywords.filter((k) => k.is_active && k.type === "exclude").length} <span className="text-sm text-gray-500">exclusiones</span>{" "}
                            {keywords.filter((k) => k.is_active && k.type === "exception").length} <span className="text-sm text-gray-500">excepciones</span>
                        </div>
                    </div>
                    {summary.pending_changes ? (
                        <div className="rounded-lg border border-amber-400 bg-amber-50 dark:bg-amber-900/20 p-3 flex gap-2">
                            <AlertTriangle className="w-5 h-5 text-amber-600 shrink-0" />
                            <div>La lista cambió después de la última aplicación. Las ofertas NUEVAS ya se filtran con la lista activa; para las existentes,
                                ejecuta <b>Excluir de los indicadores</b>.</div>
                        </div>
                    ) : (
                        <div className="rounded-lg border dark:border-gray-700 bg-white dark:bg-gray-800 p-3 text-gray-600 dark:text-gray-300">
                            Las ofertas marcadas corresponden a la lista activa. Las nuevas con título excluido no se guardan.
                        </div>
                    )}
                </div>

                {isActive(exclusive) && <ProcessBar process={exclusive} />}
                {isActive(suggestion) && <ProcessBar process={suggestion} />}

                <div className="flex gap-1 border-b dark:border-gray-700">
                    {tabs.map(([key, label]) => (
                        <button key={key} onClick={() => setTab(key)}
                            className={`px-4 py-2 -mb-px border-b-2 ${tab === key ? "border-[#1CBCE8] text-[#0C647A] dark:text-[#1CBCE8] font-semibold" : "border-transparent text-gray-500"}`}>
                            {label}
                        </button>
                    ))}
                </div>

                {tab === "list" && (
                    <div className="space-y-3">
                        <div className="flex flex-wrap gap-2 text-sm">
                            <input className="border rounded-lg px-3 py-2 dark:bg-gray-800 dark:border-gray-700 w-64" placeholder="Buscar patrón o nota…" value={search} onChange={(e) => setSearch(e.target.value)} />
                            <select className="border rounded-lg px-2 dark:bg-gray-800 dark:border-gray-700" value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)}>
                                <option value="">Todos los tipos</option><option value="exclude">Excluir</option><option value="exception">Excepción</option>
                            </select>
                            <select className="border rounded-lg px-2 dark:bg-gray-800 dark:border-gray-700" value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)}>
                                <option value="">Todas las categorías</option>{categories.map((c) => <option key={c}>{c}</option>)}
                            </select>
                            <select className="border rounded-lg px-2 dark:bg-gray-800 dark:border-gray-700" value={activeFilter} onChange={(e) => setActiveFilter(e.target.value)}>
                                <option value="">Activos e inactivos</option><option value="true">Solo activos</option><option value="false">Solo inactivos</option>
                            </select>
                            <span className="self-center text-gray-500">{visible.length} patrones</span>
                        </div>
                        <div className="overflow-x-auto border rounded-xl shadow bg-white dark:bg-gray-900 dark:border-gray-700">
                            <table className="min-w-full text-sm">
                                <thead className="bg-[#1CBCE8]/90 dark:bg-[#1CBCE8]/30 text-white dark:text-[#1CBCE8] uppercase text-xs">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Patrón</th><th className="px-3 py-2">Tipo</th><th className="px-3 py-2">Comparación</th>
                                        <th className="px-3 py-2 text-left">Categoría</th>
                                        <th className="px-3 py-2" title="Ofertas cuyo título coincide (último 'Excluir de los indicadores')">Coinciden</th>
                                        <th className="px-3 py-2">Origen</th><th className="px-3 py-2">Activo</th><th className="px-3 py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {visible.map((k) => (
                                        <tr key={k.id} className={k.is_active ? "" : "opacity-50"}>
                                            <td className="px-3 py-1.5 font-mono" title={k.notes ?? ""}>{k.pattern}</td>
                                            <td className="px-3 py-1.5 text-center">
                                                <span className={`px-2 py-0.5 rounded text-xs ${k.type === "exception" ? "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300" : "bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300"}`}>
                                                    {k.type === "exception" ? "excepción" : "excluir"}
                                                </span>
                                            </td>
                                            <td className="px-3 py-1.5 text-center text-xs">{k.match_mode === "regex" ? "regex" : "texto"}{k.whole_word ? " · palabra completa" : ""}</td>
                                            <td className="px-3 py-1.5">{k.category ?? "—"}</td>
                                            <td className="px-3 py-1.5 text-right" title={k.counted_at ? `contado el ${k.counted_at}` : "aún no contado: ver ejemplos"}>
                                                {k.matches_count === null ? "—" : fmt(k.matches_count)}
                                            </td>
                                            <td className="px-3 py-1.5 text-center text-xs">{ORIGIN[k.origin] ?? k.origin}</td>
                                            <td className="px-3 py-1.5 text-center"><input type="checkbox" checked={k.is_active} onChange={() => toggle(k)} /></td>
                                            <td className="px-3 py-1.5 whitespace-nowrap">
                                                <button onClick={() => showExamples(k)} className="text-[#1CBCE8] hover:underline mr-3 inline-flex items-center gap-1">
                                                    <Eye className="w-4 h-4" /> {loadingExamples === k.id ? "…" : "ejemplos"}
                                                </button>
                                                <button onClick={() => setEditing(k)} className="mr-3" title="Editar"><Pencil className="w-4 h-4 inline" /></button>
                                                <button onClick={() => remove(k)} className="text-red-600" title="Eliminar"><Trash2 className="w-4 h-4 inline" /></button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {tab === "analysis" && (
                    <AnalysisPanel
                        analysis={exclusive?.type === "analyze" ? exclusive : props.latest.analyze}
                        lastDelete={exclusive?.type === "delete" ? exclusive : props.latest.delete}
                        busy={busy}
                        confirmWord={props.confirmWord}
                        onAnalyze={(year, period) => start("/job-offers/title-exclusions/analyze", { year, period }, setExclusive)}
                        onApply={apply}
                        onDelete={(analysisId, confirm) => start("/job-offers/title-exclusions/delete-offers", { analysis_id: analysisId, confirm }, setExclusive)}
                        onRestore={async (id) => {
                            const ok = await Swal.fire({ icon: "question", title: "Restaurar la eliminación", text: "Se vuelven a insertar las ofertas y sus asociaciones desde el respaldo, y se recalculan los rankings.", showCancelButton: true, confirmButtonText: "Restaurar", cancelButtonText: "Cancelar" });
                            if (ok.isConfirmed) start(`/job-offers/title-exclusions/restore/${id}`, {}, setExclusive);
                        }}
                    />
                )}

                {tab === "suggest" && (
                    <SuggestionsPanel process={suggestion} busy={isActive(suggestion)} onDecide={decide}
                        onSuggest={() => start("/job-offers/title-exclusions/suggest", {}, setSuggestion)} />
                )}

                {tab === "history" && (
                    <div className="space-y-2">
                        {(props.processes ?? []).map((p: JobOfferProcess) => (
                            <div key={p.id}>
                                <ProcessBar process={p} />
                                {p.status === "done" && p.result && Object.keys(p.result).length > 0 && (
                                    <div className="text-xs text-gray-500 px-3 pb-1">{describe(p)}</div>
                                )}
                            </div>
                        ))}
                        {!props.processes?.length && <div className="text-sm text-gray-500">Sin procesos registrados.</div>}
                    </div>
                )}
            </div>

            {editing !== undefined && (
                <KeywordModal keyword={editing} categories={categories} onClose={() => setEditing(undefined)}
                    onSaved={(k, message) => {
                        setKeywords((list) => (list.some((x) => x.id === k.id) ? list.map((x) => (x.id === k.id ? k : x)) : [...list, k]));
                        setSummary((s: any) => ({ ...s, pending_changes: true }));
                        setEditing(undefined);
                        Swal.fire({ icon: "success", title: message, timer: 1500, showConfirmButton: false });
                    }} />
            )}

            {examples && (
                <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-6 w-full max-w-4xl max-h-[92vh] overflow-y-auto border dark:border-gray-700">
                        <div className="flex justify-between items-center mb-3">
                            <h2 className="text-lg font-bold">Ejemplos de <code>{examples.keyword.pattern}</code></h2>
                            <button onClick={() => setExamples(null)} className="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full"><X className="w-5 h-5" /></button>
                        </div>
                        <PreviewView preview={examples.preview} type={examples.keyword.type} />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

function describe(p: JobOfferProcess): string {
    const r = p.result ?? {};
    switch (p.type) {
        case "apply":
            return `${r.dry_run ? "[simulación] " : ""}${r.cleared ? "[marcas quitadas] " : ""}excluidas: ${fmt(r.excluded)} · nuevas: ${fmt(r.newly_excluded)} · vuelven a válidas: ${fmt(r.restored_to_valid)}`;
        case "analyze":
            return `excluiría ${fmt(r.excluded)} ofertas (${fmt(r.newly_excluded)} nuevas) · ${fmt(r.associations_total)} asociaciones`;
        case "delete":
            return `${fmt(r.offers_deleted)} ofertas borradas · respaldo de ${fmt(r.backup_rows)} filas${r.restorable ? "" : " · restaurada"}`;
        case "restore":
            return `${fmt(r.offers_restored)} ofertas restauradas`;
        case "suggest":
            return `${r.received ?? 0} sugerencias recibidas · ${r.approved ?? 0} aprobadas`;
        default:
            return "";
    }
}
