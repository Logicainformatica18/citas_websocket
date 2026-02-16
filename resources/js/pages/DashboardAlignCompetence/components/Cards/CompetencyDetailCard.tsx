import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Sparkles, Briefcase, TrendingUp } from "lucide-react";

interface Props {
  competency: any;
  onAnalyze: (c: { id: number; name: string }) => void;
}

export default function CompetencyDetailCard({
  competency,
  onAnalyze,
}: Props) {
  /* ===============================
     SEMÁFORO MEJORADO
  =============================== */
  const statusConfig: Record<string, any> = {
    aligned: {
      label: "ALINEADA",
      bg: "bg-emerald-100 dark:bg-emerald-900/30",
      text: "text-emerald-700 dark:text-emerald-300",
    },
    partial: {
      label: "PARCIAL",
      bg: "bg-amber-100 dark:bg-amber-900/30",
      text: "text-amber-700 dark:text-amber-300",
    },
    gap: {
      label: "BRECHA",
      bg: "bg-rose-100 dark:bg-rose-900/30",
      text: "text-rose-700 dark:text-rose-300",
    },
  };

  const status = statusConfig[competency.status ?? "gap"];

  return (
    <div
      className="
        rounded-2xl border
        border-[#E6F0FA] dark:border-[#1F2937]
        bg-white dark:bg-[#0F172A]
        shadow-sm hover:shadow-md
        transition-all duration-200
        p-6 space-y-5
      "
    >
      {/* ================= HEADER ================= */}
      <div className="flex items-start justify-between gap-4">
        <h3 className="font-semibold text-base text-[#0A2540] dark:text-white leading-snug">
          {competency.name}
        </h3>

        <span
          className={`px-3 py-1 text-xs font-semibold rounded-full ${status.bg} ${status.text}`}
        >
          {status.label}
        </span>
      </div>

      {/* ================= MÉTRICAS ================= */}
      <div className="grid grid-cols-2 gap-6 text-sm">
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-muted-foreground dark:text-gray-400">
            <Briefcase size={15} />
            Vacantes
          </div>
          <div className="text-xl font-semibold text-[#1CBCE8]">
            {competency.job_count ?? 0}
          </div>
        </div>

        <div className="space-y-1">
          <div className="flex items-center gap-2 text-muted-foreground dark:text-gray-400">
            <TrendingUp size={15} />
            Tendencias
          </div>
          <div className="text-xl font-semibold text-[#00B6E8]">
            {competency.trend_count ?? 0}
          </div>
        </div>
      </div>

      {/* ================= ENTIDADES ================= */}
      {competency.entities?.length > 0 && (
        <div>
          <p className="text-xs text-muted-foreground mb-2">
            Entidades relacionadas
          </p>
          <div className="flex flex-wrap gap-2">
            {competency.entities.slice(0, 6).map((e: any) => (
              <span
                key={e.id}
                className="
                  text-xs px-3 py-1 rounded-full
                  bg-[#E6F7FD] text-[#0A2540]
                  dark:bg-[#1E293B] dark:text-gray-300
                "
              >
                {e.name}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* ================= TENDENCIAS ================= */}
      {competency.recent_trends?.length > 0 && (
        <div>
          <p className="text-xs text-muted-foreground mb-2">
            Tendencias recientes
          </p>
          <ul className="text-xs space-y-1 text-[#0A2540] dark:text-gray-300">
            {competency.recent_trends.slice(0, 3).map((t: any, i: number) => (
              <li key={i}>
                • {t.trend_name} ({t.trend_score})
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* ================= RECOMENDACIÓN IA ================= */}
      {competency.alignment_recommendation && (
        <div
          className="
            text-xs p-3 rounded-xl
            bg-[#F0FAFF] text-[#0A2540]
            dark:bg-[#1E293B] dark:text-gray-300
          "
        >
          {competency.alignment_recommendation}
        </div>
      )}

      {/* ================= BOTÓN IA ================= */}
      <Button
        size="sm"
        className="
          w-full
          bg-[#1CBCE8] hover:bg-[#0EA5C6]
          text-white
          transition
        "
        onClick={() =>
          onAnalyze({ id: competency.id, name: competency.name })
        }
      >
        <Sparkles className="w-4 h-4 mr-2" />
        Analizar con IA
      </Button>
    </div>
  );
}
