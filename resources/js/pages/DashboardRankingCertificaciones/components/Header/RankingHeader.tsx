import { Badge } from "@/components/ui/badge";
import {
  BarChart3,
  Database,
  FileText,
  Sparkles,
  Settings2,
} from "lucide-react";
import {
  PeriodSelector,
  Period,
  getPeriodDisplayText,
  periodVacancyCounts,
} from "./PeriodSelector";
import { WeightConfig } from "./WeightConfigModal";

interface HeaderProps {
  period: Period;
  onPeriodChange: (period: Period) => void;
  weights: WeightConfig;
  onEditWeights: () => void;
}

export function Header({
  period,
  onPeriodChange,
  weights,
  onEditWeights,
}: HeaderProps) {
  const vacancyCount = periodVacancyCounts[period];
  const periodText = getPeriodDisplayText(period);

  return (
     <header
      className="
        relative
        overflow-hidden
        border-b
        bg-[#E6F7FD]
        dark:bg-[#0A2540]
        px-4 sm:px-6 lg:px-8   /* 👈 PADDING LATERAL */
      "
    >
      {/* ===== ISIL BACKGROUND ===== */}
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-[#00B6E8]/30 blur-3xl" />
        <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-[#1CBCE8]/20 blur-3xl" />
      </div>

      {/* ===== CONTENIDO ===== */}
      <div className="relative mx-auto max-w-7xl py-10 md:py-14">
        <div className="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">

          {/* ================= LEFT ================= */}
          <div className="space-y-5 max-w-3xl">
            {/* Title */}
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#00B6E8] shadow-lg">
                <BarChart3 className="h-6 w-6 text-white" />
              </div>

              <div>
                <p className="text-sm font-semibold text-[#005F7A] dark:text-[#1CBCE8]">
                  Observatorio Tecnológico ISIL
                </p>
                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-white">
                  Ranking de Certificaciones
                </h1>
              </div>
            </div>

            {/* Description */}
            <p className="text-[15px] leading-relaxed text-[#0A2540]/80 dark:text-gray-300">
              Análisis automatizado de las certificaciones tecnológicas más
              demandadas, basado en datos reales del mercado laboral y tendencias
              globales.
            </p>

            {/* Period + badges */}
            <div className="flex flex-wrap items-center gap-3">
              <PeriodSelector value={period} onChange={onPeriodChange} />

              <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                <Database className="h-3 w-3 text-[#00B6E8]" />
                {vacancyCount.toLocaleString()}+ vacantes analizadas
              </Badge>

              <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                <FileText className="h-3 w-3 text-[#00B6E8]" />
                5 reportes internacionales
              </Badge>

              <Badge className="gap-1.5 border border-[#00B6E8] bg-[#E6F7FD] text-[#005F7A]">
                <Sparkles className="h-3 w-3 text-[#00B6E8]" />
                Actualizado automáticamente
              </Badge>
            </div>

            {/* Active period */}
            <p className="text-sm text-[#0A2540]/70 dark:text-gray-400">
              <span className="font-semibold text-[#0A2540] dark:text-white">
                Periodo activo:
              </span>{" "}
              {periodText}
            </p>
          </div>

          {/* ================= RIGHT ================= */}
          <button
            onClick={onEditWeights}
            className="
              group
              w-full
              max-w-sm
              rounded-2xl
              border border-[#00B6E8]/40
              bg-white
              p-5
              text-left
              shadow-xl
              transition-all
              hover:border-[#00B6E8]
              hover:shadow-2xl
              dark:bg-[#102C3C]
            "
          >
            <div className="mb-2 flex items-center justify-between">
              <p className="text-xs font-bold uppercase tracking-wider text-[#00B6E8]">
                Metodología de cálculo
              </p>
              <Settings2 className="h-4 w-4 text-[#00B6E8] opacity-60 group-hover:opacity-100" />
            </div>

            <div className="space-y-1 text-sm text-[#0A2540] dark:text-gray-300">
              <p>
                <span className="font-bold text-[#00B6E8]">
                  {weights.laborWeight}%
                </span>{" "}
                Demanda laboral
              </p>
              <p>
                <span className="font-bold text-[#00B6E8]">
                  {weights.trendsWeight}%
                </span>{" "}
                Tendencias tecnológicas
              </p>
            </div>

            <p className="mt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
              Score = ({weights.laborWeight / 100} × Laboral) + (
              {weights.trendsWeight / 100} × Tendencias)
            </p>

            <p className="mt-3 border-t border-[#00B6E8]/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
              Los puntajes se calculan considerando únicamente los datos del
              período seleccionado.
            </p>

            <p className="mt-3 text-xs font-semibold text-[#00B6E8] group-hover:underline">
              Haz clic para editar ponderaciones
            </p>
          </button>
        </div>
      </div>
    </header>
  );
}
