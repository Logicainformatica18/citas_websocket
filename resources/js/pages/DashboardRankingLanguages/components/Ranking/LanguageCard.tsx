import { Briefcase } from "lucide-react";
import { LanguageRanking } from "../../types/ranking";

type Props = {
  rank: number;
  data: LanguageRanking;
  onClick?: () => void;
};

/* =========================================
   Colores por ranking
========================================= */
const rankColors: Record<number, string> = {
  1: "bg-gradient-to-br from-[#F59E0B] to-[#D97706] text-white",
  2: "bg-gradient-to-br from-[#9CA3AF] to-[#6B7280] text-white",
  3: "bg-gradient-to-br from-[#CD7F32] to-[#A16207] text-white",
};

const defaultRankColor = "bg-gray-200 text-gray-600";

export default function LanguageCard({
  rank,
  data,
  onClick,
}: Props) {
  /* =========================================
     BLINDAJE DE DATOS
  ========================================= */
  const laborScore = Number(data.labor_score ?? 0);
  const totalJobs  = Number(data.total_jobs ?? 0);

  const scoreLabel =
    laborScore >= 70 ? "Alta" :
    laborScore >= 40 ? "Media" :
    "Baja";

  return (
    <div
      onClick={onClick}
      className="
        group rounded-2xl border bg-white p-6
        relative overflow-hidden transition-all duration-300
        cursor-pointer hover:shadow-xl hover:-translate-y-[2px] hover:border-[#1CBCE8]
        dark:bg-[#0F2A3A] dark:border-[#1E3A4A]
      "
    >
      {/* Barra decorativa */}
      <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-[#1CBCE8] to-[#6EE7F9]" />

      <div className="flex justify-between gap-6">
        {/* ================= LEFT ================= */}
        <div className="flex-1 space-y-2">
          {/* Rank + Nombre */}
          <div className="flex items-center gap-3">
            <span
              className={`
                flex items-center justify-center w-10 h-10 rounded-xl
                font-bold text-sm
                ${rankColors[rank] ?? defaultRankColor}
              `}
            >
              #{rank}
            </span>

            <h3 className="text-base font-semibold uppercase tracking-wide text-slate-900 dark:text-slate-100">
              {data.name}
            </h3>
          </div>

          {/* ================= SCORE ================= */}
          <div className="pt-4 space-y-3">
            {/* Score principal */}
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                Demanda laboral
              </span>

              <div className="flex items-center gap-2">
                <span className="text-lg font-bold text-[#0EA5E9]">
                  {laborScore.toFixed(1)}
                </span>
                <span className="text-[11px] font-semibold text-gray-400 uppercase">
                  Demanda {scoreLabel}
                </span>
              </div>
            </div>

            {/* Barra score */}
            <div className="h-2 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-[#22C55E] to-[#4ADE80]"
                style={{ width: `${laborScore}%` }}
              />
            </div>

            {/* Detalle laboral */}
            <div className="pt-2">
              <div className="flex items-center justify-between text-xs uppercase text-gray-500">
                <span className="flex items-center gap-1">
                  <Briefcase className="h-3 w-3" />
                  Vacantes
                </span>
                <span className="text-[11px] text-gray-400">
                  {totalJobs.toLocaleString()}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* ================= RIGHT ================= */}
        <div className="flex flex-col items-end justify-start text-right">
          <span className="text-4xl font-extrabold text-[#0EA5E9] leading-none">
            {laborScore.toFixed(1)}
          </span>
          <span className="text-xs font-semibold text-gray-500 uppercase tracking-widest mt-1">
            Score laboral
          </span>
        </div>
      </div>
    </div>
  );
}
