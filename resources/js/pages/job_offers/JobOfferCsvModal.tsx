import { useState, useEffect } from "react";
import axios from "axios";
import { X, Upload, FileSpreadsheet } from "lucide-react";

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

      if (!data.columns || !data.path)
        throw new Error("Respuesta inválida del servidor");

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
        `✅ Importación completa:\nInsertados: ${inserted}\nSaltados: ${skipped}`
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
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Fondo difuminado */}
      <div
        className="fixed inset-0 bg-black/60 dark:bg-black/70 backdrop-blur-sm"
        aria-hidden="true"
      />

      {/* Contenedor del modal */}
      <div className="relative bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between z-10">
          <div className="flex items-center gap-2">
            <FileSpreadsheet className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            <h2 className="text-lg font-semibold">
              {step === 1 ? "Subir archivo CSV" : "Mapeo de columnas"}
            </h2>
          </div>
          <button
            onClick={onClose}
            className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Contenido scrolleable */}
        <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
          {/* Paso 1 */}
          {step === 1 && (
            <div className="space-y-5">
              <div>
                <label className="block text-sm font-medium mb-2">
                  Selecciona un archivo CSV
                </label>
                <input
                  type="file"
                  accept=".csv"
                  onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                  className="w-full bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-700 rounded-lg p-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                />
              </div>

              <button
                onClick={handleUpload}
                disabled={loading || !file}
                className="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md px-4 py-2 transition disabled:opacity-60"
              >
                <Upload className="w-4 h-4" />
                {loading ? "Analizando CSV..." : "Detectar columnas"}
              </button>
            </div>
          )}

          {/* Paso 2 */}
          {step === 2 && (
            <>
              <div>
                <h3 className="font-semibold mb-2 text-base">
                  Mapeo de columnas CSV → Campos del sistema
                </h3>
                <div className="max-h-[45vh] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-3">
                  {columns.map((col, i) => (
                    <div
                      key={i}
                      className="grid grid-cols-2 items-center gap-3 border-b border-gray-100 dark:border-gray-800 pb-2"
                    >
                      <label className="text-sm font-medium truncate">
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
                        className="bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-sm rounded-md p-1 focus:ring-2 focus:ring-blue-500 w-full"
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
              </div>

              {/* Fuente */}
              <div>
                <label className="block text-sm mb-1 font-medium">
                  Fuente de datos
                </label>
                <input
                  list="sources"
                  value={source}
                  onChange={(e) => setSource(e.target.value)}
                  placeholder="Ejemplo: Computrabajo, GetOnBoard, Adzuna..."
                  className="bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-md p-2 w-full focus:ring-2 focus:ring-blue-500"
                />
                <datalist id="sources">
                  <option value="GetOnBoard" />
                  <option value="Computrabajo" />
                  <option value="Adzuna" />
                  <option value="LocalCSV" />
                </datalist>
              </div>
            </>
          )}
        </div>

        {/* Footer fijo */}
        <div className="sticky bottom-0 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-6 py-3 flex justify-end gap-3">
          <button
            onClick={onClose}
            className="px-4 py-2 rounded-md bg-gray-300 hover:bg-gray-400 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-100 transition"
            disabled={loading}
          >
            Cancelar
          </button>

          {step === 2 ? (
            <button
              onClick={handleImport}
              disabled={loading}
              className="px-4 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white font-medium shadow disabled:opacity-60 transition"
            >
              {loading ? "Importando..." : "Importar a Base de Datos"}
            </button>
          ) : (
            <button
              onClick={handleUpload}
              disabled={loading || !file}
              className="px-4 py-2 rounded-md bg-blue-600 hover:bg-blue-700 text-white font-medium shadow disabled:opacity-60 transition"
            >
              {loading ? "Analizando..." : "Detectar columnas"}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
