import { Briefcase, Sparkles } from "lucide-react";

/* ======================================================
   TYPES
====================================================== */
export interface CompetencyItem {
  id: number;
  name: string;
  market: boolean;
  prospective: boolean;
  score?: number;
}

interface Props {
  competencies?: CompetencyItem[];
  onViewJobs: (c: CompetencyItem) => void;
  onViewTrends: (c: CompetencyItem) => void;
}

/* ======================================================
   COMPONENT
====================================================== */
export default function CompetencyAlignmentTable({
  competencies = [],
  onViewJobs,
  onViewTrends,
}: Props) {
  /* =========================
     EMPTY STATE
  ========================= */
  if (!Array.isArray(competencies) || competencies.length === 0) {
    return (
      <div className="rounded-xl border bg-white p-6 text-center text-sm text-slate-500 dark:bg-[#0F2A3A] dark:text-slate-400">
        <p className="font-semibold text-slate-700 dark:text-slate-200">
          No hay competencias para mostrar
        </p>
        <p className="mt-1">
          Selecciona una carrera para ver el detalle de alineación por
          competencia.
        </p>
      </div>
    );
  }

  /* =========================
     TABLE
  ========================= */
  return (
    <div className="rounded-xl border bg-white dark:bg-[#0F2A3A]">
      <table className="w-full text-sm border-collapse">
        <thead>
          <tr className="border-b text-left text-slate-500 dark:text-slate-400">
            <th className="p-3">Competencia</th>
            <th className="p-3 text-center">Mercado</th>
            <th className="p-3 text-center">Prospectiva</th>
            <th className="p-3 text-right">Acciones</th>
          </tr>
        </thead>

        <tbody>
          {competencies.map((c) => {
            // defensivo total
            const market = c.market === true;
            const prospective = c.prospective === true;

            return (
              <tr
                key={c.id} // ✅ key único y estable
                className="border-t hover:bg-slate-50 dark:hover:bg-[#123A52]"
              >
                <td className="p-3 font-medium text-slate-800 dark:text-slate-100">
                  {c.name || "—"}
                </td>

                <td className="p-3 text-center">
                  {market ? "✔️" : "—"}
                </td>

                <td className="p-3 text-center">
                  {prospective ? "✔️" : "—"}
                </td>

                <td className="p-3 text-right space-x-3 whitespace-nowrap">
                  <button
                    type="button"
                    onClick={() => onViewJobs(c)}
                    className="inline-flex items-center gap-1 text-xs text-[#00B6E8] hover:underline"
                  >
                    <Briefcase className="h-3 w-3" />
                    Empleos
                  </button>

                  <button
                    type="button"
                    onClick={() => onViewTrends(c)}
                    className="inline-flex items-center gap-1 text-xs text-purple-600 hover:underline"
                  >
                    <Sparkles className="h-3 w-3" />
                    Tendencias
                  </button>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
