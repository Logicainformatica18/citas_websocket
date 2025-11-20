import React from "react";

export default function PartSummary({ summary }: { summary: any }) {
    if (!summary) {
        return (
            <div className="text-gray-600 dark:text-gray-400 italic">
                No se encontró un resumen para esta parte.
            </div>
        );
    }

    return (
        <div
            className="bg-white dark:bg-gray-800
                       p-6 rounded-xl shadow-sm
                       border border-gray-200 dark:border-gray-700
                       text-gray-900 dark:text-gray-100"
        >
            <h2 className="text-2xl font-bold mb-6">Resumen</h2>

            {/* RESUMEN CORTO */}
            {summary.summary_short && (
                <div className="mb-6">
                    <h3 className="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-100">
                        Resumen corto
                    </h3>
                    <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                        {summary.summary_short}
                    </p>
                </div>
            )}

            {/* RESUMEN MEDIO */}
            {summary.summary_medium && (
                <div className="mb-6">
                    <h3 className="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-100">
                        Resumen medio
                    </h3>
                    <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                        {summary.summary_medium}
                    </p>
                </div>
            )}

            {/* RESUMEN LARGO */}
            {summary.summary_long && (
                <div className="mb-6">
                    <h3 className="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-100">
                        Resumen largo
                    </h3>
                    <p className="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                        {summary.summary_long}
                    </p>
                </div>
            )}

            {/* INSIGHTS */}
            {(summary.insights_json ?? []).length > 0 && (
                <div className="mb-8">
                    <h3 className="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-100">
                        Insights principales
                    </h3>

                    <ul className="list-disc ml-6 text-gray-700 dark:text-gray-300 space-y-1">
                        {summary.insights_json.map((ins: string, idx: number) => (
                            <li key={idx}>{ins}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* TEMAS */}
            {(summary.topics_json ?? []).length > 0 && (
                <div className="mb-8">
                    <h3 className="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-100">
                        Temas detectados
                    </h3>

                    <div className="flex flex-wrap gap-2">
                        {summary.topics_json.map((topic: string, idx: number) => (
                            <span
                                key={idx}
                                className="px-3 py-1 text-sm rounded-full
                                           bg-blue-100 text-blue-700
                                           dark:bg-blue-900 dark:text-blue-200
                                           border border-blue-200 dark:border-blue-700"
                            >
                                {topic}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* JSON RAW (COLLAPSIBLE) */}
            <details className="mt-6">
                <summary className="cursor-pointer text-blue-600 dark:text-blue-400 text-sm">
                    Ver datos crudos (JSON)
                </summary>

                <pre
                    className="bg-gray-900 text-green-300 p-4 rounded-lg mt-3
                               text-xs overflow-auto border border-gray-700"
                >
{JSON.stringify(summary, null, 2)}
                </pre>
            </details>
        </div>
    );
}
