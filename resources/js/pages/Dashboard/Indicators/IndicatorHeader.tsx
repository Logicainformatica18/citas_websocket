import { Badge } from "@/components/ui/badge";
import {
  BarChart3,
  Database,
  Calendar,
  Globe,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";

interface IndicatorHeaderProps {
  meta: {
    total_vacantes: number;
    date_from?: string;
    date_to?: string;
  };
}

export function IndicatorHeader({ meta }: IndicatorHeaderProps) {
  const { filters } = usePage().props as any;

  const onChange = (params: Record<string, any>) => {
    router.get(
      "/dashboard/indicators/job-modality",
      {
        ...filters,
        ...params,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  return (
    <header
      className="
        relative
        overflow-hidden
        border-b
        bg-[#E6F7FD]
        dark:bg-[#0A2540]
        px-4 sm:px-6 lg:px-8
      "
    >
      {/* ===== BACKGROUND ===== */}
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-[#00B6E8]/30 blur-3xl" />
        <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-[#1CBCE8]/20 blur-3xl" />
      </div>

      {/* ===== CONTENT ===== */}
      <div className="relative mx-auto max-w-7xl py-10 md:py-14">
        <div className="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">

          {/* ================= LEFT ================= */}
          <div className="space-y-6 max-w-3xl">
            {/* Title */}
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#00B6E8] shadow-lg">
                <BarChart3 className="h-6 w-6 text-white" />
              </div>

              <div>
                <p className="text-sm font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                  Observatorio Tecnológico ISIL
                </p>

                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                  Modalidad laboral de las vacantes
                </h1>
              </div>
            </div>

            {/* Description */}
            <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
              Distribución porcentual de las ofertas laborales según modalidad
              de trabajo: remoto, híbrido y presencial, basada exclusivamente
              en datos de portales de empleo.
            </p>

            {/* ===== BADGES ===== */}
            <div className="flex flex-wrap items-center gap-3 pt-2">
              <Badge className="gap-1.5 bg-white text-[#0A2540] shadow hover:shadow-md transition">
                <Database className="h-3 w-3 text-[#00B6E8]" />
                {meta.total_vacantes.toLocaleString()} vacantes analizadas
              </Badge>

              {(meta.date_from || meta.date_to) && (
                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow hover:shadow-md transition">
                  <Calendar className="h-3 w-3 text-[#00B6E8]" />
                  {meta.date_from ?? "—"} → {meta.date_to ?? "—"}
                </Badge>
              )}

              {filters?.country && (
                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow hover:shadow-md transition">
                  <Globe className="h-3 w-3 text-[#00B6E8]" />
                  {filters.country}
                </Badge>
              )}
            </div>

            {/* ===== METODOLOGÍA ===== */}
            <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
              <p className="text-sm text-slate-600 dark:text-slate-400">
                <span className="font-semibold text-slate-800 dark:text-slate-200">
                  Metodología:
                </span>{" "}
                % modalidad = (Vacantes por modalidad ÷ Total de vacantes) × 100.
                Fuente única: portales de empleo (100%).
              </p>
            </div>
          </div>

          {/* ================= RIGHT ================= */}
          <div
            className="
              w-full
              max-w-sm
              rounded-2xl
              border border-[#00B6E8]/40
              bg-white
              p-5
              text-left
              shadow-xl
              dark:bg-[#102C3C]
            "
          >
            <p className="text-xs font-bold uppercase tracking-wider text-[#00B6E8] mb-2">
              Alcance del indicador
            </p>

            <ul className="space-y-2 text-sm text-[#0A2540] dark:text-gray-300">
              <li>• Clasificación automática por modalidad</li>
              <li>• Conteo de vacantes por tipo</li>
              <li>• Cálculo porcentual sobre el total</li>
            </ul>

            <p className="mt-4 border-t border-[#00B6E8]/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
              Este indicador es descriptivo y no aplica ponderaciones ni rankings.
            </p>
          </div>
        </div>
      </div>
    </header>
  );
}
