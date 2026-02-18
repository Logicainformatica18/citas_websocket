import { Check, X, Loader2, Sparkles } from "lucide-react";
import { useEffect } from "react";

interface Competency {
  id: number;
  name: string;
  job_count?: number;
  trend_count?: number;
  market_match?: boolean;
  trend_match?: boolean;

  // Puede venir uno u otro
  status?: "aligned" | "partial" | "gap";
  estado?: string;

  analysis?: {
    status?: "loading" | "ready" | "error";
  };
}

interface Props {
  competencies: Competency[];
  onAnalyze: (competency: { id: number; name: string }) => void;
  onSelectCompetency: (competency: Competency) => void;
}

export default function CompetencyTable({
  competencies,
  onAnalyze,
  onSelectCompetency,
}: Props) {

  useEffect(() => {
    console.log("COMPETENCIES DEBUG:", competencies);
  }, [competencies]);

  /* =========================
     NORMALIZAR ESTADO
  ========================== */
  const resolveStatus = (comp: Competency): "aligned" | "partial" | "gap" => {

    // 1️⃣ Si backend ya manda status correcto
    if (comp.status) return comp.status;

    // 2️⃣ Si backend manda texto "estado"
    if (comp.estado) {
      switch (comp.estado) {
        case "Altamente alineado":
        case "Estrategicamente alineado":
        case "Alineado":
          return "aligned";

        case "Parcialmente alineado":
          return "partial";

        default:
          return "gap";
      }
    }

    // 3️⃣ Fallback lógico usando booleanos
    if (comp.market_match && comp.trend_match) return "aligned";
    if (comp.market_match || comp.trend_match) return "partial";

    return "gap";
  };

  /* =========================
     BADGE
  ========================== */
  const renderStatusBadge = (status: "aligned" | "partial" | "gap") => {
    switch (status) {
      case "aligned":
        return (
          <span className="px-3 py-1 rounded-full text-sm bg-green-100 text-green-700 font-medium">
            ✔ Alta coincidencia
          </span>
        );

      case "partial":
        return (
          <span className="px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700 font-medium">
            ● Demanda / Tendencia
          </span>
        );

      default:
        return (
          <span className="px-3 py-1 rounded-full text-sm bg-red-100 text-red-700 font-medium">
            ● GAP crítico
          </span>
        );
    }
  };

  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl shadow border overflow-hidden">
      <table className="w-full text-sm">
        <thead className="bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-300">
          <tr>
            <th className="text-left px-6 py-4 font-semibold">
              Competencia PE
            </th>
            <th className="text-center px-6 py-4 font-semibold">
              Mercado
            </th>
            <th className="text-center px-6 py-4 font-semibold">
              Tendencias
            </th>
            <th className="text-center px-6 py-4 font-semibold">
              Estado
            </th>
            {/* <th className="text-center px-6 py-4 font-semibold">
              IA
            </th> */}
          </tr>
        </thead>

        <tbody>
          {competencies.map((comp) => {
            const normalizedStatus = resolveStatus(comp);

            return (
              <tr
                key={comp.id}
                className="border-t hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors duration-200"
              >

                {/* Competencia */}
                <td
                  onClick={() => onSelectCompetency(comp)}
                  className="px-6 py-4 font-medium text-blue-600 cursor-pointer hover:underline"
                >
                  {comp.name}
                </td>

                {/* Mercado */}
                <td className="px-6 py-4 text-center">
                  {comp.market_match ? (
                    <Check className="text-green-500 mx-auto" size={18} />
                  ) : (
                    <X className="text-red-500 mx-auto" size={18} />
                  )}
                </td>

                {/* Tendencias */}
                <td className="px-6 py-4 text-center">
                  {comp.trend_match ? (
                    <Check className="text-green-500 mx-auto" size={18} />
                  ) : (
                    <X className="text-red-500 mx-auto" size={18} />
                  )}
                </td>

                {/* Estado */}
                <td className="px-6 py-4 text-center">
                  {renderStatusBadge(normalizedStatus)}
                </td>

                {/* IA */}
                {/* <td className="px-6 py-4 text-center">
                  {comp.analysis?.status === "loading" ? (
                    <Loader2
                      className="animate-spin mx-auto text-indigo-600"
                      size={18}
                    />
                  ) : (
                    <button
                      onClick={() =>
                        onAnalyze({ id: comp.id, name: comp.name })
                      }
                      className="text-indigo-600 hover:text-indigo-800 flex items-center gap-1 justify-center transition"
                    >
                      <Sparkles size={16} />
                      Analizar
                    </button>
                  )}
                </td> */}

              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
