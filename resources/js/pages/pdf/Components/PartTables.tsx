import React, { useState } from "react";

export default function PartTables({ pages }) {
    // Todas las tablas de todas las páginas
    const tables = pages.flatMap((p) => p.tables || []);

    // Convertir JSON (string o array) a formato usable
    const normalize = (raw) => {
        if (!raw) return null;

        // Si viene como string → intentar parsear
        if (typeof raw === "string") {
            try {
                raw = JSON.parse(raw);
            } catch {
                return null;
            }
        }

        // Debe ser un array de arrays
        if (!Array.isArray(raw) || !Array.isArray(raw[0])) {
            return null;
        }

        return raw;
    };

    return (
        <div className="mt-6">
            <h2 className="text-xl font-bold mb-4">Tablas detectadas</h2>

            {tables.length === 0 && (
                <p className="text-gray-500">No se detectaron tablas.</p>
            )}

            {tables.map((tbl, idx) => {
                const [showJson, setShowJson] = useState(false);
                const data = normalize(tbl.data_json);

                return (
                    <div
                        key={idx}
                        className="mb-8 p-5 border rounded bg-white shadow-sm"
                    >
                        <h3 className="font-semibold text-lg mb-2">
                            Tabla {idx + 1}
                        </h3>

                        {/* botón JSON */}
                        <button
                            onClick={() => setShowJson(!showJson)}
                            className="text-blue-600 text-sm mb-3"
                        >
                            {showJson
                                ? "Ocultar datos originales"
                                : "Ver datos originales (OCR)"}
                        </button>

                        {showJson && (
                            <pre className="bg-gray-100 p-3 rounded text-sm border max-h-64 overflow-auto mb-4">
                                {JSON.stringify(tbl.data_json, null, 2)}
                            </pre>
                        )}

                        {/* Si no tiene estructura válida */}
                        {!data ? (
                            <p className="text-red-600 text-sm">
                                No se pudo interpretar la estructura de la tabla.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full border-collapse border border-gray-300">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            {data[0].map((col, i) => (
                                                <th
                                                    key={i}
                                                    className="border border-gray-300 px-3 py-2 text-left text-sm font-semibold"
                                                >
                                                    {col}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {data.slice(1).map((row, r) => (
                                            <tr key={r}>
                                                {row.map((cell, c) => (
                                                    <td
                                                        key={c}
                                                        className="border border-gray-300 px-3 py-2 text-sm"
                                                    >
                                                        {cell}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
