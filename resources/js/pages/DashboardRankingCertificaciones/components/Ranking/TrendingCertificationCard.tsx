import { TrendingUp, Briefcase, Sparkles } from "lucide-react";

type Props = {
  data: {
    id: number;
    name: string;
    topic_category: string;

    trend_score: number;
    job_offers: number;
    final_score: number;

    is_emergent_with_market?: boolean;
  };
  onClick?: () => void;
};

export default function TrendingCertificationCard({ data, onClick }: Props) {
  /* =========================================
     BLINDAJE DE DATOS (IGUAL QUE RANKING)
  ========================================= */
  const finalScore = Number(data.final_score ?? 0);
  const trendScore = Number(data.trend_score ?? 0);
  const totalJobs  = Number(data.job_offers ?? 0);
  const isEmergent = Boolean(data.is_emergent_with_market);

  /* Etiqueta semántica (MISMA LÓGICA) */
  const scoreLabel =
    finalScore >= 70 ? "Alta" :
    finalScore >= 40 ? "Media" :
    "Baja";

  /* Normalización visual mercado (logarítmica) */
  const laborScore = Math.min(Math.log(totalJobs + 1) * 20, 100);

  return (
    <div
      onClick={onClick}
      className="
        group cursor-pointer rounded-2xl border bg-white p-6
        relative overflow-hidden transition-all duration-300
        hover:shadow-xl hover:-translate-y-[2px]
        hover:border-[#1CBCE8]
        dark:bg-[#0F2A3A] dark:border-[#1E3A4A]
      "
    >
      {/* Barra decorativa superior */}
      <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-[#1CBCE8] to-[#6EE7F9]" />

      <div className="flex justify-between gap-6">
        {/* ================= LEFT ================= */}
        <div className="flex-1 space-y-2">
          {/* Nombre */}
          <h3 className="text-base font-semibold uppercase tracking-wide text-slate-900 dark:text-slate-100">
            {data.name}
          </h3>

          {/* Categoría */}
          <div className="flex items-center gap-2 pt-1 text-xs uppercase tracking-widest text-gray-700 dark:text-slate-300">
            <span className="w-2 h-2 rounded-full bg-[#1CBCE8]" />
            {data.topic_category}
          </div>

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

            {/* Barra resultado final */}
            <div className="h-2 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-[#1CBCE8] to-[#38BDF8]"
                style={{ width: `${finalScore}%` }}
              />
            </div>

            {/* Subscores */}
            <div className="grid grid-cols-2 gap-4 pt-2">
              {/* Mercado laboral */}
              <div>
                <div className="flex items-center justify-between text-xs uppercase text-gray-500">
                  <span className="flex items-center gap-1">
                    <Briefcase className="h-3 w-3" />
                    Mercado
                  </span>
                  <span className="text-[11px] text-gray-400">
                    {totalJobs.toLocaleString()} ofertas
                  </span>
                </div>

                <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-[#1E3A4A] overflow-hidden mt-1">
                  <div
                    className="h-full bg-[#22C55E]"
                    style={{ width: `${laborScore}%` }}
                  />
                </div>
              </div>

              {/* Tendencias IA */}
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
              </div>
            </div>
          </div>
        </div>

        {/* ================= RIGHT ================= */}
        {/* Resultado final grande */}
        <div className="flex flex-col items-end justify-start text-right">
          <span className="text-4xl font-extrabold text-[#0EA5E9] leading-none">
            {finalScore.toFixed(1)}
          </span>
          <span className="text-xs font-semibold text-gray-500 uppercase tracking-widest mt-1">
            Tendencia
          </span>
        </div>
      </div>

      {/* 🔥 Badge emergente con mercado */}
      {isEmergent && (
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
          title="Certificación emergente con mercado laboral real"
        >
          <Sparkles className="w-4 h-4" />
        </div>
      )}
    </div>
  );
}
