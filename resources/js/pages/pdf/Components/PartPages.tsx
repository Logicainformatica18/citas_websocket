export default function PartPages({ pages }) {
    return (
        <div>
            <h2 className="text-xl font-bold mb-3">Texto OCR por página</h2>

            {pages.map((p) => (
                <div key={p.id} className="mb-4 p-3 border rounded bg-white">
                    <h3 className="font-semibold">Página {p.page_number}</h3>
                    <pre className="text-sm bg-gray-50 p-3 rounded">
{p.text_content || "Sin texto"}
                    </pre>
                </div>
            ))}
        </div>
    );
}
