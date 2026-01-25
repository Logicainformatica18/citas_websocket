import { MapPin, Layers, Building2, BarChart3 } from "lucide-react";

interface CityDemandKpiGridProps {
  meta: {
    total_jobs: number;
    cities_count: number;
    top_city?: string;
    top5_concentration: number;
  };
}

export default function CityDemandKpiGrid({
  meta,
}: CityDemandKpiGridProps) {
  const kpis = [
    {
      label: "Ciudad líder",
      value: meta.top_city ?? "—",
      icon: MapPin,
      color: "text-[#00B6E8]",
      bg: "bg-[#E6F7FD] dark:bg-[#0F2A3A]",
    },
    {
      label: "Concentración Top 5",
      value: `${meta.top5_concentration.toFixed(1)}%`,
      icon: Layers,
      color: "text-indigo-500",
      bg: "bg-indigo-50 dark:bg-indigo-900/20",
    },
    {
      label: "Ciudades activas",
      value: meta.cities_count.toLocaleString(),
      icon: Building2,
      color: "text-emerald-500",
      bg: "bg-emerald-50 dark:bg-emerald-900/20",
    },
    {
      label: "Vacantes analizadas",
      value: meta.total_jobs.toLocaleString(),
      icon: BarChart3,
      color: "text-slate-700 dark:text-slate-200",
      bg: "bg-slate-50 dark:bg-slate-800",
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {kpis.map((kpi) => (
        <div
          key={kpi.label}
          className={`
            relative
            overflow-hidden
            rounded-2xl
            border
            border-slate-200
            dark:border-slate-700
            ${kpi.bg}
            p-5
            shadow-sm
            transition-all
            hover:shadow-md
            hover:-translate-y-0.5
          `}
        >
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {kpi.label}
              </p>
              <p className="mt-1 text-2xl font-extrabold text-slate-900 dark:text-slate-100">
                {kpi.value}
              </p>
            </div>

            <div
              className={`
                flex h-11 w-11 items-center justify-center
                rounded-xl
                bg-white/70
                dark:bg-slate-900/40
                ${kpi.color}
              `}
            >
              <kpi.icon className="h-5 w-5" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
