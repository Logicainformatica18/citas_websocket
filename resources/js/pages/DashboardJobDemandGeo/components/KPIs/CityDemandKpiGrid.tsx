import {
  MapPin,
  Layers,
  Building2,
  BarChart3,
  GraduationCap,
  Award,
} from "lucide-react";

interface CityDemandKpiGridProps {
  meta: {
    total_jobs?: number;
    cities_count?: number;
    careers_count?: number;
    top_city?: string | null;
    top_career?: string | null; // 🔥 NUEVO
    top5_concentration?: number;
  };
}

export default function CityDemandKpiGrid({
  meta,
}: CityDemandKpiGridProps) {

  const totalJobs = meta.total_jobs ?? 0;
  const citiesCount = meta.cities_count ?? 0;
  const careersCount = meta.careers_count ?? 0;
  const topCity = meta.top_city ?? "—";
  const topCareer = meta.top_career ?? "—"; // 🔥
  const top5 = meta.top5_concentration ?? 0;

  const kpis = [
    {
      label: "Vacantes analizadas",
      value: totalJobs.toLocaleString(),
      icon: BarChart3,
      color: "text-slate-700 dark:text-slate-200",
      bg: "bg-slate-50 dark:bg-slate-800",
    },
    {
      label: "Carrera líder",
      value: topCareer,
      icon: Award,
      color: "text-orange-500",
      bg: "bg-orange-50 dark:bg-orange-900/20",
    },
    {
      label: "Carreras activas",
      value: careersCount.toLocaleString(),
      icon: GraduationCap,
      color: "text-purple-600",
      bg: "bg-purple-50 dark:bg-purple-900/20",
    },
    {
      label: "Ciudad líder",
      value: topCity,
      icon: MapPin,
      color: "text-[#00B6E8]",
      bg: "bg-[#E6F7FD] dark:bg-[#0F2A3A]",
    },
    {
      label: "Concentración Top 5",
      value: `${top5.toFixed(1)}%`,
      icon: Layers,
      color: "text-indigo-500",
      bg: "bg-indigo-50 dark:bg-indigo-900/20",
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
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
