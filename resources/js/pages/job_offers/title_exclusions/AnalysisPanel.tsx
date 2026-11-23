import { useState } from "react";
import { ShieldOff, Trash2, RotateCcw } from "lucide-react";
import type { JobOfferProcess } from "../ProcessStatus";

const fmt = (n: number | undefined | null) => (n ?? 0).toLocaleString("es-PE");

type Props = {
    analysis: JobOfferProcess | null;
    lastDelete: JobOfferProcess | null;
    busy: boolean;
    confirmWord: string;
    onAnalyze: (year: number, period: string) => void;
    onApply: () => void;
    onDelete: (analysisId: number, confirm: string) => void;
    onRestore: (deleteId: number) => void;
};

/** "Analizar basura": qué excluiría la lista actual y cuánto cambiarían los indicadores. */
export default function AnalysisPanel({ analysis, lastDelete, busy, confirmWord, onAnalyze, onApply, onDelete, onRestore }: Props) {
    const now = new Date();
    const [year, setYear] = useState(now.getFullYear());
    const [period, setPeriod] = useState(now.getMonth() < 6 ? "s1" : "s2");
    const [showDelete, setShowDelete] = useState(false);
    const [word, setWord] = useState("");
    const r = analysis?.status === "done" ? analysis.result : null;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end gap-3 bg-white dark:bg-gray-800 p-4 rounded-xl border dark:border-gray-700">
                <div className="text-sm">
                    <div className="font-semibold">Semestre para medir el impacto en los indicadores</div>
                    <div className="flex gap-2 mt-1">
                        <select className="border rounded px-2 py-1 dark:bg-gray-900 dark:border-gray-700" value={year} onChange={(e) => setYear(+e.target.value)}>
                            {Array.from({ length: now.getFullYear() - 2023 }, (_, i) => now.getFullYear() - i).map((y) => <option key={y}>{y}</option>)}
                        </select>
                        <select className="border rounded px-2 py-1 dark:bg-gray-900 dark:border-gray-700" value={period} onChange={(e) => setPeriod(e.target.value)}>
                            <option value="s1">Ene–Jun</option>
                            <option value="s2">Jul–Dic</option>
                        </select>
                    </div>
                </div>
                <button disabled={busy} onClick={() => onAnalyze(year, period)}
                    className="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50">
                    Analizar basura
                </button>
                <span className="text-xs text-gray-500">Simulación con la lista activa; no cambia nada. Corre en cola.</span>
            </div>

            {lastDelete?.status === "done" && lastDelete.result?.restorable && (
                <div className="p-3 rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 text-sm flex items-center gap-3">
                    Eliminación #{lastDelete.id}: {fmt(lastDelete.result.offers_deleted)} ofertas borradas el {lastDelete.finished_at} por {lastDelete.user_name}
                    (respaldo de {fmt(lastDelete.result.backup_rows)} filas).
                    <button disabled={busy} onClick={() => onRestore(lastDelete.id)}
                        className="ml-auto px-3 py-1 rounded bg-white dark:bg-gray-800 border flex items-center gap-1 disabled:opacity-50">
                        <RotateCcw className="w-4 h-4" /> Restaurar desde el respaldo
                    </button>
                </div>
            )}

            {!r && <div className="text-sm text-gray-500">Aún no hay un análisis terminado.</div>}

            {r && (
                <>
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
                        <Card label="Ofertas evaluadas" value={fmt(r.total_offers)} />
                        <Card label="Excluiría la lista actual" value={fmt(r.excluded)} sub={`${((r.excluded * 100) / Math.max(1, r.total_offers)).toFixed(1)} % del total`} />
                        <Card label="Nuevas (hoy sin marcar)" value={fmt(r.newly_excluded)} sub={`ya marcadas: ${fmt(r.already_marked)}`} />
                        <Card label={`En ${r.period.label}`} value={fmt(r.period.excluded)} />
                        <Card label="Asociaciones de esas ofertas" value={fmt(r.associations_total)} sub="se borrarían al eliminar" />
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button disabled={busy} onClick={onApply}
                            className="px-4 py-2 rounded-lg bg-[#0C647A] hover:bg-[#0a5466] text-white flex items-center gap-2 disabled:opacity-50">
                            <ShieldOff className="w-4 h-4" /> Excluir de los indicadores (recomendado, reversible)
                        </button>
                        <button disabled={busy || !r.excluded} onClick={() => { setWord(""); setShowDelete(true); }}
                            className="px-4 py-2 rounded-lg border border-red-500 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2 disabled:opacity-50">
                            <Trash2 className="w-4 h-4" /> Eliminar definitivamente…
                        </button>
                        <span className="text-xs text-gray-500 self-center">Análisis #{analysis!.id} · {analysis!.finished_at} · {analysis!.user_name}</span>
                    </div>

                    <Section title={`Impacto en los indicadores (${r.period.label})`}>
                        <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
                            {Object.entries(r.impact ?? {}).map(([type, im]: any) => <Impact key={type} im={im} />)}
                        </div>
                    </Section>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <Section title="Por categoría">
                            <Table head={["Categoría", "Ofertas", "Con palabra TI", "Ejemplos"]}
                                rows={r.by_category.map((c: any) => [c.category, fmt(c.offers), fmt(c.with_ti_word), <Examples key="e" items={c.examples} />])} />
                        </Section>
                        <Section title="Por fuente">
                            <Table head={["Fuente", "Ofertas"]} rows={r.by_source.map((s: any) => [s.source, fmt(s.offers)])} />
                        </Section>
                    </div>

                    <Section title="Por patrón (atribución: el primer patrón de la lista que coincide)">
                        <Table head={["Patrón", "Categoría", "Ofertas", "Con palabra TI", "Ejemplos"]}
                            rows={r.by_keyword.map((k: any) => [
                                <code key="p">{k.pattern}{k.whole_word ? " (palabra)" : ""}</code>, k.category, fmt(k.offers),
                                <span key="t" className={k.with_ti_word > 0 ? "text-amber-600 font-semibold" : ""}>{fmt(k.with_ti_word)}</span>,
                                <Examples key="e" items={k.examples} />,
                            ])} />
                    </Section>
                </>
            )}

            {showDelete && r && (
                <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
                    <div className="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-lg border dark:border-gray-700 space-y-3 text-sm">
                        <h3 className="text-lg font-bold text-red-600">Eliminar definitivamente</h3>
                        <p>Se borrarán <b>{fmt(r.excluded)}</b> ofertas y <b>{fmt(r.associations_total)}</b> filas asociadas:</p>
                        <ul className="list-disc pl-5">
                            {r.associations.map((a: any) => <li key={a.table}><code>{a.table}</code>: {fmt(a.rows)}</li>)}
                        </ul>
                        <p className="text-gray-600 dark:text-gray-300">
                            Antes de borrar, cada fila se copia a <code>job_offer_deletion_backups</code> (se puede restaurar).
                            Corre en cola. La alternativa recomendada es "Excluir de los indicadores", que no borra nada.
                        </p>
                        <label className="block">Escribe <b>{confirmWord}</b> para confirmar:
                            <input className="mt-1 border rounded px-3 py-2 w-full dark:bg-gray-800 dark:border-gray-700" value={word} onChange={(e) => setWord(e.target.value)} />
                        </label>
                        <div className="flex gap-3 justify-end">
                            <button onClick={() => setShowDelete(false)} className="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700">Cancelar</button>
                            <button disabled={word !== confirmWord} onClick={() => { setShowDelete(false); onDelete(analysis!.id, word); }}
                                className="px-4 py-2 rounded bg-red-600 text-white disabled:opacity-40">Eliminar</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function Impact({ im }: { im: any }) {
    const pos = (p: number | null) => (p === null ? "—" : `#${p}`);
    return (
        <div className="rounded-lg border dark:border-gray-700 p-3 bg-white dark:bg-gray-800 text-xs">
            <div className="font-semibold text-sm capitalize">{im.label}</div>
            <div className="text-gray-600 dark:text-gray-300 mb-2">
                Vacantes analizadas: {fmt(im.vacantes_before)} → <b>{fmt(im.vacantes_after)}</b> ({fmt(im.vacantes_after - im.vacantes_before)}) ·
                asociaciones que dejan de contar: {fmt(im.associations_removed)}
            </div>
            <table className="w-full">
                <thead><tr className="text-gray-500"><th className="text-left">Top 10 antes</th><th>vac.</th><th className="text-left pl-2">Top 10 después</th><th>vac.</th><th>antes</th></tr></thead>
                <tbody>
                    {im.top_before.map((b: any, i: number) => {
                        const a = im.top_after[i];
                        return (
                            <tr key={i} className="border-t dark:border-gray-700">
                                <td>{b.position}. {b.name}</td><td className="text-right">{fmt(b.jobs)}</td>
                                <td className="pl-2">{a ? `${a.position}. ${a.name}` : ""}</td><td className="text-right">{a ? fmt(a.jobs) : ""}</td>
                                <td className="text-center text-gray-500">{a ? pos(a.other_position) : ""}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
            {im.biggest_drops?.length > 0 && (
                <div className="mt-2">
                    <div className="font-semibold">Mayores bajas</div>
                    {im.biggest_drops.map((d: any) => (
                        <div key={d.id}>{d.name}: {fmt(d.before)} → {fmt(d.after)} ({fmt(d.diff)}) · posición {pos(d.position_before)} → {pos(d.position_after)}</div>
                    ))}
                </div>
            )}
        </div>
    );
}

function Card({ label, value, sub }: { label: string; value: string; sub?: string }) {
    return (
        <div className="rounded-lg border dark:border-gray-700 p-3 bg-white dark:bg-gray-800">
            <div className="text-xs text-gray-500">{label}</div>
            <div className="text-xl font-semibold">{value}</div>
            {sub && <div className="text-[11px] text-gray-400">{sub}</div>}
        </div>
    );
}

function Section({ title, children }: { title: string; children: any }) {
    return (
        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border dark:border-gray-700">
            <h3 className="font-semibold mb-2">{title}</h3>
            {children}
        </div>
    );
}

function Table({ head, rows }: { head: string[]; rows: any[][] }) {
    return (
        <div className="max-h-96 overflow-y-auto">
            <table className="w-full text-sm">
                <thead className="sticky top-0 bg-gray-100 dark:bg-gray-700"><tr>{head.map((h) => <th key={h} className="text-left px-2 py-1">{h}</th>)}</tr></thead>
                <tbody>{rows.map((r, i) => <tr key={i} className="border-t dark:border-gray-700 align-top">{r.map((c, j) => <td key={j} className="px-2 py-1">{c}</td>)}</tr>)}</tbody>
            </table>
        </div>
    );
}

function Examples({ items }: { items: string[] }) {
    return <div className="text-xs text-gray-500">{(items ?? []).slice(0, 4).join(" · ")}</div>;
}
