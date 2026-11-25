import axios from "axios";
import Swal from "sweetalert2";
import { RefreshCw, Power } from "lucide-react";
import { fmt, usd } from "./shared";

type Row = { s: string; o: string | null; n: number };

const OFFER_STATES = ["pendiente", "en_proceso", "ti", "no_ti", "dudosa"];
const TREND_STATES = ["pendiente", "en_proceso", "relevante", "ruido", "dudosa"];
const LABEL: Record<string, string> = {
    pendiente: "Pendiente", en_proceso: "En proceso", ti: "TI", no_ti: "No TI", dudosa: "Dudosa", relevante: "Relevante", ruido: "Ruido",
};

function StateTable({ title, rows, states, origins }: { title: string; rows: Row[]; states: string[]; origins: string[] }) {
    const get = (s: string, o: string | null) => rows.filter((r) => r.s === s && (o === null ? true : r.o === o)).reduce((a, r) => a + Number(r.n), 0);
    const total = rows.reduce((a, r) => a + Number(r.n), 0);
    return (
        <div className="rounded border p-3">
            <h3 className="font-medium mb-2">{title} <span className="text-muted-foreground text-sm">({fmt(total)})</span></h3>
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-muted-foreground">
                        <th className="py-1">Estado</th><th className="text-right">Total</th>
                        {origins.map((o) => <th key={o} className="text-right">{o}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {states.map((s) => (
                        <tr key={s} className="border-t">
                            <td className="py-1">{LABEL[s]}</td>
                            <td className="text-right font-medium">{fmt(get(s, null))}</td>
                            {origins.map((o) => <td key={o} className="text-right">{fmt(get(s, o))}</td>)}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function SummaryPanel({ summary, onChange }: { summary: any; onChange: (s: any) => void }) {
    const refresh = async () => onChange((await axios.get("/ai-classification/summary")).data);
    const toggle = async () => {
        const on = !summary.settings.enabled;
        const ok = await Swal.fire({
            icon: on ? "question" : "warning",
            title: on ? "Activar la clasificación con IA" : "Apagar la clasificación con IA",
            text: on ? "Los comandos volverán a llamar a la IA." : "Los comandos dejarán de llamar a la IA (siguen las reglas y la herencia por título, que no cuestan). Las decisiones ya tomadas no cambian.",
            showCancelButton: true, confirmButtonText: on ? "Activar" : "Apagar", cancelButtonText: "Cancelar",
        });
        if (!ok.isConfirmed) return;
        const { data } = await axios.post("/ai-classification/toggle", { enabled: on });
        onChange(data.summary);
        Swal.fire({ icon: "success", title: data.message, timer: 2000, showConfirmButton: false });
    };
    const last = summary.runs?.[0];
    const st = summary.settings;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <span className={`px-2 py-1 rounded text-sm ${st.enabled ? "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200" : "bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200"}`}>
                    IA {st.enabled ? "activa" : "APAGADA (interruptor de emergencia)"}
                </span>
                <button onClick={toggle} className="inline-flex items-center gap-1 rounded border px-2 py-1 text-sm"><Power className="h-4 w-4" />{st.enabled ? "Apagar" : "Activar"}</button>
                <button onClick={refresh} className="inline-flex items-center gap-1 rounded border px-2 py-1 text-sm"><RefreshCw className="h-4 w-4" />Actualizar</button>
                <span className="text-sm text-muted-foreground">Costo del mes: <b>{usd(summary.cost_month)}</b> · acumulado: <b>{usd(summary.cost_total)}</b></span>
                <span className="text-sm text-muted-foreground">Filtro de tendencias en los rankings: <b>{st.trends_filter_enabled ? "activo" : "apagado"}</b></span>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <StateTable title="Ofertas" rows={summary.offers} states={OFFER_STATES} origins={["keyword", "ia", "manual"]} />
                <StateTable title="Tendencias" rows={summary.trends} states={TREND_STATES} origins={["regla", "ia", "manual"]} />
            </div>

            <div className="rounded border p-3 text-sm space-y-1">
                <h3 className="font-medium">Histórico (Batch API)</h3>
                <p className="text-muted-foreground">
                    Histórico = ofertas hasta el id {fmt(st.offers_history_max_id)} y tendencias hasta el id {fmt(st.trends_history_max_id)}.
                    {st.history_submitted_at ? ` Enviado por el operador el ${st.history_submitted_at}; ai:classify-history --collect sigue enviando lo que falte.` : " Aún no se ejecutó ai:classify-history --submit."}
                    {" "}Lotes en curso: <b>{summary.batches_in_flight}</b>.
                </p>
                {summary.batches?.length > 0 && (
                    <table className="w-full">
                        <thead><tr className="text-left text-muted-foreground"><th>Lote</th><th>Tipo</th><th>Estado</th><th className="text-right">Elementos</th><th className="text-right">Costo</th><th>Enviado</th><th>Aplicado</th></tr></thead>
                        <tbody>
                            {summary.batches.map((b: any) => (
                                <tr key={b.id} className="border-t" title={b.error ?? ""}>
                                    <td>#{b.id}</td><td>{b.kind}/{b.pass}</td><td>{b.status}{b.provider_status ? ` (${b.provider_status})` : ""}</td>
                                    <td className="text-right">{fmt(b.items)}</td><td className="text-right">{usd(b.cost_usd)}</td><td>{b.submitted_at ?? "-"}</td><td>{b.applied_at ?? "-"}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <div className="rounded border p-3 text-sm">
                <h3 className="font-medium mb-2">Últimas corridas {last && <span className="text-muted-foreground font-normal">(última: {last.command} {last.finished_at ?? "en curso"} · {last.status})</span>}</h3>
                <table className="w-full">
                    <thead>
                        <tr className="text-left text-muted-foreground">
                            <th>#</th><th>Comando</th><th>Inicio</th><th>Estado</th><th className="text-right">Procesados</th><th className="text-right">Heredados</th>
                            <th className="text-right">Por regla</th><th className="text-right">Llamadas</th><th className="text-right">Tokens</th><th className="text-right">Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        {summary.runs.map((r: any) => (
                            <tr key={r.id} className="border-t align-top">
                                <td>{r.id}</td>
                                <td>{r.command} {r.kind}{r.dry_run ? " (dry-run)" : ""}</td>
                                <td>{r.started_at}</td>
                                <td className={r.status === "error" ? "text-red-600" : ""} title={r.errors ?? ""}>{r.status}{r.errors ? " ⚠" : ""}</td>
                                <td className="text-right">{fmt(r.processed)}</td><td className="text-right">{fmt(r.inherited)}</td><td className="text-right">{fmt(r.by_rule)}</td>
                                <td className="text-right">{fmt(r.calls)}</td><td className="text-right">{fmt(r.tokens_in + r.tokens_out)}</td><td className="text-right">{usd(r.cost_usd)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {last?.errors && <pre className="mt-2 whitespace-pre-wrap text-xs text-red-700 dark:text-red-300">{last.errors}</pre>}
            </div>
        </div>
    );
}
