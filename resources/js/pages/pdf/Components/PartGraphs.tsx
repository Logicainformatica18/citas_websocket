import React, { useState } from "react";
import {
    BarChart, Bar, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer, CartesianGrid
} from "recharts";

export default function PartGraphs({ pages }) {
    const graphs = pages.flatMap((p) => p.graphs || []);

    const normalizeData = (raw) => {
        if (!raw) return null;

        // 1) Si viene string → parsearlo
        if (typeof raw === "string") {
            try {
                raw = JSON.parse(raw);
            } catch (e) {
                console.error("JSON inválido:", raw);
                return null;
            }
        }

        // 2) Validación del formato
        if (!Array.isArray(raw.labels) || !Array.isArray(raw.values)) {
            console.warn("Formato inesperado:", raw);
            return null;
        }

        const labels = raw.labels; // ["Now", "2030"]
        const values = raw.values; // [...numeros]

        const rows = [];
        let index = 0;

        while (index < values.length) {
            let row: any = { categoria: `Grupo ${rows.length + 1}` };

            labels.forEach((label) => {
                row[label] = values[index] ?? null;
                index++;
            });

            rows.push(row);
        }

        return rows;
    };

    return (
        <div>
            <h2 className="text-xl font-bold mb-3">Gráficos detectados</h2>

            {graphs.length === 0 && <p>No se detectaron gráficos.</p>}

            {graphs.map((graph, index) => {
                const [showJson, setShowJson] = useState(false);

                const data = normalizeData(graph?.data_json);

                return (
                    <div key={index} className="mb-8 p-5 border rounded bg-white shadow-sm">

                        <h3 className="font-semibold text-lg mb-2">
                            {graph.title || `Gráfico ${index + 1}`}
                        </h3>

                        {/* Botón para ver JSON */}
                        <button
                            onClick={() => setShowJson(!showJson)}
                            className="text-blue-600 text-sm mb-3"
                        >
                            {showJson ? "Ocultar datos (OCR)" : "Ver datos originales (OCR)"}
                        </button>

                        {showJson && (
                            <pre className="bg-gray-100 p-3 rounded text-sm mb-4 border">
                                {JSON.stringify(graph.data_json, null, 2)}
                            </pre>
                        )}

                        {!data ? (
                            <p className="text-red-600 text-sm mt-2">
                                No se pudo interpretar la estructura del gráfico.
                            </p>
                        ) : (
                            <div style={{ width: "100%", height: 350 }}>
                                <ResponsiveContainer>
                                    <BarChart data={data}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis dataKey="categoria" />
                                        <YAxis />
                                        <Tooltip />
                                        <Legend />

                                        {Object.keys(data[0])
                                            .filter((key) => key !== "categoria")
                                            .map((key, i) => (
                                                <Bar
                                                    key={i}
                                                    dataKey={key}
                                                    fill={`hsl(${(i * 60) % 360},70%,50%)`}
                                                />
                                            ))}
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
