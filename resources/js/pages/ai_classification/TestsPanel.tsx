import axios from "axios";
import Swal from "sweetalert2";
import { Fragment, useState } from "react";
import { RefreshCw } from "lucide-react";
import { KIND_LABEL, usd, type Prompt } from "./shared";

export default function TestsPanel({ prompts, tests, onChange }: { prompts: Prompt[]; tests: any[]; onChange: (t: any[]) => void }) {
    const drafts = prompts.flatMap((p) => p.versions.filter((v) => v.id !== p.active_version_id).map((v) => ({ ...v, kind: p.kind })));
    const [versionId, setVersionId] = useState<number | "">(drafts[0]?.id ?? "");
    const [size, setSize] = useState(100);
    const [open, setOpen] = useState<number | null>(null);
    const [onlyChanges, setOnlyChanges] = useState(true);

    const create = async () => {
        if (!versionId) return;
        try {
            const { data } = await axios.post("/ai-classification/tests", { version_id: versionId, sample_size: size });
            onChange(data.tests);
            Swal.fire({ icon: "info", title: "Prueba registrada", text: data.message });
        } catch (e: any) {
            Swal.fire({ icon: "error", title: "Error", text: e.response?.data?.message ?? e.message });
        }
    };
    const refresh = async () => onChange((await axios.get("/ai-classification/tests")).data);

    return (
        <div className="space-y-4 text-sm">
            <div className="rounded border p-3 space-y-2">
                <p className="text-muted-foreground">
                    Compara la versión vigente con un borrador sobre la misma muestra al azar (50 a 200 elementos). La prueba queda pendiente y la
                    ejecuta la siguiente corrida de <code>ai:classify</code> (en <code>jobs:run-all</code>) o, a mano, <code>php artisan ai:prompt-test</code>.
                    No cambia ninguna clasificación.
                </p>
                <div className="flex flex-wrap gap-2 items-center">
                    <select value={versionId} onChange={(e) => setVersionId(Number(e.target.value))} className="rounded border px-2 py-1 bg-background">
                        {drafts.length === 0 && <option value="">No hay borradores: créalo en "Prompts y versiones"</option>}
                        {drafts.map((v) => <option key={v.id} value={v.id}>{KIND_LABEL[v.kind]} v{v.version} ({v.status}) {v.note ?? ""}</option>)}
                    </select>
                    <input type="number" min={50} max={200} value={size} onChange={(e) => setSize(Number(e.target.value))} className="w-24 rounded border px-2 py-1 bg-background" />
                    <button onClick={create} disabled={!versionId} className="rounded bg-primary text-primary-foreground px-3 py-1 disabled:opacity-50">Registrar prueba</button>
                    <button onClick={refresh} className="inline-flex items-center gap-1 rounded border px-2 py-1"><RefreshCw className="h-4 w-4" />Actualizar</button>
                </div>
            </div>

            <table className="w-full">
                <thead><tr className="text-left text-muted-foreground"><th>#</th><th>Tipo</th><th>Vigente → borrador</th><th>Muestra</th><th>Estado</th><th className="text-right">Concordancia</th><th className="text-right">Costo</th><th>Registrada</th><th></th></tr></thead>
                <tbody>
                    {tests.map((t) => (
                        <Fragment key={t.id}>
                            <tr className="border-t">
                                <td>{t.id}</td><td>{KIND_LABEL[t.kind as "offers"]}</td><td>v{t.base_version} → v{t.draft_version}</td><td>{t.sample_size}</td>
                                <td title={t.error ?? ""}>{t.status}</td>
                                <td className="text-right">{t.agreement !== null ? `${t.agreement} %` : "-"}</td><td className="text-right">{usd(t.cost_usd)}</td>
                                <td>{t.created_by} · {t.created_at}</td>
                                <td>{t.result && <button onClick={() => setOpen(open === t.id ? null : t.id)} className="underline">{open === t.id ? "Ocultar" : "Ver cambios"}</button>}</td>
                            </tr>
                            {open === t.id && t.result && (
                                <tr>
                                    <td colSpan={9} className="p-2 bg-muted/40">
                                        <label className="flex gap-1 items-center mb-2"><input type="checkbox" checked={onlyChanges} onChange={(e) => setOnlyChanges(e.target.checked)} />Solo los que cambian ({t.result.compared - t.result.same} de {t.result.compared})</label>
                                        <table className="w-full text-xs">
                                            <thead><tr className="text-left text-muted-foreground"><th>Elemento</th><th>Vigente</th><th>Borrador</th></tr></thead>
                                            <tbody>
                                                {t.result.items.filter((i: any) => !onlyChanges || i.base?.c !== i.draft?.c).map((i: any) => (
                                                    <tr key={i.id} className="border-t align-top">
                                                        <td className="py-1 pr-2">
                                                            <div className="font-medium">{i.title}</div>
                                                            <div className="text-muted-foreground">{i.company ?? i.url} {i.source ?? ""} · actual: {i.current}{i.origin ? ` (${i.origin})` : ""}</div>
                                                        </td>
                                                        <td className="pr-2"><b>{i.base?.c ?? "-"}</b> {i.base?.cat} — {i.base?.m}</td>
                                                        <td><b>{i.draft?.c ?? "-"}</b> {i.draft?.cat} — {i.draft?.m}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            )}
                        </Fragment>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
