import { useState } from "react";
import axios from "axios";

type Props = {
  open: boolean;
  onClose: () => void;
  onImported: () => void;
};

// Diccionario modalidades
const modalityMap: Record<number, string> = {
  1: "Presencial",
  2: "Remoto",
  3: "Híbrido",
};

// Formatear fecha desde timestamp UNIX
const formatDateUnix = (timestamp: number | null | undefined) => {
  if (!timestamp) return "N/A";
  try {
    return new Date(timestamp * 1000).toLocaleDateString("es-PE", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  } catch {
    return "N/A";
  }
};

export default function JobOfferModal({ open, onClose, onImported }: Props) {
  const [apiUrl, setApiUrl] = useState(
    "https://www.getonbrd.com/api/v0/search/jobs?query=programador+web&per_page=10"
  );
  const [preview, setPreview] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  if (!open) return null;

  const handleFetch = async () => {
    if (!apiUrl.trim()) return;
    setLoading(true);
    try {
      const res = await axios.get(apiUrl);
      setPreview(res.data.data || []);
    } catch (e) {
      console.error("Error al obtener datos externos", e);
      alert("No se pudo obtener datos de la API.");
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!apiUrl.trim()) return;
    setLoading(true);
    try {
      await axios.post("/job-offers/import", { api_url: apiUrl });
      onImported();
      onClose();
    } catch (e) {
      console.error("Error al guardar", e);
      alert("No se pudo guardar en la base de datos.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg w-[800px] p-6 space-y-4">
        <h2 className="text-xl font-bold mb-2">Importar Ofertas desde API</h2>

        <input
          type="text"
          value={apiUrl}
          onChange={(e) => setApiUrl(e.target.value)}
          className="w-full p-2 border rounded"
        />

        <div className="flex gap-2">
          <button
            onClick={handleFetch}
            disabled={loading}
            className="px-4 py-2 bg-blue-600 text-white rounded"
          >
            {loading ? "Cargando..." : "Obtener"}
          </button>
          <button
            onClick={handleSave}
            disabled={loading}
            className="px-4 py-2 bg-green-600 text-white rounded"
          >
            Guardar en BD
          </button>
          <button
            onClick={onClose}
            className="px-4 py-2 bg-gray-400 text-white rounded"
          >
            Cancelar
          </button>
        </div>

        {preview.length > 0 && (
          <div className="max-h-96 overflow-y-auto border rounded">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-100">
                <tr>
                  <th className="px-2 py-1 text-left">Título</th>
                  <th className="px-2 py-1 text-left">Empresa</th>
                  <th className="px-2 py-1 text-left">Ubicación</th>
                  <th className="px-2 py-1 text-left">Modalidad</th>
                  <th className="px-2 py-1 text-left">Salario</th>
                  <th className="px-2 py-1 text-left">Publicado</th>
                </tr>
              </thead>
              <tbody>
                {preview.map((job) => {
                  const attr = job.attributes || {};

                  const companyName =
                    attr.company?.data?.attributes?.name ??
                    "N/A"; // ⚠️ puede no venir en search/jobs

                  const modality = attr.modality?.data?.id
                    ? modalityMap[attr.modality.data.id] ?? `ID ${attr.modality.data.id}`
                    : "N/A";

                  const location = attr.remote_modality
                    ? `${attr.remote_modality} (${attr.countries?.join(", ") ?? "?"})`
                    : attr.countries?.join(", ") ?? "N/A";

                  const salary =
                    attr.min_salary && attr.max_salary
                      ? `${attr.min_salary} - ${attr.max_salary} USD`
                      : "N/A";

                  return (
                    <tr key={job.id} className="border-t">
                      <td className="px-2 py-1">
                        {job.links?.public_url ? (
                          <a
                            href={job.links.public_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-600 hover:underline"
                          >
                            {attr.title ?? "N/A"}
                          </a>
                        ) : (
                          attr.title ?? "N/A"
                        )}
                      </td>
                      <td className="px-2 py-1">{companyName}</td>
                      <td className="px-2 py-1">{location}</td>
                      <td className="px-2 py-1">{modality}</td>
                      <td className="px-2 py-1">{salary}</td>
                      <td className="px-2 py-1">
                        {formatDateUnix(attr.published_at)}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
