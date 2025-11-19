export default function PartSummary({ summary }) {
    if (!summary) return <p>No hay resumen generado.</p>;

    return (
        <div className="bg-gray-100 p-4 rounded border">
            <h2 className="text-xl font-bold mb-3">Resumen</h2>

            <pre className="bg-black text-green-300 p-4 rounded text-sm">
{JSON.stringify(summary, null, 2)}
            </pre>
        </div>
    );
}
