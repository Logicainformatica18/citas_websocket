import { CertificationRanking } from "../../types/ranking";
import { TrendingUp, Briefcase, Sparkles } from "lucide-react";

type Props = {
    rank: number;
    data: CertificationRanking;
    variant?: "certification" | "trend";
    onAction?: (
        action: "laboral" | "trend",
        item: CertificationRanking
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

export default function CertificationCard({
    rank,
    data,
    onAction,
    variant = "certification",
}: Props) {

    /* =========================================
       TIPO DE ITEM (FUENTE ÚNICA)
    ========================================= */
    const isTrend = data.entity_type === "trend";


    /* =========================================
       BLINDAJE DE DATOS
    ========================================= */
 const hasTrend =
  data.trend_reports !== null &&
  data.trend_reports !== undefined &&
  Number(data.trend_reports) > 0;


    const trendReports = Number(data.trend_reports ?? 0);
    const totalJobs = Number(data.total_jobs ?? 0);
    const isEmergent = Boolean(data.is_emergent_with_market);
    const finalScore = Number(data.final_score ?? 0);
    const laborScore = Number(data.labor_score ?? 0);
    const trendScore = Number(data.trend_score ?? 0);
    const laborWeightedScore = Number(data.labor_weighted_score ?? 0);

    /* Etiqueta semántica */
    const scoreLabel =
        finalScore >= 70 ? "Alta" :
            finalScore >= 40 ? "Media" :
                "Baja";

    return (
        <div
            className={`
    group rounded-2xl border bg-white p-6
    relative overflow-hidden transition-all duration-300
    ${!isTrend ? "hover:shadow-xl hover:-translate-y-[2px] hover:border-[#1CBCE8]" : "opacity-95"}
    dark:bg-[#0F2A3A] dark:border-[#1E3A4A]
  `}
        >

            {/* Barra decorativa */}
            <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-[#1CBCE8] to-[#6EE7F9]" />

            {/* BADGE TIPO (CLAVE PARA VER QUE SE JUNTAN) */}


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

                    {/* Vendor + Nivel (solo si existe) */}
                    {!isTrend && data.vendor && (
                        <p className="text-xs uppercase tracking-wider text-gray-600 dark:text-slate-300">
                            {data.vendor} · NIVEL {data.level}
                        </p>
                    )}

                    {/* Categoría (si existe) */}
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

                        {/* Barra Score Final */}
                        <div className="h-2 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden">
                            <div
                                className="h-full bg-gradient-to-r from-[#1CBCE8] to-[#38BDF8]"
                                style={{ width: `${finalScore}%` }}
                            />
                        </div>

                        {/* Subscores */}
                        <div className="grid grid-cols-2 gap-4 pt-2">
                            {/* Laboral */}
                            {/* Laboral */}
                            <div
                                onClick={(e) => {
                                    e.stopPropagation();
                                    console.log("CLICK LABORAL", data.id);
                                    if (totalJobs === 0) return;
onAction?.("laboral", data);

                                }}

                                className={`
    rounded-lg p-2 transition
    ${totalJobs === 0
  ? "opacity-40 cursor-not-allowed"
  : "cursor-pointer hover:bg-slate-50 dark:hover:bg-[#1E3A4A]"
}

  `}
                            >

                                <div className="flex items-center justify-between text-xs uppercase text-gray-500">
                                    <span className="flex items-center gap-1">
                                        <Briefcase className="h-3 w-3" />
                                        Laboral
                                    </span>

                                  {totalJobs > 0 && (
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
                           <div
  onClick={(e) => {
    e.stopPropagation();
    console.log("CLICK TENDENCIA CARD", data.id, data.entity_type);

    if (!hasTrend) return;

    onAction?.("trend", data);
  }}
  title={!hasTrend ? "No hay reportes de tendencia" : "Ver reportes de tendencia"}
  className={`
    rounded-lg p-2 transition
    ${hasTrend
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

                                <div className="flex items-center justify-between text-[11px] text-gray-500">
                                    <span>
                                        Score tendencias: {trendScore.toFixed(1)}
                                    </span>

                                    {trendReports > 0 && (
                                        <span className="text-gray-400">
                                            {trendReports} reporte{trendReports !== 1 ? "s" : ""}
                                        </span>
                                    )}
                                </div>

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

            {/* BADGE EMERGENTE (solo certificaciones) */}
            {!isTrend && isEmergent && (
                <div
                    className="
            absolute bottom-3 right-3
            flex items-center justify-center
            w-9 h-9 rounded-full
            bg-purple-100 text-purple-700
            shadow-sm
            ring-1 ring-purple-300
            group-hover:scale-110 transition
            dark:bg-purple-900/30 dark:text-purple-300
          "
                    title="Certificación emergente con mercado (detectada por tendencias)"
                >
                    <Sparkles className="w-4 h-4" />
                </div>
            )}
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
