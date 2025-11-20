import React from "react";
import { Table2 } from "lucide-react";

interface Props {
    pages: any[];
}

export default function PartTables({ pages }: Props) {
    const pagesWithTables = pages.filter(
        (p) => Array.isArray(p.tables) && p.tables.length > 0
    );

    if (pagesWithTables.length === 0) return null;

    const orderedPages = [...pagesWithTables].sort(
        (a, b) => Number(a.page_number) - Number(b.page_number)
    );

    return (
        <div>
            {/* Título general */}
            <h2 className="text-2xl font-bold mb-6 flex items-center gap-2">
                <Table2 className="w-6 h-6 text-blue-500" />
                Tablas detectadas
            </h2>

            {orderedPages.map((page) => (
                <div key={page.id} className="mb-10">

                    {/* Encabezado con Página alineada a la derecha */}
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="text-lg font-semibold text-gray-200 dark:text-gray-300">
                            {/* vacío intencional; el título real es la primera columna de la tabla */}
                        </h3>

                        <span className="text-sm font-semibold text-gray-500 dark:text-gray-400">
                            Página {page.page_number}
                        </span>
                    </div>

                    {page.tables.map((table: any, idx: number) => {
                        const rows = table.data_json ?? [];

                        if (!Array.isArray(rows) || rows.length === 0) {
                            return (
                                <div
                                    key={idx}
                                    className="p-3 border bg-gray-50 dark:bg-gray-800 rounded"
                                >
                                    <p className="text-gray-500 dark:text-gray-400 text-sm">
                                        Tabla detectada pero sin datos válidos.
                                    </p>
                                </div>
                            );
                        }

                        // 👉 Aquí está la clave:
                        // La PRIMERA columna de la PRIMERA FILA es el título
                        const tableTitle = Array.isArray(rows[0]) ? rows[0][0] : "Tabla";

                        const headers = rows[0];

                        return (
                            <div
                                key={idx}
                                className="mb-6 p-4 border rounded-xl bg-white dark:bg-gray-800 shadow"
                            >
                                {/* Título tomado de la PRIMERA COLUMNA */}
                                <h4 className="text-xl font-semibold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                    <Table2 className="w-5 h-5 text-blue-500" />
                                    {tableTitle}
                                </h4>

                                <div className="overflow-x-auto rounded-lg">
                                    <table className="min-w-full text-sm border border-gray-300 dark:border-gray-700 rounded-lg">
                                        <thead className="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                {headers.map((col: any, i: number) => (
                                                    <th
                                                        key={i}
                                                        className="border px-3 py-2 font-semibold text-gray-800 dark:text-gray-100 dark:border-gray-600"
                                                    >
                                                        {col}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>

                                        <tbody>
                                            {rows.slice(1).map((row: any[], rIdx: number) => (
                                                <tr key={rIdx} className="even:bg-gray-50 dark:even:bg-gray-700/40">
                                                    {row.map((cell, cIdx) => (
                                                        <td
                                                            key={cIdx}
                                                            className="border px-3 py-2 text-gray-700 dark:text-gray-200 dark:border-gray-600"
                                                        >
                                                            {cell}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {(table.insights_json ?? []).length > 0 && (
                                    <ul className="mt-3 text-sm text-gray-700 dark:text-gray-300 list-disc ml-6">
                                        {table.insights_json.map((txt: string, ii: number) => (
                                            <li key={ii}>{txt}</li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        );
                    })}
                </div>
            ))}
        </div>
    );
}
