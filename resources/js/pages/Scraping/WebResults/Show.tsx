import AppLayout from "@/layouts/app-layout";

// =============================
// Tipado de Props
// =============================
interface WebResult {
    id: number;
    url: string;
    status: string;
    error_message?: string | null;
    ai_json: any; // puedes tiparlo más si quieres
    created_at: string;
}

interface Props {
    result: WebResult;
}

// =============================
// Componente
// =============================
export default function Show({ result }: Props) {
    return (
        <AppLayout title={`Resultado #${result.id}`}>
            <div className="p-6">

                <h1 className="text-2xl font-bold mb-4">
                    Resultado #{result.id}
                </h1>

                <p className="mb-2">
                    <strong>URL:</strong> {result.url}
                </p>

                <p className="mb-4">
                    <strong>Estado:</strong> {result.status}
                </p>

                {result.error_message && (
                    <div className="bg-red-100 text-red-700 p-3 mb-4 rounded">
                        <strong>Error:</strong> {result.error_message}
                    </div>
                )}

                <h2 className="text-xl font-semibold mb-2">JSON procesado:</h2>

                <pre className="bg-gray-900 text-green-400 p-4 rounded overflow-auto text-sm">
                    {JSON.stringify(result.ai_json, null, 2)}
                </pre>

            </div>
        </AppLayout>
    );
}
