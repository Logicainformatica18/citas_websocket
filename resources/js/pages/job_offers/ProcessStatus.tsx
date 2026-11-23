import { useEffect, useRef, useState } from "react";
import axios from "axios";
import { CheckCircle2, Loader2, XCircle, Clock } from "lucide-react";

/** Proceso en cola del módulo de ofertas (job_offer_processes). */
export type JobOfferProcess = {
    id: number;
    type: string;
    label: string;
    status: "queued" | "running" | "done" | "failed";
    user_name: string | null;
    params: any;
    total: number;
    processed: number;
    percent: number;
    result: any;
    message: string | null;
    created_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    updated_at: string | null;
};

const rankingsRunning = (p: JobOfferProcess | null) =>
    !!p?.result?.rankings && ["queued", "running", "waiting"].includes(p.result.rankings.status);

export const isActive = (p: JobOfferProcess | null) =>
    !!p && (p.status === "queued" || p.status === "running" || rankingsRunning(p));

/**
 * Sondea el estado de un proceso cada 2 s mientras corre (y cada 5 s mientras se recalculan los
 * rankings que lanzó). onFinish se llama una vez cuando el proceso termina.
 */
export function useProcess(initial: JobOfferProcess | null, onFinish?: (p: JobOfferProcess, summary?: any) => void) {
    const [process, setProcess] = useState<JobOfferProcess | null>(initial);
    const notified = useRef<number | null>(initial && !isActive(initial) ? initial.id : null);

    useEffect(() => setProcess(initial), [initial?.id]);

    useEffect(() => {
        if (!process || !isActive(process)) return;
        const wait = process.status === "done" ? 5000 : 2000;
        const t = setTimeout(async () => {
            try {
                const { data } = await axios.get(`/job-offers/processes/${process.id}`);
                setProcess(data.process);
                if (data.process.status !== "queued" && data.process.status !== "running" && notified.current !== data.process.id) {
                    notified.current = data.process.id;
                    onFinish?.(data.process, data.summary);
                }
            } catch {
                /* se reintenta en el siguiente ciclo */
            }
        }, wait);
        return () => clearTimeout(t);
    }, [process]);

    return [process, setProcess] as const;
}

const fmt = (n: number | undefined | null) => (n ?? 0).toLocaleString("es-PE");

/** Barra de progreso + estado (y el recálculo de rankings encadenado, si lo hay). */
export function ProcessBar({ process }: { process: JobOfferProcess | null }) {
    if (!process) return null;
    const r = process.result?.rankings;
    const icon =
        process.status === "done" ? <CheckCircle2 className="w-4 h-4 text-green-600" /> :
        process.status === "failed" ? <XCircle className="w-4 h-4 text-red-600" /> :
        process.status === "queued" ? <Clock className="w-4 h-4 text-gray-500" /> :
        <Loader2 className="w-4 h-4 animate-spin text-[#1CBCE8]" />;
    const statusLabel = { queued: "En cola", running: "En proceso", done: "Terminado", failed: "Falló" }[process.status];

    return (
        <div className="rounded-lg border dark:border-gray-700 p-3 bg-white dark:bg-gray-800 text-sm space-y-2">
            <div className="flex items-center gap-2 flex-wrap">
                {icon}
                <span className="font-semibold">{process.label} #{process.id}</span>
                <span className="text-gray-500">· {statusLabel}</span>
                {process.total > 0 && (
                    <span className="text-gray-500">· {fmt(process.processed)} / {fmt(process.total)}</span>
                )}
                <span className="text-gray-400 ml-auto text-xs">
                    {process.user_name ?? "—"} · {process.created_at}
                </span>
            </div>
            {(process.status === "running" || process.status === "queued") && (
                <div className="h-2 bg-gray-200 dark:bg-gray-700 rounded">
                    <div className="h-2 bg-[#1CBCE8] rounded transition-all" style={{ width: `${process.percent}%` }} />
                </div>
            )}
            {process.status === "failed" && <div className="text-red-600">{process.message}</div>}
            {r && (
                <div className="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-2">
                    {rankingsRunning(process) ? <Loader2 className="w-3 h-3 animate-spin" /> : <CheckCircle2 className="w-3 h-3 text-green-600" />}
                    Recálculo de rankings (por indicador y semestre): {r.done ?? 0} / {r.total}
                    {r.current ? ` · ${r.current}` : ""}
                    {r.status === "waiting" ? " · esperando a otro recálculo" : ""}
                </div>
            )}
        </div>
    );
}
