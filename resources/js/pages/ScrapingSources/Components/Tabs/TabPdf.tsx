export default function TabPdf({
    existingPdf,
    sourceId,
    handleFile,
}: {
    existingPdf?: string | null;
    sourceId: number;
    handleFile: (e: any, field: string) => void;
}) {
    return (
        <div className="space-y-4">

            {/* 📌 1. Mostrar PDF actual (archivo completo) */}
            {existingPdf && (
                <div className="mb-4">
                    <p className="text-sm font-medium mb-2">PDF completo cargado:</p>

                    <iframe
                        src={`/storage/${existingPdf}`}
                        className="w-full h-64 border rounded-lg"
                    />

                    <a
                        href={`/storage/${existingPdf}`}
                        target="_blank"
                        className="text-blue-600 text-sm underline block mt-2"
                    >
                        Abrir en nueva pestaña
                    </a>
                </div>
            )}

            {/* 📌 2. Subir PDF completo */}
            <div>
                <label className="text-sm font-medium">Subir nuevo PDF completo</label>
                <input
                    type="file"
                    accept="application/pdf"
                    className="mt-2"
                    onChange={(e) => handleFile(e, "pdf_file")}
                />
            </div>

            {/* 📌 3. Ir al módulo de partes */}
            <a
                href={`/scraping-sources/${sourceId}/parts`}
                target="_blank"
                className="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700"
            >
                Gestionar Partes del PDF
            </a>

            <p className="text-xs text-gray-500">
                Desde el módulo de partes podrás dividir este PDF, procesarlo, extraer tablas,
                gráficos, textos y resúmenes.
            </p>
        </div>
    );
}
