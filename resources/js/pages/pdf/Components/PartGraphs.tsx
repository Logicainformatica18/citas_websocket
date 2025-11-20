import React from "react";
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    CartesianGrid,
    ResponsiveContainer,
    LabelList,
} from "recharts";
import { BarChart3 } from "lucide-react";

// Paleta moderna estilo Looker Studio
const COLORS = [
    "#4F46E5", "#0EA5E9", "#10B981", "#F59E0B", "#EF4444",
    "#8B5CF6", "#EC4899", "#14B8A6", "#A3E635", "#FB923C",
    "#6366F1", "#22D3EE"
];

/* ----------------------------------------------------
   Convert OCR JSON → Recharts
------------------------------------------------------ */
function parseGraphData(graph: any) {
    if (!graph || !graph.data_json) return null;

    const labels = graph.data_json.labels || [];
    const values = graph.data_json.values || [];

    if (!Array.isArray(labels) || !Array.isArray(values)) return null;
    if (labels.length !== values.length) return null;

    return labels.map((label: any, idx: number) => ({
        name: String(label),
        value: Number(values[idx]),
        fill: COLORS[idx % COLORS.length],
    }));
}

/* ----------------------------------------------------
   Leyenda personalizada
------------------------------------------------------ */
function CustomLegend({ data }: { data: any[] }) {
    return (
        <div
            className="flex flex-wrap gap-4 mt-4 p-2 border-t
            border-gray-200 dark:border-gray-700
            text-sm overflow-x-auto"
        >
            {data.map((item, i) => (
                <div key={i} className="flex items-center gap-2 min-w-[200px]">
                    <div
                        className="w-4 h-4 rounded-sm border border-gray-300 dark:border-gray-600"
                        style={{ backgroundColor: item.fill }}
                    ></div>
                    <span className="text-gray-700 dark:text-gray-300">{item.name}</span>
                </div>
            ))}
        </div>
    );
}

export default function PartGraphs({ pages }: { pages: any[] }) {
    const pagesWithGraphs = pages.filter(
        (p) => (p.graphs ?? []).length > 0
    );

    if (pagesWithGraphs.length === 0) return null;

    return (
        <div>
            {/* TÍTULO PRINCIPAL */}
            <h2 className="text-2xl font-bold mb-6 flex items-center gap-2 uppercase">
                <BarChart3 className="w-6 h-6 text-blue-500" />
                Gráficos detectados
            </h2>

            {pagesWithGraphs.map((page) => (
                <div key={page.id} className="mb-12">

                    {/* ▲ Página a la derecha */}
                    <div className="flex justify-between items-center mb-3">
                        <div></div>
                        <span className="text-sm font-semibold text-gray-500 dark:text-gray-400">
                            Página {page.page_number}
                        </span>
                    </div>

                    {page.graphs.map((graph: any, idx: number) => {
                        const data = parseGraphData(graph);

                        return (
                            <div
                                key={idx}
                                className="p-4 mb-6 border rounded-lg
                                bg-white dark:bg-gray-800
                                border-gray-200 dark:border-gray-700
                                shadow-sm"
                            >
                                {/* Título del gráfico */}
                                <h4 className="text-lg font-semibold mb-2 text-gray-900 dark:text-gray-100 uppercase">
                                    {graph.title || `Gráfico ${idx + 1}`}
                                </h4>

                                {/* OCR Viewer */}
                                <details className="mb-3">
                                    <summary className="cursor-pointer text-blue-600 dark:text-blue-400 text-sm">
                                        Ver datos originales (OCR)
                                    </summary>

                                    <pre
                                        className="bg-gray-100 dark:bg-gray-900
                                        p-3 rounded mt-2
                                        text-xs text-gray-800 dark:text-gray-200
                                        overflow-x-auto border
                                        border-gray-200 dark:border-gray-700"
                                    >
{JSON.stringify(graph.data_json, null, 2)}
                                    </pre>
                                </details>

                                {/* Gráfico */}
                                {data ? (
                                    <>
                                        <div className="w-full overflow-x-auto pb-2">
                                            <div className="min-w-[1000px] h-80">
                                                <ResponsiveContainer width="100%" height="100%">
                                                    <BarChart data={data} margin={{ bottom: 40 }}>
                                                        <CartesianGrid
                                                            strokeDasharray="3 3"
                                                            stroke="#d1d5db"
                                                        />

                                                        <XAxis
                                                            dataKey="name"
                                                            tick={false}
                                                            axisLine={false}
                                                        />

                                                        <YAxis
                                                            stroke="#6b7280"
                                                            tick={{ fill: "#6b7280" }}
                                                        />

                                                        <Tooltip
                                                            contentStyle={{
                                                                background: "#ffffffee",
                                                                borderRadius: "8px",
                                                                border: "1px solid #e5e7eb",
                                                                color: "#000",
                                                            }}
                                                        />

                                                        <Bar dataKey="value">
                                                            <LabelList
                                                                dataKey="value"
                                                                position="insideTop"
                                                                className="font-bold"
                                                                style={{
                                                                    fill: "#fff",
                                                                    textShadow:
                                                                        "0px 0px 6px rgba(0,0,0,0.7)",
                                                                }}
                                                            />
                                                        </Bar>
                                                    </BarChart>
                                                </ResponsiveContainer>
                                            </div>
                                        </div>

                                        {/* Leyenda */}
                                        <CustomLegend data={data} />
                                    </>
                                ) : (
                                    <p className="text-red-500 dark:text-red-400 text-sm">
                                        No se pudo interpretar este gráfico.
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>
            ))}
        </div>
    );
}
