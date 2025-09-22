import { useState } from "react";
import { router } from "@inertiajs/react";
import axios from "axios";

type Scraping = {
  id?: number;
  name: string;
  base_url: string;
};

interface Props {
  scraping: Scraping | null;
  onClose: () => void;
  onSaved: (scraping: Scraping) => void;
}

export default function ScrapingModal({ scraping, onClose, onSaved }: Props) {
  const [form, setForm] = useState<Scraping>(
    scraping ?? { name: "", base_url: "" }
  );
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState<{ name?: string; base_url?: string }>({});
  const [results, setResults] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  // Guardar scraping
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);
    setErrors({});
    try {
      if (scraping?.id) {
        await router.put(`/scrapings/${scraping.id}`, form, {
          onSuccess: (page) => {
            const updated = page.props.scraping as Scraping;
            onSaved(updated);
            onClose();
          },
          onError: (err) => setErrors(err),
        });
      } else {
        await router.post(`/scrapings`, form, {
          onSuccess: (page) => {
            const created = page.props.scraping as Scraping;
            onSaved(created);
            onClose();
          },
          onError: (err) => setErrors(err),
        });
      }
    } finally {
      setProcessing(false);
    }
  };

const runScraping = async () => {
  if (!scraping?.id) return;
  setLoading(true);
  setResults([]);
  try {
    const res = await axios.post(`/scrapings/${scraping.id}/run`);
    console.log("📥 Resultados recibidos:", res.data.data); // 👈 debug aquí
 setResults(res.data.data.data || []); // 👈 ahora agarra directamente el array

  } catch (e) {
    console.error("Error al ejecutar scraping", e);
    alert("❌ Error al ejecutar scraping");
  } finally {
    setLoading(false);
  }
};


  return (
    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
      <div className="bg-white dark:bg-gray-900 rounded shadow-lg p-6 w-full max-w-4xl h-[90vh] overflow-y-auto">
        <h2 className="text-xl font-bold mb-4">
          {scraping ? "Editar Scraping" : "Nuevo Scraping"}
        </h2>

        {/* Formulario */}
        <form onSubmit={handleSubmit} className="space-y-4 mb-6">
          <div>
            <label className="block text-sm font-medium mb-1">Nombre</label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className="w-full border rounded px-3 py-2"
              placeholder="Ej: Cursos ISIL"
            />
            {errors.name && <p className="text-red-600 text-sm">{errors.name}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">URL base</label>
            <input
              type="url"
              value={form.base_url}
              onChange={(e) => setForm({ ...form, base_url: e.target.value })}
              className="w-full border rounded px-3 py-2"
              placeholder="https://isil.pe"
            />
            {errors.base_url && (
              <p className="text-red-600 text-sm">{errors.base_url}</p>
            )}
          </div>

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={processing}
              className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition disabled:opacity-50"
            >
              {processing ? "Guardando..." : "Guardar"}
            </button>
          </div>
        </form>

        {/* Botón ejecutar */}
        {scraping?.id && (
          <div className="mb-6">
            <button
              onClick={runScraping}
              disabled={loading}
              className="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition disabled:opacity-50"
            >
              {loading ? "Ejecutando..." : "Ejecutar Scraping"}
            </button>
          </div>
        )}

        {/* Resultados */}
        {results.length > 0 ? (
          <div>
            <h3 className="text-lg font-bold mb-2">Resultados del Scraping</h3>
            <div className="overflow-x-auto">
              <table className="min-w-full border border-gray-300 rounded bg-white dark:bg-black">
                <thead>
                  <tr>
                    {Object.keys(results[0]).map((col) => (
                      <th
                        key={col}
                        className="px-4 py-2 border text-black dark:text-white bg-gray-100 dark:bg-gray-800"
                      >
                        {col}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {results.map((row, i) => (
                    <tr
                      key={i}
                      className="border-t hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                      {Object.keys(results[0]).map((col) => (
                        <td
                          key={col}
                          className="px-4 py-2 border text-black dark:text-white"
                        >
                          {row[col] ?? "-"}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ) : scraping?.id && !loading ? (
          <div className="text-gray-500 dark:text-gray-400 italic">
            ⚠️ No se encontraron resultados todavía. Ejecuta el scraping para
            ver datos.
          </div>
        ) : null}
      </div>
    </div>
  );
}
