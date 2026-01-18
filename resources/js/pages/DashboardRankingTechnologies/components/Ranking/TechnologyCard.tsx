import { CertificationRanking } from "../../types/ranking";
import {
  TrendingUp,
  Briefcase,
  Sparkles,
  GraduationCap,
} from "lucide-react";

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

export default function CertificationCard({
  rank,
  data,
  onClick,
}: Props) {
  /* =========================================
     BLINDAJE DE TIPO
  ========================================= */
  const isTrend = data.entity_type === "trend";
  const isISIL = Number(data.is_isil) === 1;

  /* =========================================
     BLINDAJE DE DATOS
  ========================================= */
  const finalScore = Number(data.final_score ?? 0);
  const laborScore = Number(data.labor_score ?? 0);
  const trendScore = Number(data.trend_score ?? 0);
  const totalJobs = Number(data.total_jobs ?? 0);
  const isEmergent = Number(data.is_emergent_with_market ?? 0) === 1;

  /* Etiqueta semántica */
  const scoreLabel =
    finalScore >= 70 ? "Alta" :
    finalScore >= 40 ? "Media" :
    "Baja";

  return (
    <div
      onClick={!isTrend ? onClick : undefined}
      className={`
        group rounded-2xl border bg-white p-6
        relative overflow-hidden transition-all duration-300
        ${
          !isTrend
            ? "cursor-pointer hover:shadow-xl hover:-translate-y-[2px]"
            : "cursor-default opacity-95"
        }
        hover:border-[#1CBCE8]
        dark:bg-[#0F2A3A] dark:border-[#1E3A4A]
      `}
    >
      {/* Barra decorativa */}
      <div
        className={`
          absolute top-0 left-0 h-1 w-full
          ${
            isTrend
              ? "bg-gradient-to-r from-[#A855F7] to-[#C084FC]"
              : "bg-gradient-to-r from-[#1CBCE8] to-[#6EE7F9]"
          }
        `}
      />

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

            {/* 🔥 BADGE ISIL */}
            {isISIL && !isTrend && (
              <span
                className="
                  ml-2 inline-flex items-center gap-1
                  rounded-full bg-[#ECFAFD] px-2 py-0.5
                  text-[10px] font-bold uppercase
                  text-[#0284C7]
                  dark:bg-[#14384F] dark:text-[#7DD3FC]
                "
              >
                <GraduationCap className="h-3 w-3" />
                ISIL
              </span>
            )}

            {/* 🔥 BADGE TREND */}
            {isTrend && (
              <span
                className="
                  ml-2 inline-flex items-center gap-1
                  rounded-full bg-purple-100 px-2 py-0.5
                  text-[10px] font-bold uppercase
                  text-purple-700
                  dark:bg-[#2A1B3D] dark:text-purple-300
                "
              >
                <TrendingUp className="h-3 w-3" />
                Tendencia
              </span>
            )}
          </div>

          {/* Vendor + Nivel */}
          {!isTrend && data.vendor && (
            <p className="text-xs uppercase tracking-wider text-gray-600 dark:text-slate-300">
              {data.vendor} · NIVEL {data.level}
            </p>
          )}

          {/* Categoría */}
          {data.category && !isTrend && (
            <div className="flex items-center gap-2 pt-1 text-xs uppercase tracking-widest text-gray-700 dark:text-slate-300">
              <span className="w-2 h-2 rounded-full bg-[#1CBCE8]" />
              {data.category}
            </div>
          )}

          {/* ================= SCORES ================= */}
          <div className="pt-4 space-y-3">
            {/* Resultado ponderado */}
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                Resultado ponderado
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

            {/* Barra score */}
            <div className="h-2 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-[#1CBCE8] to-[#38BDF8]"
                style={{ width: `${Math.min(finalScore, 100)}%` }}
              />
            </div>

            {/* Subscores */}
            <div className="grid grid-cols-2 gap-4 pt-2">
              {/* Laboral */}
              <div>
                <div className="flex items-center justify-between text-xs uppercase text-gray-500">
                  <span className="flex items-center gap-1">
                    <Briefcase className="h-3 w-3" />
                    Laboral
                  </span>

                  {!isTrend && (
                    <span className="text-[11px] text-gray-400">
                      {totalJobs.toLocaleString()} vacantes
                    </span>
                  )}
                </div>

                <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden mt-1">
                  <div
                    className="h-full bg-[#22C55E]"
                    style={{ width: `${laborScore}%` }}
                  />
                </div>

                <span className="text-[11px] text-gray-500">
                  Score laboral: {laborScore.toFixed(1)}
                </span>
              </div>

              {/* Tendencias */}
              <div>
                <div className="flex items-center gap-1 text-xs uppercase text-gray-500">
                  <TrendingUp className="h-3 w-3" />
                  Tendencias
                </div>

                <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden mt-1">
                  <div
                    className="h-full bg-[#A855F7]"
                    style={{ width: `${trendScore}%` }}
                  />
                </div>

                <span className="text-[11px] text-gray-500">
                  Score tendencias: {trendScore.toFixed(1)}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* ================= RIGHT ================= */}
        <div className="flex flex-col items-end justify-start text-right">
          <span className="text-4xl font-extrabold text-[#0EA5E9] leading-none">
            {finalScore.toFixed(1)}
          </span>
          <span className="text-xs font-semibold text-gray-500 uppercase tracking-widest mt-1">
            Resultado final
          </span>
        </div>
      </div>
 {/* BADGE ISIL (solo certificaciones, abajo) */}
{!isTrend && (
  <div className="pt-4 flex justify-end">
    <span
      className="
        inline-flex items-center
        rounded-full px-3 py-1
        text-[11px] font-semibold uppercase tracking-widest
        bg-sky-100 text-sky-700
        dark:bg-[#14384F]
        dark:text-[#7DD3FC]
      "
    >
      ISIL
    </span>
  </div>
)}
       
    </div>
  );
}
