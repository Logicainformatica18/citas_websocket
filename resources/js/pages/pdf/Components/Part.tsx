import React, { useState } from "react";
import { Link } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";

interface Props {
    pdf: any;
    part: any;
    pages: any[];
    summary: any;
}

export default function Part({ pdf, part, pages, summary }: Props) {
    const [tab, setTab] = useState("summary");

    return (
        <div className="p-6 max-w-6xl mx-auto">
            {/* BACK */}
            <Link
                href={route("pdf.show", pdf.id)}
                className="inline-flex items-center text-blue-600 hover:underline"
            >
                <ChevronLeft className="w-4 h-4 mr-1" />
                Volver al documento
            </Link>

            {/* HEADER */}
            <div className="mt-4 mb-6">
                <h1 className="text-3xl font-bold">
                    Parte {part.part_number} — {pdf.title}
                </h1>

                <div className="text-sm text-gray-500">
                    {pages.length} páginas procesadas
                </div>
            </div>

            {/* TABS */}
            <div className="flex gap-6 border-b mb-6">
                {[
                    ["summary", "Resumen"],
                    ["pages", "Páginas"],
                    ["tables", "Tablas"],
                    ["graphs", "Gráficos"],
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
            </div>

            {/* CONTENT */}
            {tab === "summary" && (
                <div>
                    <h2 className="text-xl font-bold mb-3">Resumen del bloque</h2>
                    {summary ? (
                        <pre className="bg-gray-50 p-4 rounded whitespace-pre-wrap">
                            {summary.summary_text}
                        </pre>
                    ) : (
                        <p>No hay resumen generado aún.</p>
                    )}
                </div>
            )}

            {tab === "pages" && (
                <div className="space-y-4">
                    {pages.map((p) => (
                        <div key={p.id} className="p-4 border rounded bg-white shadow-sm">
                            <h3 className="font-semibold mb-2">Página {p.page_number}</h3>
                            <pre className="text-sm whitespace-pre-wrap">
                                {p.text_content || "(sin texto OCR)"}
                            </pre>
                        </div>
                    ))}
                </div>
            )}

            {tab === "tables" && (
                <div className="space-y-4">
                    {pages.flatMap((p) => p.tables ?? []).length === 0 ? (
                        <p>No se detectaron tablas.</p>
                    ) : (
                        pages.flatMap((p) =>
                            (p.tables ?? []).map((t: any, idx: number) => (
                                <div key={idx} className="p-4 border rounded bg-white">
                                    <h3 className="font-semibold">Tabla detectada</h3>
                                    <pre className="bg-gray-50 p-3 rounded">
                                        {JSON.stringify(t.data_json, null, 2)}
                                    </pre>
                                </div>
                            ))
                        )
                    )}
                </div>
            )}

            {tab === "graphs" && (
                <div className="space-y-4">
                    {pages.flatMap((p) => p.graphs ?? []).length === 0 ? (
                        <p>No se detectaron gráficos.</p>
                    ) : (
                        pages.flatMap((p) =>
                            (p.graphs ?? []).map((g: any, idx: number) => (
                                <div key={idx} className="p-4 border rounded bg-white">
                                    <h3 className="font-semibold">Gráfico detectado</h3>
                                    <pre className="bg-gray-50 p-3 rounded">
                                        {JSON.stringify(g.data_json, null, 2)}
                                    </pre>
                                </div>
                            ))
                        )
                    )}
                </div>
            )}
        </div>
    );
}
