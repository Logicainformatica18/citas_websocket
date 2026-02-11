import { TrendingUp, Briefcase } from "lucide-react";

type Props = {
  rank: number;
  data: any;
  onAction?: (
    action: "laboral" | "trend",
    item: any
  ) => void;
};

const rankColors: Record<number, string> = {
  1: "bg-gradient-to-br from-[#F59E0B] to-[#D97706] text-white",
  2: "bg-gradient-to-br from-[#9CA3AF] to-[#6B7280] text-white",
  3: "bg-gradient-to-br from-[#CD7F32] to-[#A16207] text-white",
};

const defaultRankColor = "bg-gray-200 text-gray-600";

export default function MacroTrendCard({
  rank,
  data,
  onAction,
}: Props) {

  const finalScore = Number(data.final_score ?? 0);
  const laborScore = Number(data.labor_score ?? 0);
  const trendScore = Number(data.trend_score ?? 0);
  const totalJobs = Number(data.total_jobs ?? 0);
  const trendReports = Number(data.trend_reports ?? 0);

  const scoreLabel =
    finalScore >= 70
      ? "Alta"
      : finalScore >= 40
      ? "Media"
      : "Baja";

  return (
    <div
      className="
        group rounded-xl border bg-white p-4
        hover:border-[#1CBCE8] hover:shadow-md
        transition-all duration-300
        dark:bg-[#0F2A3A] dark:border-[#1E3A4A]
      "
    >

      {/* Barra superior decorativa */}
      <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-[#1CBCE8] to-[#38BDF8] rounded-t-xl" />

      <div className="flex justify-between gap-4">

        {/* ================= LEFT ================= */}
        <div className="flex-1 space-y-3">

          {/* Rank + Nombre */}
          <div className="flex items-start gap-2">
            <span
              className={`
                flex items-center justify-center
                w-6 h-6 rounded-md text-[10px] font-bold
                ${rankColors[rank] ?? defaultRankColor}
              `}
            >
              #{rank}
            </span>

            <h3 className="
              text-sm font-semibold
              text-slate-900 dark:text-slate-100
              leading-snug
              break-words
            ">
              {data.name}
            </h3>
          </div>

          {/* Subtítulo */}
          <div className="text-[10px] uppercase tracking-widest text-gray-500">
            Macro Tendencia Estratégica
          </div>

          {/* RESULTADO */}
          <div className="space-y-1">
            <div className="flex justify-between text-[11px]">
              <span className="text-gray-500 uppercase">
                Resultado
              </span>
              <span className="text-gray-500 font-medium">
                Proyección {scoreLabel}
              </span>
            </div>

            <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-[#1CBCE8] to-[#38BDF8]"
                style={{ width: `${finalScore}%` }}
              />
            </div>
          </div>

          {/* SUB SCORES */}
          <div className="grid grid-cols-2 gap-3 pt-2 text-[10px]">

            {/* Laboral */}
            <div
              onClick={() =>
                totalJobs > 0 && onAction?.("laboral", data)
              }
              className={`
                space-y-1 transition
                ${totalJobs > 0
                  ? "cursor-pointer hover:opacity-80"
                  : "opacity-40 cursor-not-allowed"}
              `}
            >
              <div className="flex justify-between text-gray-500">
                <span className="flex items-center gap-1">
                  <Briefcase className="h-3 w-3" />
                  Laboral
                </span>
                <span>{totalJobs}</span>
              </div>

              <div className="h-1 w-full bg-gray-200 dark:bg-[#1E3A4A] rounded-full overflow-hidden">
                <div
                  className="h-full bg-[#22C55E]"
                  style={{ width: `${laborScore}%` }}
                />
              </div>
            </div>

            {/* Tendencias */}
            <div
              onClick={() =>
                trendReports > 0 && onAction?.("trend", data)
              }
              className={`
                space-y-1 transition
                ${trendReports > 0
                  ? "cursor-pointer hover:opacity-80"
                  : "opacity-40 cursor-not-allowed"}
              `}
            >
              <div className="flex justify-between text-gray-500">
                <span className="flex items-center gap-1">
                  <TrendingUp className="h-3 w-3" />
                  Tendencias
                </span>
                <span>{trendReports}</span>
              </div>

              <div className="h-1 w-full bg-gray-200 dark:bg-[#1E3A4A] rounded-full overflow-hidden">
                <div
                  className="h-full bg-[#A855F7]"
                  style={{ width: `${trendScore}%` }}
                />
              </div>
            </div>

          </div>
        </div>

        {/* ================= RIGHT SCORE ================= */}
        <div className="text-right flex flex-col items-end">
          <span className="text-2xl font-bold text-[#0EA5E9] leading-none">
            {finalScore.toFixed(1)}
          </span>
          <span className="text-[10px] uppercase tracking-widest text-gray-500">
            Final
          </span>
        </div>
      </div>

      {/* BADGE */}
      <div className="pt-3 flex justify-end">
        <span className="
          text-[9px] px-2 py-0.5
          rounded-full
          bg-sky-100 text-sky-700
          dark:bg-[#14384F] dark:text-[#7DD3FC]
          uppercase font-semibold tracking-widest
        ">
          Macro
        </span>
      </div>

    </div>
  );
}
