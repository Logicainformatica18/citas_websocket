import axios from "axios";
import Swal from "sweetalert2";
import { useState } from "react";
import { KIND_LABEL, fmt, type Prompt, type PromptVersion } from "./shared";

type Diff = { version: number; previous: number | null; diff: [string, string][] };

export default function PromptsPanel({ prompts, models, onChange }: { prompts: Prompt[]; models: string[]; onChange: (p: Prompt[]) => void }) {
    const [kind, setKind] = useState<"offers" | "trends">("offers");
    const prompt = prompts.find((p) => p.kind === kind)!;
    const active = prompt.versions.find((v) => v.id === prompt.active_version_id);
    const [selected, setSelected] = useState<PromptVersion | null>(null);
    const [draft, setDraft] = useState<{ body: string; model: string; note: string } | null>(null);
    const [diff, setDiff] = useState<Diff | null>(null);
    const view = selected ?? active ?? null;

    const fail = (e: any) => Swal.fire({ icon: "error", title: "Error", text: e.response?.data?.message ?? e.message });

    const save = async () => {
        if (!draft) return;
        try {
            const { data } = await axios.post("/ai-classification/prompts", { kind, ...draft });
            onChange(data.prompts);
            setDraft(null);
            Swal.fire({ icon: "success", title: data.message });
        } catch (e) { fail(e); }
    };

    const activate = async (v: PromptVersion) => {
        const back = v.version < (active?.version ?? 0);
        const ok = await Swal.fire({
            icon: "question", title: back ? `Volver a la versión ${v.version}` : `Activar la versión ${v.version}`,
            text: "Las próximas corridas clasificarán lo nuevo con esta versión. Lo ya clasificado no cambia (para reprocesarlo: ai:classify --reclassify-version=N).",
            showCancelButton: true, confirmButtonText: "Activar", cancelButtonText: "Cancelar",
        });
        if (!ok.isConfirmed) return;
        try {
            const { data } = await axios.post(`/ai-classification/prompts/${v.id}/activate`);
            onChange(data.prompts);
            Swal.fire({ icon: "success", title: data.message, timer: 2500, showConfirmButton: false });
        } catch (e) { fail(e); }
    };

    const showDiff = async (v: PromptVersion) => {
        try { setDiff((await axios.get(`/ai-classification/prompts/${v.id}/diff`)).data); } catch (e) { fail(e); }
    };

    return (
        <div className="space-y-3">
            <div className="flex gap-2">
                {(["offers", "trends"] as const).map((k) => (
                    <button key={k} onClick={() => { setKind(k); setSelected(null); setDraft(null); setDiff(null); }}
                        className={`rounded border px-3 py-1 text-sm ${kind === k ? "bg-primary text-primary-foreground" : ""}`}>{KIND_LABEL[k]}</button>
                ))}
                <button onClick={() => setDraft({ body: view?.body ?? "", model: view?.model ?? models[0], note: "" })}
                    className="ml-auto rounded border px-3 py-1 text-sm">Nueva versión (borrador)</button>
            </div>

            <div className="grid gap-4 lg:grid-cols-[22rem_1fr]">
                <div className="rounded border text-sm">
                    <table className="w-full">
                        <thead><tr className="text-left text-muted-foreground"><th className="p-2">Versión</th><th>Estado</th><th className="text-right p-2">Decisiones</th></tr></thead>
                        <tbody>
                            {prompt.versions.map((v) => (
                                <tr key={v.id} onClick={() => { setSelected(v); setDiff(null); }}
                                    className={`border-t cursor-pointer ${view?.id === v.id ? "bg-muted" : ""}`}>
                                    <td className="p-2">
                                        <div className="font-medium">v{v.version} · {v.model}</div>
                                        <div className="text-xs text-muted-foreground">{v.created_by} · {v.created_at}</div>
                                        {v.note && <div className="text-xs">{v.note}</div>}
                                    </td>
                                    <td className={v.status === "activa" ? "text-green-700 dark:text-green-300 font-medium" : ""}>{v.status}</td>
                                    <td className="text-right p-2">{fmt(v.decisions)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="space-y-2">
                    {draft ? (
                        <div className="space-y-2">
                            <div className="flex flex-wrap gap-2 items-center text-sm">
                                <span className="font-medium">Borrador nuevo de {KIND_LABEL[kind]}</span>
                                <select value={draft.model} onChange={(e) => setDraft({ ...draft, model: e.target.value })} className="rounded border px-2 py-1 bg-background">
                                    {models.map((m) => <option key={m}>{m}</option>)}
                                </select>
                                <input value={draft.note} onChange={(e) => setDraft({ ...draft, note: e.target.value })} placeholder="Nota: qué cambia y por qué"
                                    className="flex-1 min-w-60 rounded border px-2 py-1 bg-background" />
                            </div>
                            <textarea value={draft.body} onChange={(e) => setDraft({ ...draft, body: e.target.value })} rows={28}
                                className="w-full rounded border p-2 font-mono text-xs bg-background" />
                            <div className="flex gap-2">
                                <button onClick={save} className="rounded bg-primary text-primary-foreground px-3 py-1 text-sm">Guardar borrador</button>
                                <button onClick={() => setDraft(null)} className="rounded border px-3 py-1 text-sm">Cancelar</button>
                                <span className="text-xs text-muted-foreground self-center">El borrador no se usa hasta activarlo. Pruébalo antes en "Probar versión".</span>
                            </div>
                        </div>
                    ) : view && (
                        <div className="space-y-2">
                            <div className="flex flex-wrap gap-2 items-center text-sm">
                                <span className="font-medium">v{view.version} ({view.status})</span>
                                {view.activated_at && <span className="text-muted-foreground">activada por {view.activated_by} el {view.activated_at}</span>}
                                <button onClick={() => showDiff(view)} className="rounded border px-2 py-1">Diferencias con la anterior</button>
                                {view.status !== "activa" && <button onClick={() => activate(view)} className="rounded border px-2 py-1">
                                    {view.version < (active?.version ?? 0) ? "Volver a esta versión" : "Activar"}</button>}
                            </div>
                            {diff ? (
                                <div className="rounded border p-2 font-mono text-xs max-h-[38rem] overflow-auto">
                                    <div className="mb-1 text-muted-foreground">v{diff.previous ?? "—"} → v{diff.version}</div>
                                    {diff.diff.map(([op, line], i) => (
                                        <div key={i} className={op === "+" ? "bg-green-100 dark:bg-green-900/40" : op === "-" ? "bg-red-100 dark:bg-red-900/40 line-through" : "text-muted-foreground"}>
                                            {op === "=" ? "  " : op + " "}{line || " "}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <pre className="rounded border p-2 text-xs whitespace-pre-wrap max-h-[38rem] overflow-auto">{view.body}</pre>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
