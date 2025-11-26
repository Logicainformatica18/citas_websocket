import AppLayout from "@/layouts/app-layout";
import { Link } from "@inertiajs/react";

// =============================
// ✅ Tipado de Props
// =============================
interface WebResult {
    id: number;
    url: string;
    status: string;
    created_at: string;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    source: {
        id: number;
        name: string;
        url: string | null;
    };
    results: Pagination<WebResult>;
}

// =============================
// ✅ Componente TSX
// =============================
export default function Index({ source, results }: Props) {
    return (
        <AppLayout title={`Resultados Web – ${source.name}`}>
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-6">
                    Resultados de Scraping Web – {source.name}
                </h1>

                <table className="w-full border text-sm">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="p-2 border">ID</th>
                            <th className="p-2 border">URL</th>
                            <th className="p-2 border">Estado</th>
                            <th className="p-2 border">Fecha</th>
                            <th className="p-2 border">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        {results.data.map((result: WebResult) => (
                            <tr key={result.id} className="border-t">
                                <td className="p-2 border">{result.id}</td>
                                <td className="p-2 border text-blue-600">
                                    {result.url}
                                </td>
                                <td className="p-2 border">
                                    {result.status}
                                </td>
                                <td className="p-2 border">
                                    {result.created_at}
                                </td>
                                <td className="p-2 border">
                                    <Link
                                        href={route("scraping.results.show", result.id)}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        Ver
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
