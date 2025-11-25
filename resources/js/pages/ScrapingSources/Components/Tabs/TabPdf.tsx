export default function TabPdf({
    existingPdf,
    handleFile,
}: {
    existingPdf?: string | null;
    handleFile: (e: any, field: string) => void;
}) {
    return (
        <div className="space-y-4">
            {existingPdf && (
                <div className="mb-4">
                    <p className="text-sm font-medium mb-2">PDF actual:</p>

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

            <label className="text-sm font-medium"></label>
            <input
                type="file"
                accept="application/pdf"
                className="mt-2"
                onChange={(e) => handleFile(e, "pdf_file")}
            />
        </div>
    );
}
