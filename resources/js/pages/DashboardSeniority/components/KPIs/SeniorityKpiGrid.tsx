import { Briefcase, TrendingUp, User } from "lucide-react";
import { CareerSeniority } from "../hooks/useSeniorityData";

type Props = {
  data: CareerSeniority[];
};

export function SeniorityKpiGrid({ data }: Props) {
  let junior = 0;
  let mid = 0;
  let senior = 0;

  data.forEach((career) => {
    career.distribution.forEach((d) => {
      if (d.seniority === "junior") junior += d.jobs;
      if (d.seniority === "mid") mid += d.jobs;
      if (d.seniority === "senior") senior += d.jobs;
    });
  });

  const total = junior + mid + senior;

  /* =====================================================
     🛑 SIN DATA CLASIFICADA
  ===================================================== */
  if (total === 0) {
    return (
      <div className="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
        No hay vacantes con seniority identificado en el Periodo seleccionado.
      </div>
    );
  }

  const kpis = [
    {
      label: "Junior",
      value: `${((junior / total) * 100).toFixed(1)}%`,
      subtitle: `${junior.toLocaleString()} vacantes`,
      icon: User,
      accent: "from-[#7DD3FC] to-[#38BDF8]",
    },
    {
      label: "Mid",
      value: `${((mid / total) * 100).toFixed(1)}%`,
      subtitle: `${mid.toLocaleString()} vacantes`,
      icon: Briefcase,
      accent: "from-[#00B6E8] to-[#1CBCE8]",
    },
    {
      label: "Senior",
      value: `${((senior / total) * 100).toFixed(1)}%`,
      subtitle: `${senior.toLocaleString()} vacantes`,
      icon: TrendingUp,
      accent: "from-[#0EA5E9] to-[#0284C7]",
    },
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      {kpis.map((kpi) => (
        <div
          key={kpi.label}
          className="
            group
            relative
            overflow-hidden
            rounded-2xl
            border
            bg-white
            p-5
            shadow-sm
            transition-all
            hover:-translate-y-1
            hover:shadow-xl
            dark:bg-[#0F2A3A]
            dark:border-[#1E3A4A]
          "
        >
          {/* 🎨 Glow decorativo */}
          <div
            className={`
              pointer-events-none
              absolute
              -right-10
              -top-10
              h-32
              w-32
              rounded-full
              bg-gradient-to-br
              ${kpi.accent}
              opacity-20
              blur-3xl
            `}
          />

          {/* Header */}
          <div className="relative z-10 flex items-center gap-4">
            <div
              className={`
                flex
                h-12
                w-12
                items-center
                justify-center
                rounded-xl
                bg-gradient-to-br
                ${kpi.accent}
                text-white
                shadow-lg
              `}
            >
              <kpi.icon className="h-6 w-6" />
            </div>

            <div>
              <p className="text-sm font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                {kpi.label}
              </p>
              <p className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                {kpi.value}
              </p>
            </div>
          </div>

          {/* Footer */}
          <div className="relative z-10 mt-3 text-sm text-slate-600 dark:text-slate-400">
            {kpi.subtitle}
          </div>
        </div>
      ))}
    </div>
  );
}
