import { useState } from "react";
import axios from "axios";

type Props = {
  open: boolean;
  onClose: () => void;
  onImported: () => void;
};

const modalityMap: Record<string, string> = {
  onsite: "Presencial",
  remote: "Remoto",
  hybrid: "Híbrido",
};

const formatDate = (input: any) => {
  if (!input) return "N/A";
  try {
    if (!isNaN(input)) {
      return new Date(Number(input) * 1000).toLocaleDateString("es-PE", {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
    }
    return new Date(input).toLocaleDateString("es-PE", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  } catch {
    return "N/A";
  }
};

// Normalizador de datos de preview
const normalizePreview = (raw: any, source: string) => {
  console.log("🔍 Datos crudos recibidos de backend:", raw);

  if (source === "getonboard") {
    return (raw?.raw?.data ?? []).map((job: any) => {
      const attr = job.attributes ?? {};
      return {
        id: job.id,
        title: attr.title ?? "N/A",
        company: attr.company?.data?.attributes?.name ?? "N/A",
        country: attr.countries?.join(", ") ?? "-",
        city: attr.city ?? "-",
        modality: attr.remote_modality
          ? modalityMap[attr.remote_modality] ?? attr.remote_modality
          : "N/A",
        salary:
          attr.min_salary && attr.max_salary
            ? `${attr.min_salary} - ${attr.max_salary} ${attr.salary_currency ?? "USD"}`
            : "N/A",
        url: job.links?.public_url ?? null,
        published_at: attr.published_at,
      };
    });
  }

  if (source === "adzuna") {
    return (raw?.raw?.results ?? []).map((job: any, idx: number) => {
      console.log("📌 Job Adzuna crudo:", job);
      return {
        id: idx,
        title: job.title ?? "N/A",
        company: job.company?.display_name ?? "N/A",
        country: job.location?.area?.[0] ?? "-",
        city: job.location?.area?.[1] ?? "-",
        modality: "N/A",
        salary:
          job.salary_min && job.salary_max
            ? `${job.salary_min} - ${job.salary_max} ${job.salary_currency ?? "USD"}`
            : "N/A",
        url: job.redirect_url ?? null,
        published_at: job.created,
      };
    });
  }

  return [];
};

export default function JobOfferModal({ open, onClose, onImported }: Props) {
  const [source, setSource] = useState<"getonboard" | "adzuna">("getonboard");
  const [query, setQuery] = useState("programador");
  const [preview, setPreview] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  if (!open) return null;

  const handleFetch = async () => {
    setLoading(true);
    try {
      console.log("🚀 Enviando request preview:", { source, query });
      const res = await axios.post("/job-offers/preview", { source, query });
      console.log("✅ Respuesta backend preview:", res.data);
      const norm = normalizePreview(res.data, source);
      console.log("📊 Preview normalizado:", norm);
      setPreview(norm);
    } catch (e) {
      console.error("❌ Error obteniendo preview:", e);
      alert("No se pudo obtener datos de la API.");
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    setLoading(true);
    try {
      console.log("💾 Guardando en BD:", { source, query });
      await axios.post("/job-offers/import", { source, query });
      onImported();
      onClose();
    } catch (e) {
      console.error("❌ Error guardando en BD:", e);
      alert("No se pudo guardar en la base de datos.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-gray-900 rounded-lg w-[900px] p-6 space-y-4 shadow-lg border border-gray-200 dark:border-gray-700">
        <h2 className="text-xl font-bold mb-2 text-gray-800 dark:text-gray-100">
          Importar Ofertas por busqueda
        </h2>

        {/* Selección de fuente + query */}
        <div className="flex gap-2">
          <select
            value={source}
            onChange={(e) => setSource(e.target.value as "getonboard" | "adzuna")}
            className="p-2 border rounded bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border-gray-300 dark:border-gray-600"
          >
            <option value="getonboard">GetOnBoard</option>
            <option value="adzuna">Adzuna (US)</option>
          </select>

          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Buscar..."
            className="flex-1 p-2 border rounded bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border-gray-300 dark:border-gray-600"
          />
        </div>

        <div className="flex gap-2">
          <button
            onClick={handleFetch}
            disabled={loading}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded disabled:opacity-50"
          >
            {loading ? "Cargando..." : "Obtener"}
          </button>
          <button
            onClick={handleSave}
            disabled={loading}
            className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded disabled:opacity-50"
          >
            Guardar en BD
          </button>
          <button
            onClick={onClose}
            className="px-4 py-2 bg-gray-400 hover:bg-gray-500 text-white rounded"
          >
            Cancelar
          </button>
        </div>

        {preview.length > 0 && (
          <div className="max-h-96 overflow-y-auto border rounded border-gray-200 dark:border-gray-700">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                <tr>
                  <th className="px-2 py-1 text-left">Título</th>
                  <th className="px-2 py-1 text-left">Empresa</th>
                  <th className="px-2 py-1 text-left">País</th>
                  <th className="px-2 py-1 text-left">Ciudad</th>
                  <th className="px-2 py-1 text-left">Modalidad</th>
                  <th className="px-2 py-1 text-left">Salario</th>
                  <th className="px-2 py-1 text-left">Publicado</th>
                </tr>
              </thead>
              <tbody className="text-gray-800 dark:text-gray-200">
                {preview.map((job) => (
                  <tr
                    key={job.id}
                    className="border-t border-gray-200 dark:border-gray-700"
                  >
                    <td className="px-2 py-1">
                      {job.url ? (
                        <a
                          href={job.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-blue-600 dark:text-blue-400 hover:underline"
                        >
                          {job.title}
                        </a>
                      ) : (
                        job.title
                      )}
                    </td>
                    <td className="px-2 py-1">{job.company}</td>
                    <td className="px-2 py-1">{job.country}</td>
                    <td className="px-2 py-1">{job.city}</td>
                    <td className="px-2 py-1">{job.modality}</td>
                    <td className="px-2 py-1">{job.salary}</td>
                    <td className="px-2 py-1">{formatDate(job.published_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
