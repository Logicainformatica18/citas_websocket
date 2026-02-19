import { Loader2, Sparkles } from "lucide-react";
import { useEffect } from "react";

interface Competency {
  id: number;
  name: string;
  final_score: number; // porcentaje final (ej: 82.5)
  level: "Fuerte" | "Media" | "Débil" | "Crítica";
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
     BADGE SEGÚN NIVEL
  ========================== */
  const renderLevelBadge = (level: Competency["level"]) => {

    const styles = {
      Fuerte: "bg-green-100 text-green-700",
      Media: "bg-yellow-100 text-yellow-700",
      Débil: "bg-orange-100 text-orange-700",
      Crítica: "bg-red-100 text-red-700",
    };

    return (
      <span
        className={`px-3 py-1 rounded-full text-sm font-medium ${styles[level]}`}
      >
        {level}
      </span>
    );
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
              Puntaje
            </th>
            <th className="text-center px-6 py-4 font-semibold">
              Nivel estratégico
            </th>
            {/* <th className="text-center px-6 py-4 font-semibold">
              IA
            </th> */}
          </tr>
        </thead>

        <tbody>
          {competencies.map((comp) => (
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

              {/* Puntaje */}
              <td className="px-6 py-4 text-center font-semibold">
                {comp.final_score}%
              </td>

              {/* Nivel */}
              <td className="px-6 py-4 text-center">
                {renderLevelBadge(comp.level)}
              </td>

              {/* IA (opcional) */}
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
          ))}
        </tbody>
      </table>
    </div>
  );
}
