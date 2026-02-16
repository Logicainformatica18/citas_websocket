import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

interface Props {
  competency: any;
  onAnalyze: (c: { id: number; name: string }) => void;
}

export default function CompetencyCard({
  competency,
  onAnalyze,
}: Props) {
  const statusConfig = {
    aligned: {
      label: "Alineada",
      class:
        "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400",
    },
    partial: {
      label: "Parcial",
      class:
        "bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400",
    },
    gap: {
      label: "Brecha",
      class:
        "bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400",
    },
  };

  const config =
    statusConfig[competency.status] ?? statusConfig.gap;

  return (
    <div className="rounded-2xl border bg-white dark:bg-card p-6 shadow-sm hover:shadow-md transition space-y-5">
      {/* HEADER */}
      <div className="flex items-start justify-between gap-4">
        <h4 className="font-semibold text-base leading-snug">
          {competency.name}
        </h4>

        <Badge className={config.class}>
          {config.label}
        </Badge>
      </div>

      {/* MÉTRICAS */}
      <div className="grid grid-cols-2 gap-6 text-sm">
        <div>
          <p className="text-muted-foreground">
            Vacantes asociadas
          </p>
          <p className="text-xl font-semibold text-[#1CBCE8]">
            {competency.job_count}
          </p>
        </div>

        <div>
          <p className="text-muted-foreground">
            Tendencias estratégicas
          </p>
          <p className="text-xl font-semibold text-[#1CBCE8]">
            {competency.trend_count}
          </p>
        </div>
      </div>

      {/* ACCIONES */}
      <div className="flex gap-3">
        <Button
          variant="outline"
          className="flex-1"
          disabled={competency.job_count === 0}
        >
          Ver Vacantes
        </Button>

        <Button
          variant="outline"
          className="flex-1"
          disabled={competency.trend_count === 0}
        >
          Ver Tendencias
        </Button>
      </div>

      {/* RECOMENDACIÓN (solo si GAP o PARCIAL) */}
      {competency.alignment_recommendation &&
        competency.status !== "aligned" && (
          <div className="text-xs bg-muted p-3 rounded-lg">
            {competency.alignment_recommendation}
          </div>
        )}
    </div>
  );
}
