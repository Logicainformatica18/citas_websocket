import React, { useState } from "react";
import { Link } from "@inertiajs/react";

export default function Show({ pdf }) {
    const [tab, setTab] = useState("summary");

    return (
        <div className="p-6">
            {/* HEADER */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold">{pdf.title}</h1>

                <div className="text-sm text-gray-500 mt-1">
                    Año: {pdf.year ?? "No especificado"}
                </div>

                <div className="mt-2">
                    {pdf.processed ? (
                        <span className="px-2 py-1 text-xs bg-green-600 text-white rounded">
                            Procesado ✓
                        </span>
                    ) : (
                        <span className="px-2 py-1 text-xs bg-yellow-600 text-white rounded">
                            Procesando…
                        </span>
                    )}
                </div>

                <div className="mt-4">
                    <Link
                        href="/pdf"
                        className="text-blue-600 hover:underline text-sm"
                    >
                        ← Volver
                    </Link>
                </div>
            </div>

            {/* TABS */}
            <div className="border-b mb-6">
                <nav className="flex gap-6">
                    {[
                        ["summary", "Resumen"],
                        ["pages", "Páginas"],
                        ["graphs", "Gráficos"],
                        ["tables", "Tablas"],
                    ].map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => setTab(key)}
                            className={`pb-2 ${
                                tab === key
                                    ? "border-b-2 border-blue-500 font-semibold"
                                    : "text-gray-500"
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* CONTENT */}
            {tab === "summary" && (
                <SummaryTab summary={pdf.summary} />
            )}

            {tab === "pages" && (
                <PagesTab pages={pdf.pages} />
            )}

            {tab === "graphs" && (
                <GraphsTab pages={pdf.pages} />
            )}

            {tab === "tables" && (
                <TablesTab pages={pdf.pages} />
            )}
        </div>
    );
}

/* -------------------------------
 * 1️⃣ TAB: RESUMEN GLOBAL
 * ------------------------------- */
function SummaryTab({ summary }) {
    if (!summary) {
        return <div>No se generó resumen aún.</div>;
    }

    return (
        <div className="space-y-4">
            <Section title="Resumen corto" text={summary.summary_short} />
            <Section title="Resumen mediano" text={summary.summary_medium} />
            <Section title="Resumen largo" text={summary.summary_long} />

            <Section title="Insights" text={
                summary.insights_json?.join("\n- ") || "No insights"
            } />

            <Section title="Temas principales" text={
                summary.topics_json?.join("\n- ") || "No temas"
            } />
        </div>
    );
}

/* -------------------------------
 * 2️⃣ TAB: PÁGINAS COMPLETAS
 * ------------------------------- */
function PagesTab({ pages }) {
    return (
        <div className="space-y-4">
            {pages.map((p) => (
                <div
                    key={p.id}
                    className="p-4 border rounded bg-white shadow-sm"
                >
                    <h3 className="font-semibold mb-2">
                        Página {p.page_number}
                    </h3>

                    <pre className="text-sm text-gray-700 whitespace-pre-wrap">
                        {p.text_content?.substring(0, 500) || "(vacío)"}
                    </pre>

                    {p.text_content?.length > 500 && (
                        <div className="text-xs text-gray-400 mt-2">
                            (Mostrando primeras 500 letras)
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

/* -------------------------------
 * 3️⃣ TAB: GRÁFICOS DETECTADOS
 * ------------------------------- */
function GraphsTab({ pages }) {
    const graphs = pages.flatMap((p) =>
        (p.graphs ?? []).map((g) => ({ page: p.page_number, ...g }))
    );

    if (graphs.length === 0) return <div>No hay gráficos detectados.</div>;

    return (
        <div className="space-y-6">
            {graphs.map((g, idx) => (
                <div key={idx} className="p-4 border rounded bg-white shadow">
                    <h3 className="font-semibold mb-2">
                        Gráfico — Página {g.page}
                    </h3>

                    {g.title && (
                        <div className="text-gray-800 mb-2 font-medium">
                            {g.title}
                        </div>
                    )}

                    <pre className="text-sm bg-gray-50 p-3 rounded">
                        {JSON.stringify(g.data_json, null, 2)}
                    </pre>

                    {g.insights_json?.length > 0 && (
                        <div className="mt-2 text-sm text-green-700">
                            <strong>Insights:</strong>
                            <ul className="list-disc ml-5">
                                {g.insights_json.map((i, idx2) => (
                                    <li key={idx2}>{i}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

/* -------------------------------
 * 4️⃣ TAB: TABLAS DETECTADAS
 * ------------------------------- */
function TablesTab({ pages }) {
    const tables = pages.flatMap((p) =>
        (p.tables ?? []).map((t) => ({ page: p.page_number, ...t }))
    );

    if (tables.length === 0) return <div>No se detectaron tablas.</div>;

    return (
        <div className="space-y-6">
            {tables.map((t, idx) => (
                <div key={idx} className="p-4 border rounded bg-white shadow">
                    <h3 className="font-semibold mb-2">
                        Tabla — Página {t.page}
                    </h3>

                    <pre className="text-sm bg-gray-50 p-3 rounded">
                        {JSON.stringify(t.data_json, null, 2)}
                    </pre>

                    {t.insights_json?.length > 0 && (
                        <div className="mt-2 text-sm text-blue-700">
                            <strong>Insights:</strong>
                            <ul className="list-disc ml-5">
                                {t.insights_json.map((i, idx2) => (
                                    <li key={idx2}>{i}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

/* -------------------------------
 * UTIL: Caja de sección
 * ------------------------------- */
function Section({ title, text }) {
    return (
        <div>
            <h2 className="text-lg font-semibold mb-2">{title}</h2>
            <div className="p-3 bg-gray-50 rounded whitespace-pre-wrap text-sm">
                {text}
            </div>
        </div>
    );
}
