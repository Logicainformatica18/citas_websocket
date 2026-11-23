import { Fragment, useState } from "react";
import { Sparkles, Check, X } from "lucide-react";
import type { JobOfferProcess } from "../ProcessStatus";

const fmt = (n: number | undefined | null) => (n ?? 0).toLocaleString("es-PE");

type Props = {
    process: JobOfferProcess | null;
    busy: boolean;
    onSuggest: () => void;
    onDecide: (processId: number, keys: string[], approve: boolean) => void;
};

/** "Sugerir con IA": sugerencias con su impacto; se aprueban una por una o todas. */
export default function SuggestionsPanel({ process, busy, onSuggest, onDecide }: Props) {
    const [selected, setSelected] = useState<string[]>([]);
    const [open, setOpen] = useState<string | null>(null);
    const r = process?.status === "done" ? process.result : null;
    const list: any[] = r?.suggestions ?? [];
    const pending = list.filter((s) => s.status === "pending");

    const toggle = (k: string) => setSelected((s) => (s.includes(k) ? s.filter((x) => x !== k) : [...s, k]));
    const decide = (keys: string[], approve: boolean) => {
        if (!keys.length) return;
        onDecide(process!.id, keys, approve);
        setSelected([]);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3 bg-white dark:bg-gray-800 p-4 rounded-xl border dark:border-gray-700">
                <button disabled={busy} onClick={onSuggest}
                    className="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white flex items-center gap-2 disabled:opacity-50">
                    <Sparkles className="w-4 h-4" /> Sugerir con IA
                </button>
                <span className="text-xs text-gray-500">
                    Envía la lista actual y una muestra de títulos frecuentes que hoy no se excluyen (y los excluidos con palabra de TI, para proponer
                    excepciones). Nada se agrega sin tu aprobación.
                </span>
            </div>

            {!r && <div className="text-sm text-gray-500">{process?.status === "failed" ? `La última consulta falló: ${process.message}` : "Aún no hay sugerencias."}</div>}

            {r && (
                <>
                    <div className="text-sm text-gray-600 dark:text-gray-300">
                        Consulta #{process!.id} ({process!.finished_at}, {r.model}): {fmt(r.titles_sent)} títulos enviados, {r.received} sugerencias recibidas,
                        {" "}{list.length} válidas y nuevas. Pendientes: {pending.length}.
                    </div>
                    <div className="flex gap-2">
                        <button disabled={!selected.length} onClick={() => decide(selected, true)} className="px-3 py-1.5 rounded bg-green-600 text-white text-sm disabled:opacity-40">
                            Aprobar seleccionadas ({selected.length})
                        </button>
                        <button disabled={!pending.length} onClick={() => decide(pending.map((s) => s.key), true)} className="px-3 py-1.5 rounded bg-green-700 text-white text-sm disabled:opacity-40">
                            Aprobar todas ({pending.length})
                        </button>
                        <button disabled={!selected.length} onClick={() => decide(selected, false)} className="px-3 py-1.5 rounded bg-gray-300 dark:bg-gray-700 text-sm disabled:opacity-40">
                            Descartar seleccionadas
                        </button>
                    </div>
                    <div className="overflow-x-auto border rounded-xl dark:border-gray-700 bg-white dark:bg-gray-800">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-100 dark:bg-gray-700 text-xs uppercase">
                                <tr><th></th><th className="text-left px-2">Patrón</th><th>Tipo</th><th className="text-left">Categoría</th>
                                    <th>Afectaría</th><th>Coinciden</th><th>Palabra TI</th><th className="text-left">Motivo</th><th>Estado</th><th></th></tr>
                            </thead>
                            <tbody>
                                {list.map((s) => (
                                    <Fragment key={s.key}>
                                        <tr className="border-t dark:border-gray-700">
                                            <td className="px-2"><input type="checkbox" disabled={s.status !== "pending"} checked={selected.includes(s.key)} onChange={() => toggle(s.key)} /></td>
                                            <td className="px-2 font-mono">{s.pattern}<span className="text-xs text-gray-500"> {s.match_mode === "regex" ? "regex" : "texto"}{s.whole_word ? " · palabra" : ""}</span></td>
                                            <td className="text-center">{s.type === "exception" ? "excepción" : "excluir"}</td>
                                            <td>{s.category}</td>
                                            <td className="text-center font-semibold" title={s.type === "exception" ? "ofertas que rescataría" : "ofertas que pasaría a excluir"}>{fmt(s.affected)}</td>
                                            <td className="text-center">{fmt(s.matches)}</td>
                                            <td className={`text-center ${s.with_ti_word > 0 && s.type === "exclude" ? "text-amber-600 font-semibold" : ""}`}>{fmt(s.with_ti_word)}</td>
                                            <td className="text-xs">{s.reason}</td>
                                            <td className="text-center text-xs">{{ pending: "pendiente", approved: "aprobada", rejected: "descartada", skipped: "omitida" }[s.status as string]}</td>
                                            <td className="px-2 whitespace-nowrap">
                                                <button className="text-xs text-[#1CBCE8] hover:underline mr-2" onClick={() => setOpen(open === s.key ? null : s.key)}>ejemplos</button>
                                                {s.status === "pending" && (
                                                    <>
                                                        <button title="Aprobar" onClick={() => decide([s.key], true)}><Check className="w-4 h-4 inline text-green-600" /></button>
                                                        <button title="Descartar" onClick={() => decide([s.key], false)}><X className="w-4 h-4 inline text-gray-500" /></button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                        {open === s.key && (
                                            <tr><td></td><td colSpan={9} className="text-xs pb-2">
                                                {s.examples.length ? s.examples.map((e: any, i: number) => (
                                                    <div key={i}>{fmt(e.offers)} · {e.title}{e.ti ? " ⚠ palabra TI" : ""}</div>
                                                )) : "Sin coincidencias en las ofertas actuales."}
                                            </td></tr>
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </div>
    );
}
