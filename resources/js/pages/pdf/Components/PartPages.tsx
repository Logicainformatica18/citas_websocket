export default function PartPages({ pages }) {
    return (
        <div className="text-gray-900 dark:text-gray-100">
            <h2 className="text-xl font-bold mb-6">Texto OCR por página</h2>

            {pages.map((p) => (
                <div
                    key={p.id}
                    className="mb-6 p-5 rounded-xl
                               bg-white dark:bg-gray-800
                               border border-gray-200 dark:border-gray-700
                               shadow-sm"
                >
                    <h3 className="text-lg font-semibold mb-3">
                        Página {p.page_number}
                    </h3>

                    <pre
                        className="text-sm leading-relaxed whitespace-pre-wrap
                                   bg-gray-100 dark:bg-gray-900
                                   text-gray-800 dark:text-gray-200
                                   p-4 rounded-lg overflow-x-auto
                                   border border-gray-300 dark:border-gray-700"
                    >
{p.text_content || "Sin texto"}
                    </pre>
                </div>
            ))}
        </div>
    );
}
