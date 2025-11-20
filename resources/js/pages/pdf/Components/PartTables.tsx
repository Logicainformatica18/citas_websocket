import React from "react";

interface Props {
    pages: any[];
}

export default function PartTables({ pages }: Props) {
    // Filtrar solo páginas que tengan tablas válidas
    const pagesWithTables = pages.filter(
        (p) => Array.isArray(p.tables) && p.tables.length > 0
    );

    // Si NO hay ninguna tabla → NO renderizar nada
    if (pagesWithTables.length === 0) {
        return null;
    }

    // Ordenado por número de página
    const orderedPages = [...pagesWithTables].sort(
        (a, b) => Number(a.page_number) - Number(b.page_number)
    );

    return (
        <div>
            <h2 className="text-xl font-bold mb-4">Tablas detectadas</h2>

            {orderedPages.map((page) => (
                <div
                    key={page.id}
                    className="mb-8 border rounded p-4 bg-white shadow-sm"
                >
                    <h3 className="font-semibold text-lg mb-3">
                        Página {page.page_number}
                    </h3>

                    {page.tables.map((table: any, idx: number) => {
                        const rows = table.data_json ?? [];

                        // Validación: tabla vacía o mal formada
                        if (!Array.isArray(rows) || rows.length === 0) {
                            return (
                                <div
                                    key={idx}
                                    className="p-3 border bg-gray-50 rounded"
                                >
                                    <p className="text-gray-500 text-sm">
                                        Tabla detectada pero sin datos válidos.
                                    </p>
                                </div>
                            );
                        }

                        const headers = rows[0];

                        return (
                            <div
                                key={idx}
                                className="mb-6 p-3 border bg-gray-50 rounded"
                            >
                                <h4 className="font-semibold mb-2">
                                    Tabla {idx + 1}
                                </h4>

                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm border border-gray-300">
                                        <thead className="bg-gray-200">
                                            <tr>
                                                {headers.map(
                                                    (col: any, i: number) => (
                                                        <th
                                                            key={i}
                                                            className="border px-2 py-1 font-semibold"
                                                        >
                                                            {col}
                                                        </th>
                                                    )
                                                )}
                                            </tr>
                                        </thead>

                                        <tbody>
                                            {rows
                                                .slice(1)
                                                .map(
                                                    (
                                                        row: any[],
                                                        rIdx: number
                                                    ) => (
                                                        <tr key={rIdx}>
                                                            {row.map(
                                                                (
                                                                    cell,
                                                                    cIdx
                                                                ) => (
                                                                    <td
                                                                        key={
                                                                            cIdx
                                                                        }
                                                                        className="border px-2 py-1"
                                                                    >
                                                                        {cell}
                                                                    </td>
                                                                )
                                                            )}
                                                        </tr>
                                                    )
                                                )}
                                        </tbody>
                                    </table>
                                </div>

                                {(table.insights_json ?? []).length > 0 && (
                                    <ul className="mt-2 text-sm text-gray-600 list-disc ml-5">
                                        {table.insights_json.map(
                                            (txt: string, ii: number) => (
                                                <li key={ii}>{txt}</li>
                                            )
                                        )}
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
