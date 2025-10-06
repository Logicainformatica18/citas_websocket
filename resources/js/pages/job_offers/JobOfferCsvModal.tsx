import { useState, useEffect } from "react";
import axios from "axios";
import { X, Upload } from "lucide-react";

interface Props {
  open: boolean;
  onClose: () => void;
  onImported: () => void;
}

export default function JobOfferCsvModal({ open, onClose, onImported }: Props) {
  const [file, setFile] = useState<File | null>(null);
  const [columns, setColumns] = useState<string[]>([]);
  const [path, setPath] = useState<string>("");
  const [loading, setLoading] = useState(false);
  const [step, setStep] = useState(1);
  const [mapping, setMapping] = useState<Record<string, string>>({});
  const [source, setSource] = useState("LocalCSV");

  if (!open) return null;

  // 🧠 Autoseleccionar columnas por nombre
  useEffect(() => {
    if (columns.length > 0) {
      const autoMap: Record<string, string> = {};
      const knownFields = [
        "title",
        "company",
        "city",
        "country",
        "modality",
        "date",
        "url",
        "latitude",
        "longitude",
      ];
      for (const col of columns) {
        const match = knownFields.find(
          (f) => f.toLowerCase() === col.toLowerCase()
        );
        autoMap[col] = match ?? "";
      }
      setMapping(autoMap);
    }
  }, [columns]);

  // 📤 Subir CSV
  const handleUpload = async () => {
    if (!file) return alert("Selecciona un archivo CSV");
    setLoading(true);
    try {
      const fd = new FormData();
      fd.append("file", file);
      const { data } = await axios.post("/job-offers/import/upload", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      if (!data.columns || !data.path) throw new Error("Respuesta inválida del servidor");

      setColumns(data.columns);
      setPath(data.path);
      setStep(2);
    } catch (e) {
      console.error(e);
      alert("❌ Error al analizar CSV. Verifica el archivo.");
    } finally {
      setLoading(false);
    }
  };

  // 🚀 Importar CSV
  const handleImport = async () => {
    if (!path) return alert("Ruta de archivo no detectada");
    setLoading(true);
    try {
      const { data } = await axios.post("/job-offers/import/process", {
        path,
        mapping,
        source,
      });

      const inserted = data?.inserted ?? 0;
      const skipped = data?.skipped ?? 0;

      alert(
        `✅ Importación completa:\n` +
          `Insertados: ${inserted}\n` +
          `Saltados: ${skipped}`
      );

      onImported();
      onClose();
    } catch (e) {
      console.error("❌ Error al procesar CSV:", e);
      alert("Error al procesar CSV");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
      <div className="bg-slate-800 rounded-lg w-full max-w-lg p-6 border border-slate-700 text-white shadow-xl">
        {/* Header */}
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold">
            {step === 1 ? "Subir archivo CSV" : "Mapeo de columnas"}
          </h2>
          <button onClick={onClose}>
            <X className="w-5 h-5 text-slate-400 hover:text-white" />
          </button>
        </div>

        {/* Paso 1 */}
        {step === 1 && (
          <div className="space-y-4">
            <input
              type="file"
              accept=".csv"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              className="w-full text-white border border-slate-600 rounded p-2 bg-slate-700"
            />

            <button
              onClick={handleUpload}
              disabled={loading || !file}
              className="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded disabled:opacity-50"
            >
              <Upload className="w-4 h-4" />
              {loading ? "Analizando..." : "Detectar columnas"}
            </button>
          </div>
        )}

        {/* Paso 2 */}
        {step === 2 && (
          <div>
            <h3 className="font-semibold mb-3">
              Mapear columnas CSV → Campos del sistema
            </h3>

            <div className="max-h-[45vh] overflow-y-auto border border-slate-700 rounded p-3 mb-3 space-y-3">
              {columns.map((col, i) => (
                <div
                  key={i}
                  className="grid grid-cols-2 items-center gap-3 border-b border-slate-700/60 pb-2"
                >
                  <label className="text-slate-300 text-sm font-medium truncate">
                    {col}
                  </label>

                  <select
                    value={mapping[col] ?? ""}
                    onChange={(e) =>
                      setMapping((prev) => ({
                        ...prev,
                        [col]: e.target.value,
                      }))
                    }
                    className="bg-slate-700 text-white text-sm rounded p-1 w-full"
                  >
                    <option value="">— Selecciona campo —</option>
                    <option value="title">Título</option>
                    <option value="company">Empresa</option>
                    <option value="city">Ciudad</option>
                    <option value="country">País</option>
                    <option value="modality">Modalidad</option>
                    <option value="date">Fecha</option>
                    <option value="url">URL</option>
                    <option value="latitude">Latitud</option>
                    <option value="longitude">Longitud</option>
                  </select>
                </div>
              ))}
            </div>

            {/* Fuente */}
            <div className="mt-4">
              <label className="block text-sm mb-1 font-medium">
                Fuente de datos
              </label>
              <input
                list="sources"
                value={source}
                onChange={(e) => setSource(e.target.value)}
                placeholder="Ejemplo: Computrabajo, GetOnBoard, Adzuna..."
                className="bg-slate-700 text-white rounded p-2 w-full"
              />
              <datalist id="sources">
                <option value="GetOnBoard" />
                <option value="Computrabajo" />
                <option value="Adzuna" />
                <option value="LocalCSV" />
              </datalist>
            </div>

            <button
              onClick={handleImport}
              disabled={loading}
              className="w-full bg-green-600 hover:bg-green-700 px-4 py-2 rounded mt-5 font-semibold disabled:opacity-50"
            >
              {loading ? "Importando..." : "Importar a Base de Datos"}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
