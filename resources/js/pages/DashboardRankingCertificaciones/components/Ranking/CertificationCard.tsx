import { CertificationRanking } from "../../types/ranking";
import { TrendingUp, Briefcase } from "lucide-react";

type Props = {
  rank: number;
  data: CertificationRanking;
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

export default function CertificationCard({ rank, data, onClick }: Props) {
  /* =========================================
     BLINDAJE DE DATOS (MUY IMPORTANTE)
  ========================================= */
  const finalScore = Number(data.final_score ?? 0);
  const laborScore = Number(data.labor_score ?? 0);
  const trendScore = Number(data.trend_score ?? 0);
  const totalJobs  = Number(data.total_jobs ?? 0);

  /* Etiqueta semántica opcional */
  const scoreLabel =
    finalScore >= 70 ? "Alta" :
    finalScore >= 40 ? "Media" :
    "Baja";

  return (
    <div
      onClick={onClick}
      className="
        group
        cursor-pointer
        rounded-2xl
        border
        bg-white
        p-6
        relative
        overflow-hidden
        transition-all
        duration-300
        hover:shadow-xl
        hover:-translate-y-[2px]
        hover:border-[#1CBCE8]
        dark:bg-[#0F2A3A]
        dark:border-[#1E3A4A]
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
                flex items-center justify-center
                w-10 h-10
                rounded-xl
                font-bold
                text-sm
                ${rankColors[rank] ?? defaultRankColor}
              `}
            >
              #{rank}
            </span>

            <h3 className="text-base font-semibold uppercase tracking-wide text-slate-900 dark:text-slate-100">
              {data.name}
            </h3>
          </div>

          {/* Vendor + Nivel */}
          <p className="text-xs uppercase tracking-wider text-gray-600 dark:text-slate-300">
            {data.vendor} · NIVEL {data.level}
          </p>

          {/* Categoría */}
          {data.category && (
            <div className="flex items-center gap-2 pt-1 text-xs uppercase tracking-widest text-gray-700 dark:text-slate-300">
              <span className="w-2 h-2 rounded-full bg-[#1CBCE8]" />
              {data.category}
            </div>
          )}

          {/* ================= SCORES ================= */}
          <div className="pt-4 space-y-3">
            {/* Score Final */}
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                Score final
              </span>

              <div className="flex items-center gap-2">
                <span className="text-lg font-bold text-[#0EA5E9]">
                  {finalScore.toFixed(1)}
                </span>
                <span className="text-[11px] font-semibold text-gray-400 uppercase">
                  Proyección {scoreLabel}
                </span>
              </div>
            </div>

            {/* Barra Score Final */}
            <div className="h-2 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-[#1CBCE8] to-[#38BDF8] transition-all duration-500"
                style={{ width: `${finalScore}%` }}
              />
            </div>

            {/* Subscores */}
            <div className="grid grid-cols-2 gap-4 pt-2">
              {/* Laboral */}
              <div>
                <div className="flex items-center gap-1 text-xs text-gray-500 uppercase">
                  <Briefcase className="h-3 w-3" />
                  Laboral
                </div>
                <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden mt-1">
                  <div
                    className="h-full bg-[#22C55E] transition-all duration-500"
                    style={{ width: `${laborScore}%` }}
                  />
                </div>
                <span className="text-[11px] text-gray-500">
                  {laborScore.toFixed(1)}
                </span>
              </div>

              {/* Tendencias */}
              <div>
                <div className="flex items-center gap-1 text-xs text-gray-500 uppercase">
                  <TrendingUp className="h-3 w-3" />
                  Tendencias
                </div>
                <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden mt-1">
                  <div
                    className="h-full bg-[#A855F7] transition-all duration-500"
                    style={{ width: `${trendScore}%` }}
                  />
                </div>
                <span className="text-[11px] text-gray-500">
                  {trendScore.toFixed(1)}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* ================= RIGHT ================= */}
        <div className="flex flex-col items-end justify-start">
          <span className="text-4xl font-extrabold text-[#0EA5E9] leading-none">
            {totalJobs}
          </span>
          <span className="text-xs font-semibold text-gray-500 uppercase tracking-widest mt-1">
            Vacantes
          </span>
        </div>
      </div>
    </div>
  );
}
