import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState } from "react";
import SummaryPanel from "./SummaryPanel";
import PromptsPanel from "./PromptsPanel";
import TestsPanel from "./TestsPanel";
import ReviewPanel from "./ReviewPanel";

const breadcrumbs: BreadcrumbItem[] = [{ title: "Clasificación con IA", href: "/ai-classification" }];

import { type Prompt } from "./shared";

export default function AiClassificationIndex() {
    const props = usePage().props as any;
    const params = new URLSearchParams(typeof window !== "undefined" ? window.location.search : "");
    const [tab, setTab] = useState<string>(params.get("tab") ?? "summary");
    const [summary, setSummary] = useState(props.summary);
    const [prompts, setPrompts] = useState<Prompt[]>(props.prompts);
    const [tests, setTests] = useState<any[]>(props.tests);

    const tabs = [
        ["summary", "Resumen"],
        ["prompts", "Prompts y versiones"],
        ["tests", "Probar versión"],
        ["review", "Revisión manual"],
    ] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-4 space-y-4">
                <div>
                    <h1 className="text-xl font-semibold">Clasificación con IA</h1>
                    <p className="text-sm text-muted-foreground">
                        Ofertas TI / no TI y relevancia de los reportes de tendencias. Los indicadores ignoran las ofertas <b>no TI</b>;
                        las dudosas y pendientes cuentan hasta que se decidan. La clasificación corre en consola
                        (<code>ai:classify</code> y <code>ai:classify-history --collect</code> dentro de <code>jobs:run-all</code>).
                    </p>
                </div>
                <div className="flex gap-1 border-b">
                    {tabs.map(([k, label]) => (
                        <button key={k} onClick={() => setTab(k)}
                            className={`px-3 py-2 text-sm -mb-px border-b-2 ${tab === k ? "border-primary font-medium" : "border-transparent text-muted-foreground"}`}>
                            {label}
                        </button>
                    ))}
                </div>
                {tab === "summary" && <SummaryPanel summary={summary} onChange={setSummary} />}
                {tab === "prompts" && <PromptsPanel prompts={prompts} models={props.models} onChange={setPrompts} />}
                {tab === "tests" && <TestsPanel prompts={prompts} tests={tests} onChange={setTests} />}
                {tab === "review" && <ReviewPanel initialKind={(params.get("kind") as any) ?? "offers"} />}
            </div>
        </AppLayout>
    );
}
