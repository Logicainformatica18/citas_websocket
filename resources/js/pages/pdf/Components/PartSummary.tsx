import React from "react";

export default function PartSummary({ summary }: { summary: any }) {
    if (!summary) {
        return (
            <div className="text-gray-500 italic">
                No se encontró un resumen para esta parte.
            </div>
        );
    }

    return (
        <div className="bg-white p-6 rounded-md shadow border">
            <h2 className="text-2xl font-bold mb-4">Resumen</h2>

            {/* RESUMEN CORTO */}
            {summary.summary_short && (
                <div className="mb-6">
                    <h3 className="text-lg font-semibold mb-1">Resumen corto</h3>
                    <p className="text-gray-700 leading-relaxed">
                        {summary.summary_short}
                    </p>
                </div>
            )}

            {/* RESUMEN MEDIANO */}
            {summary.summary_medium && (
                <div className="mb-6">
                    <h3 className="text-lg font-semibold mb-1">Resumen medio</h3>
                    <p className="text-gray-700 leading-relaxed">
                        {summary.summary_medium}
                    </p>
                </div>
            )}

            {/* RESUMEN LARGO */}
            {summary.summary_long && (
                <div className="mb-6">
                    <h3 className="text-lg font-semibold mb-1">Resumen largo</h3>
                    <p className="text-gray-700 leading-relaxed whitespace-pre-line">
                        {summary.summary_long}
                    </p>
                </div>
            )}

            {/* INSIGHTS */}
            {(summary.insights_json ?? []).length > 0 && (
                <div className="mb-6">
                    <h3 className="text-lg font-semibold mb-2">Insights principales</h3>
                    <ul className="list-disc ml-6 text-gray-700">
                        {summary.insights_json.map((ins: string, idx: number) => (
                            <li key={idx}>{ins}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* TEMAS */}
            {(summary.topics_json ?? []).length > 0 && (
                <div className="mb-6">
                    <h3 className="text-lg font-semibold mb-2">Temas detectados</h3>
                    <div className="flex flex-wrap gap-2">
                        {summary.topics_json.map((topic: string, idx: number) => (
                            <span
                                key={idx}
                                className="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm"
                            >
                                {topic}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* OPCIONAL: Mostrar JSON crudo pero colapsado */}
            <details className="mt-4">
                <summary className="cursor-pointer text-blue-600 text-sm">
                    Ver datos crudos (JSON)
                </summary>
                <pre className="bg-gray-900 text-green-300 p-4 rounded mt-2 overflow-auto text-xs">
{JSON.stringify(summary, null, 2)}
                </pre>
            </details>
        </div>
    );
}
