import { useEffect, useState } from "react";
import axios from "axios";
import { X, Sparkles, RefreshCw } from "lucide-react";

type Props = {
  open: boolean;
  onClose: () => void;

  competency: {
    id: number;
    name: string;
  };

  context: {
    career_id: number;
    year: number;
    period: string;
  };
};

type AnalysisResult = {
  source: "cache" | "ai";
  analysis: {
    diagnosis: string;
    recommendation: string;
    generated_at: string;
  };
};

export default function CompetencyAIAnalysisDrawer({
  open,
  onClose,
  competency,
  context,
}: Props) {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<AnalysisResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  /* ======================================================
     FETCH IA ANALYSIS
  ====================================================== */
  const fetchAnalysis = () => {
    setLoading(true);
    setError(null);

    axios
      .get(
        `/dashboard/indicators/pe-alignment/competency/${competency.id}/analyze`,
        {
          params: context,
        }
      )
      .then((res) => setResult(res.data))
      .catch(() =>
        setError("No se pudo generar el análisis de la competencia.")
      )
      .finally(() => setLoading(false));
  };

  /* ======================================================
     AUTO LOAD
  ====================================================== */
  useEffect(() => {
    if (open) {
      fetchAnalysis();
    } else {
      setResult(null);
      setError(null);
    }
  }, [open]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[9999] flex">
      {/* BACKDROP */}
      <div
        className="flex-1 bg-black/40"
        onClick={onClose}
      />

      {/* DRAWER */}
      <div className="w-full max-w-md bg-white dark:bg-gray-900 border-l dark:border-gray-800 p-6 overflow-y-auto">
        {/* HEADER */}
        <div className="flex items-start justify-between mb-4">
          <div>
            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
              Análisis IA de Competencia
            </h3>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              {competency.name}
            </p>
          </div>

          <button onClick={onClose}>
            <X className="w-4 h-4 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" />
          </button>
        </div>

        {/* CONTENT */}
        {loading && (
          <div className="text-sm text-gray-500 dark:text-gray-400">
            Analizando competencia…
          </div>
        )}

        {error && (
          <div className="text-sm text-red-600">
            {error}
          </div>
        )}

        {result && (
          <div className="space-y-4">
            {/* SOURCE */}
            <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
              <Sparkles className="w-4 h-4" />
              {result.source === "cache"
                ? "Resultado guardado previamente"
                : "Generado por IA"}
            </div>

            {/* DIAGNOSIS */}
            <div>
              <h4 className="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Diagnóstico
              </h4>
              <p className="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">
                {result.analysis.diagnosis}
              </p>
            </div>

            {/* RECOMMENDATION */}
            <div className="rounded-lg border border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-950 p-4">
              <h4 className="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1">
                Recomendación académica
              </h4>
              <p className="text-sm text-blue-900 dark:text-blue-200 leading-relaxed">
                {result.analysis.recommendation}
              </p>
            </div>

            {/* META */}
            <div className="text-xs text-gray-500 dark:text-gray-400">
              Última actualización:{" "}
              {new Date(result.analysis.generated_at).toLocaleString()}
            </div>

            {/* ACTIONS */}
            <button
              onClick={fetchAnalysis}
              className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300 hover:underline"
            >
              <RefreshCw className="w-3 h-3" />
              Reanalizar competencia
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
