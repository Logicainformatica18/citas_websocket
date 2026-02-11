import { useEffect, useRef } from "react";
import {
  XCircle,
  AlertTriangle,
  Sparkles,
  RefreshCcw,
} from "lucide-react";

/* ======================================================
   TYPES
====================================================== */
type Competency = {
  id: number;
  name: string;
  market_match: boolean;
  trend_match: boolean;

  analysis?: {
    diagnosis?: string;
    recommendation?: string;
    updated_at?: string;
    source?: "auto" | "manual";
    status?: "loading" | "ready" | "error";
  };
};

type Props = {
  competencies: Competency[];

  /** 🔥 Auto-análisis (solo 1 vez, primer GAP) */
  onAutoAnalyze?: (competency: {
    id: number;
    name: string;
  }) => void;

  /** 🔁 Re-analizar manualmente */
  onReanalyze?: (competency: {
    id: number;
    name: string;
  }) => void;
};

/* ======================================================
   COMPONENT
====================================================== */
export default function CompetencyGapCard({
  competencies,
  onAutoAnalyze,
  onReanalyze,
}: Props) {
  const gaps = competencies.filter(
    (c) => !c.market_match && !c.trend_match
  );

  /* ======================================================
     AUTO-ANÁLISIS (solo una vez por carga)
  ====================================================== */
  const hasAutoTriggered = useRef(false);

  useEffect(() => {
    if (
      gaps.length > 0 &&
      onAutoAnalyze &&
      !hasAutoTriggered.current &&
      !gaps[0].analysis
    ) {
      hasAutoTriggered.current = true;

      onAutoAnalyze({
        id: gaps[0].id,
        name: gaps[0].name,
      });
    }
  }, [gaps, onAutoAnalyze]);

  return (
    <div className="rounded-xl border bg-white dark:bg-gray-900 p-5 shadow-sm h-full">
      {/* HEADER */}
      <div className="flex items-center gap-2 mb-2">
        <AlertTriangle className="w-5 h-5 text-yellow-500" />
        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
          GAP de Competencias
        </h3>
      </div>

      <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">
        Competencias no alineadas con el mercado ni reportes estratégicos
      </p>

      {/* LISTA GAP */}
      <div className="space-y-4">
        {gaps.map((c, index) => (
          <div
            key={c.id}
            className="rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-4 space-y-3"
          >
            {/* TÍTULO */}
            <div className="flex items-center gap-2">
              <XCircle className="w-4 h-4 text-red-500" />
              <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                {c.name}
              </span>
            </div>

            {/* BADGE */}
            <span className="inline-block text-xs px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-300">
              Sin alineación
            </span>

            {/* ESTADO IA */}
            {c.analysis?.status === "loading" && (
              <div className="text-xs text-gray-500 dark:text-gray-400 italic">
                Analizando automáticamente…
              </div>
            )}

            {c.analysis?.status === "error" && (
              <div className="text-xs text-red-600 dark:text-red-400">
                Error al generar recomendación IA
              </div>
            )}

            {/* RESULTADO IA */}
            {c.analysis?.status === "ready" && (
              <div className="rounded-md bg-white/70 dark:bg-gray-800 p-3 border border-blue-200 dark:border-blue-900">
                <div className="flex items-center gap-1 text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1">
                  <Sparkles className="w-3 h-3" />
                  Recomendación académica (IA)
                </div>

                <p className="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                  {c.analysis.recommendation}
                </p>

                <div className="mt-1 text-[11px] text-gray-500 dark:text-gray-400 italic">
                  {c.analysis.source === "auto"
                    ? "Generado automáticamente"
                    : "Generado manualmente"}{" "}
                  · Última actualización {c.analysis.updated_at}
                </div>
              </div>
            )}

            {/* SIN ANÁLISIS */}
            {!c.analysis && (
              <div className="text-xs text-gray-500 dark:text-gray-400 italic">
                Pendiente de análisis IA
              </div>
            )}

            {/* ACCIONES */}
            <div className="flex justify-end pt-1">
              <button
                onClick={() =>
                  onReanalyze?.({
                    id: c.id,
                    name: c.name,
                  })
                }
                className="flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:underline"
              >
                <RefreshCcw className="w-3 h-3" />
                Reanalizar competencia
              </button>
            </div>

            {/* FLAG VISUAL AUTO */}
            {index === 0 && !c.analysis && (
              <div className="text-[11px] text-gray-500 dark:text-gray-400 italic">
                Analizado automáticamente
              </div>
            )}
          </div>
        ))}

        {gaps.length === 0 && (
          <div className="text-xs text-gray-500 dark:text-gray-400">
            No se detectaron GAP críticos 🎉
          </div>
        )}
      </div>

      {/* FOOTER */}
      <div className="mt-4 text-xs text-gray-700 dark:text-gray-300 font-medium text-right">
        Total competencias con GAP:{" "}
        <span className="font-semibold">
          {gaps.length} de {competencies.length}
        </span>
      </div>
    </div>
  );
}
