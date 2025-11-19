export default function PartMetadata({ part }) {
    return (
        <div className="p-4 bg-gray-100 rounded border">
            <h2 className="text-xl font-bold mb-3">Metadata</h2>

            <pre className="bg-black text-green-300 p-4 rounded text-sm">
{JSON.stringify(part, null, 2)}
            </pre>
        </div>
    );
}
