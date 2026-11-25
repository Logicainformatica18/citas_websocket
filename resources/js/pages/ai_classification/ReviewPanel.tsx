import axios from "axios";
import Swal from "sweetalert2";
import { useEffect, useState } from "react";
import { ExternalLink, Check, X } from "lucide-react";
import { fmt } from "./shared";

const OFFER_FILTERS = [["dudosa", "Dudosas"], ["no_ti", "No TI (IA o manual)"], ["ti", "TI (IA o manual)"]] as const;
const TREND_FILTERS = [["dudosa", "Dudosas"], ["ruido", "Ruido"], ["relevante", "Relevantes"]] as const;

export default function ReviewPanel({ initialKind }: { initialKind: "offers" | "trends" }) {
    const [kind, setKind] = useState<"offers" | "trends">(initialKind);
    const [status, setStatus] = useState("dudosa");
    const [search, setSearch] = useState("");
    const [page, setPage] = useState(1);
    const [data, setData] = useState<any>(null);
    const [open, setOpen] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const load = async (p = page) => {
        setLoading(true);
        try {
            const url = kind === "offers" ? "/ai-classification/review/offers" : "/ai-classification/review/trends";
            setData((await axios.get(url, { params: { status, search: search || undefined, page: p } })).data);
        } finally { setLoading(false); }
    };
    useEffect(() => { setPage(1); load(1); }, [kind, status]);

    const fail = (e: any) => Swal.fire({ icon: "error", title: "Error", text: e.response?.data?.message ?? e.message });
    const done = (msg: string) => { Swal.fire({ icon: "success", title: msg, timer: 1500, showConfirmButton: false }); load(); };

    const decideGroup = async (g: any, to: "ti" | "no_ti") => {
        const text = g.generic
            ? `Título genérico: se marcan solo las ${fmt(g.offers)} ofertas de este grupo.`
            : `Se marcan todas las ofertas con este título (salvo las excluidas por keyword) y las ofertas futuras con este título lo heredan.`;
        const ok = await Swal.fire({ icon: "question", title: `"${g.title}" → ${to === "ti" ? "TI" : "No TI"}`, text, showCancelButton: true, confirmButtonText: "Confirmar", cancelButtonText: "Cancelar" });
        if (!ok.isConfirmed) return;
        try { done((await axios.post("/ai-classification/review/offers/group", { title_key: g.title_key, from: status, to })).data.message); } catch (e) { fail(e); }
    };
    const decideOffer = async (id: number, to: "ti" | "no_ti") => {
        try { done((await axios.post(`/ai-classification/review/offers/${id}`, { to })).data.message); } catch (e) { fail(e); }
    };
    const decideTrend = async (id: number, to: "relevante" | "ruido") => {
        try { done((await axios.post(`/ai-classification/review/trends/${id}`, { to })).data.message); } catch (e) { fail(e); }
    };

    const filters = kind === "offers" ? OFFER_FILTERS : TREND_FILTERS;
    const pages = data ? Math.max(1, Math.ceil(data.total / data.per)) : 1;
    const btn = "inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs";

    return (
        <div className="space-y-3 text-sm">
            <div className="flex flex-wrap gap-2 items-center">
                {(["offers", "trends"] as const).map((k) => (
                    <button key={k} onClick={() => { setKind(k); setStatus("dudosa"); }} className={`rounded border px-3 py-1 ${kind === k ? "bg-primary text-primary-foreground" : ""}`}>
                        {k === "offers" ? "Ofertas" : "Tendencias"}
                    </button>
                ))}
                <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded border px-2 py-1 bg-background">
                    {filters.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
                <input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === "Enter" && (setPage(1), load(1))}
                    placeholder={kind === "offers" ? "Buscar título" : "Buscar título o entidad"} className="rounded border px-2 py-1 bg-background" />
                <button onClick={() => { setPage(1); load(1); }} className="rounded border px-2 py-1">Buscar</button>
                {data && <span className="text-muted-foreground">{fmt(data.total)} {kind === "offers" ? "títulos" : "reportes"}{loading ? " · cargando…" : ""}</span>}
            </div>

            {kind === "offers" && data?.groups && (
                <div className="space-y-2">
                    {data.groups.map((g: any) => (
                        <div key={g.title_key} className="rounded border p-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <button onClick={() => setOpen(open === g.title_key ? null : g.title_key)} className="font-medium text-left hover:underline">{g.title}</button>
                                <span className="text-muted-foreground">{fmt(g.offers)} ofertas{g.manual ? ` · ${fmt(g.manual)} manuales` : ""}{g.generic ? " · título genérico" : ""}</span>
                                {g.title_decision && <span className="text-xs text-muted-foreground">IA (título): {g.title_decision.status}{g.title_decision.depends ? ", depende de la descripción" : ""} — {g.title_decision.reason}</span>}
                                <span className="ml-auto flex gap-1">
                                    {status !== "ti" && <button onClick={() => decideGroup(g, "ti")} className={btn}><Check className="h-3 w-3" />TI</button>}
                                    {status !== "no_ti" && <button onClick={() => decideGroup(g, "no_ti")} className={btn}><X className="h-3 w-3" />No TI</button>}
                                </span>
                            </div>
                            {open === g.title_key && (
                                <table className="w-full mt-2 text-xs">
                                    <tbody>
                                        {g.examples.map((e: any) => (
                                            <tr key={e.id} className="border-t align-top">
                                                <td className="py-1 pr-2 w-1/3">
                                                    <div>{e.title}</div>
                                                    <div className="text-muted-foreground">{e.company} · {e.source} · {e.published_at?.slice(0, 10)} · #{e.id}
                                                        {e.url && <a href={e.url} target="_blank" rel="noreferrer" className="ml-1 inline-flex"><ExternalLink className="h-3 w-3" /></a>}</div>
                                                    <div className="text-muted-foreground">{e.ti_origin === "manual" ? "manual" : `IA: ${e.reason ?? ""}`}</div>
                                                </td>
                                                <td className="pr-2 text-muted-foreground">{e.d || "(sin descripción)"}</td>
                                                <td className="whitespace-nowrap">
                                                    <button onClick={() => decideOffer(e.id, "ti")} className={btn}>TI</button>{" "}
                                                    <button onClick={() => decideOffer(e.id, "no_ti")} className={btn}>No TI</button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {kind === "trends" && data?.rows && (
                <table className="w-full">
                    <thead><tr className="text-left text-muted-foreground"><th>Entidad</th><th>Reporte</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>
                        {data.rows.map((r: any) => (
                            <tr key={r.id} className="border-t align-top">
                                <td className="py-1 pr-2">{r.entity}<div className="text-xs text-muted-foreground">{r.entity_type} · {r.year}-T{r.quarter}</div></td>
                                <td className="pr-2">
                                    <a href={r.source_url} target="_blank" rel="noreferrer" className="hover:underline inline-flex gap-1 items-center">{r.source_title || r.trend_name}<ExternalLink className="h-3 w-3" /></a>
                                    <div className="text-xs text-muted-foreground break-all">{r.source_url}</div>
                                    {r.source_snippet && <div className="text-xs">{r.source_snippet.slice(0, 300)}</div>}
                                </td>
                                <td className="pr-2 text-xs">{r.relevance_origin}{r.category ? ` · ${r.category}` : ""} — {r.reason}</td>
                                <td className="whitespace-nowrap">
                                    {status !== "relevante" && <button onClick={() => decideTrend(r.id, "relevante")} className={btn}><Check className="h-3 w-3" />Relevante</button>}{" "}
                                    {status !== "ruido" && <button onClick={() => decideTrend(r.id, "ruido")} className={btn}><X className="h-3 w-3" />Ruido</button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            {data && pages > 1 && (
                <div className="flex gap-2 items-center">
                    <button disabled={page <= 1} onClick={() => { setPage(page - 1); load(page - 1); }} className="rounded border px-2 py-1 disabled:opacity-50">Anterior</button>
                    <span>Página {page} de {fmt(pages)}</span>
                    <button disabled={page >= pages} onClick={() => { setPage(page + 1); load(page + 1); }} className="rounded border px-2 py-1 disabled:opacity-50">Siguiente</button>
                </div>
            )}
        </div>
    );
}
