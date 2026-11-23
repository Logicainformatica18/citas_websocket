/** Resultado de /job-offers/title-exclusions/preview (o /{id}/examples): coincidencias y ejemplos. */
export type Preview = {
    matches: number;
    titles: number;
    affected: number;
    already_excluded: number;
    with_ti_word: number;
    examples: { title: string; offers: number; ti: boolean; excluded_by: string | null }[];
    random: { title: string; offers: number; ti: boolean; excluded_by: string | null }[];
    ti_examples: { title: string; offers: number; ti: boolean; excluded_by: string | null }[];
};

const fmt = (n: number | undefined | null) => (n ?? 0).toLocaleString("es-PE");

function ExampleList({ title, items, hint }: { title: string; items: Preview["examples"]; hint?: string }) {
    if (!items?.length) return null;
    return (
        <div>
            <div className="font-semibold text-sm mt-3 mb-1">
                {title} {hint && <span className="font-normal text-xs text-gray-500">{hint}</span>}
            </div>
            <ul className="text-sm divide-y dark:divide-gray-700 border rounded dark:border-gray-700 max-h-64 overflow-y-auto">
                {items.map((e, i) => (
                    <li key={i} className="px-2 py-1 flex gap-2 items-center">
                        <span className="text-gray-500 w-12 text-right shrink-0">{fmt(e.offers)}</span>
                        <span className="flex-1">{e.title}</span>
                        {e.ti && <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">palabra TI</span>}
                        {e.excluded_by && <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700" title="Patrón activo que hoy la excluye">hoy: {e.excluded_by}</span>}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function PreviewView({ preview, type }: { preview: Preview; type: "exclude" | "exception" }) {
    const risk = preview.matches > 0 ? Math.round((preview.with_ti_word * 100) / preview.matches) : 0;
    return (
        <div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                <Stat label="Ofertas que coinciden" value={fmt(preview.matches)} sub={`${fmt(preview.titles)} títulos distintos`} />
                <Stat
                    label={type === "exception" ? "Ofertas que rescataría" : "Ofertas que pasaría a excluir"}
                    value={fmt(preview.affected)}
                    sub="frente a la lista activa"
                />
                <Stat label="Ya excluidas por otro patrón" value={fmt(preview.already_excluded)} />
                <Stat
                    label="Con palabra de TI (riesgo)"
                    value={`${fmt(preview.with_ti_word)} (${risk} %)`}
                    sub="posibles falsos positivos"
                    warn={type === "exclude" && risk >= 10}
                />
            </div>
            <ExampleList title="Títulos más frecuentes" items={preview.examples} />
            <ExampleList title="Con palabra de TI" hint="revisa estos antes de activar una exclusión" items={preview.ti_examples} />
            <ExampleList title="Muestra al azar (menos frecuentes)" items={preview.random} />
        </div>
    );
}

function Stat({ label, value, sub, warn }: { label: string; value: string; sub?: string; warn?: boolean }) {
    return (
        <div className={`rounded border p-2 dark:border-gray-700 ${warn ? "border-amber-400 bg-amber-50 dark:bg-amber-900/20" : ""}`}>
            <div className="text-xs text-gray-500">{label}</div>
            <div className="text-lg font-semibold">{value}</div>
            {sub && <div className="text-[11px] text-gray-400">{sub}</div>}
        </div>
    );
}
