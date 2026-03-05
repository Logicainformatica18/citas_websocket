import { TechnologyRanking } from "../../types/technologyRanking";

import {
    TrendingUp,
    Briefcase,
    GraduationCap,
} from "lucide-react";

type Props = {
    rank: number;
    data: TechnologyRanking;
    onAction?: (
        action: "laboral" | "trend",
        item: TechnologyRanking
    ) => void;
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

export default function TechnologyCard({
    rank,
    data,
    onAction,
}: Props) {

    /* =========================================
       CONTEXTO DEL ITEM
    ========================================= */
    const isTechnology = true; // 🔥 en este ranking siempre son tecnologías
    const classification = data.classification as
        | "isil"
        | "trend"
        | "isil+trend"
        | "market";

    const isISIL = classification === "isil" || classification === "isil+trend";
    const isTrend = classification === "trend" || classification === "isil+trend";


    /* =========================================
       BLINDAJE DE DATOS
    ========================================= */
    const finalScore = Number(data.final_score ?? 0);
    const laborScore = Number(data.labor_score ?? 0);
    const trendScore = Number(data.trend_score ?? 0);
    const totalJobs = Number(data.total_jobs ?? 0);
    const trendReports = Number((data as any).trend_reports ?? 0);
    const hasTrendData = trendReports > 0;

    /* =========================================
       TENDENCIAS (REGLA ÚNICA)
    ========================================= */


    /* =========================================
       Etiqueta semántica
    ========================================= */
    const scoreLabel =
        finalScore >= 70 ? "Alta" :
            finalScore >= 40 ? "Media" :
                "Baja";

    return (
        <div
            className={`
        group rounded-2xl border bg-white p-6
        relative overflow-hidden transition-all duration-300
       ${isTechnology
  ? `cursor-pointer hover:shadow-xl hover:-translate-y-[2px] ${
      isISIL ? "hover:border-[#1CBCE8]" : "hover:border-purple-500"
    }`
  : ""
}
        dark:bg-[#0F2A3A] dark:border-[#1E3A4A]
      `}
        >
            {/* Barra decorativa */}
          <div
  className={`
    absolute top-0 left-0 h-1 w-full
    ${
      isISIL
        ? "bg-gradient-to-r from-[#1CBCE8] to-[#6EE7F9]"
        : "bg-gradient-to-r from-purple-500 to-fuchsia-500"
    }
  `}
/>

            <div className="flex justify-between gap-3">
                {/* ================= LEFT ================= */}
                <div className="flex-1 space-y-4">
                    {/* ================= HEADER ================= */}
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

                        {/* BADGE ISIL */}




                    </div>

                    {/* ================= SUBTÍTULO ================= */}
                    <p className="text-sm text-slate-500">
                        {data.category ?? "Tecnología"}
                    </p>

                    {/* ================= CATEGORÍA ================= */}
                    {data.category && (
                        <div className="flex items-center gap-2 pt-1 text-xs uppercase tracking-widest text-gray-700 dark:text-slate-300">
                            <span className="w-2 h-2 rounded-full bg-[#1CBCE8]" />
                            {data.category}
                        </div>
                    )}

                    {/* ================= RESULTADO PONDERADO ================= */}
                    <div className="pt-6 space-y-3">
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

                        {/* Barra */}
                        <div className="h-2 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden">
                            <div
                                className="h-full bg-gradient-to-r from-[#1CBCE8] to-[#38BDF8]"
                                style={{ width: `${Math.min(finalScore, 100)}%` }}
                            />
                        </div>

                        {/* ================= BLOQUES ================= */}
                        <div className="grid grid-cols-2 gap-4 pt-2">
                            {/* ================= LABORAL ================= */}
                            <div
                                onClick={(e) => {
                                    e.stopPropagation();
                                    if (totalJobs === 0) return;
                                    onAction?.("laboral", data);
                                }}
                                className={`
                  rounded-lg p-2 transition
                  ${totalJobs > 0
                                        ? "cursor-pointer hover:bg-slate-50 dark:hover:bg-[#1E3A4A]"
                                        : "opacity-40 cursor-not-allowed"
                                    }
                `}
                            >
                                <div className="flex items-center justify-between text-xs uppercase text-gray-500">
                                    <span className="flex items-center gap-1">
                                        <Briefcase className="h-3 w-3" />
                                        Laboral
                                    </span>

                                    <span className="text-[11px] text-gray-400">
                                        {totalJobs.toLocaleString()} vacante{totalJobs !== 1 ? "s" : ""}
                                    </span>
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

                            {/* ================= TENDENCIAS ================= */}
                            <div
                                onClick={(e) => {
                                    e.stopPropagation();
                                    if (!hasTrendData) return;
                                    onAction?.("trend", data);
                                }}
                                className={`
    rounded-lg p-2 transition
    ${hasTrendData
                                        ? "cursor-pointer hover:bg-slate-50 dark:hover:bg-[#1E3A4A]"
                                        : "opacity-40 cursor-not-allowed"
                                    }
  `}
                            >

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

                                <span className="text-[11px] text-gray-500 flex justify-between">
                                    <span>Score: {trendScore.toFixed(1)}</span>
                                    {hasTrendData && (
                                        <span className="text-gray-400">
                                            {trendReports} reporte{trendReports !== 1 ? "s" : ""}
                                        </span>
                                    )}


                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ================= RIGHT ================= */}
             <div className="flex flex-col items-end justify-between text-right">

  <div>
    <span className="text-4xl font-extrabold text-[#0EA5E9] leading-none">
      {finalScore.toFixed(1)}
    </span>
    <span className="block text-xs font-semibold text-gray-500 uppercase tracking-widest mt-1">
      Resultado final
    </span>
  </div>

  {/* BADGES */}
  <div className="flex gap-2 mt-4">

    {isISIL && (
      <span
        className="
          inline-flex items-center gap-1
          rounded-full px-3 py-1
          text-xs font-semibold
          bg-sky-100 text-sky-700
          dark:bg-[#14384F]
          dark:text-[#7DD3FC]
        "
      >
        <GraduationCap className="w-3 h-3" />
        ISIL
      </span>
    )}

    {!isISIL && (
      <span
        className="
          inline-flex items-center gap-1
          rounded-full px-3 py-1
          text-xs font-semibold
          bg-purple-100 text-purple-700
          dark:bg-[#2B1C3A]
          dark:text-[#E9D5FF]
        "
      >
        <TrendingUp className="w-3 h-3" />
        Trend
      </span>
    )}

  </div>

</div>
            </div>



        </div>
    );
}
