import {
  Home,
  Laptop,
  Users,
} from "lucide-react";

interface ModalityItem {
  modalidad: "remoto" | "híbrido" | "presencial";
  vacantes: number;
  porcentaje: number;
}

interface ModalityKpiGridProps {
  data: ModalityItem[];
}

export default function ModalityKpiGrid({
  data,
}: ModalityKpiGridProps) {

  const getValue = (key: ModalityItem["modalidad"]) =>
    data.find((d) => d.modalidad === key)?.porcentaje ?? 0;

  const kpis = [
    {
      key: "remoto",
      label: "Trabajo remoto",
      value: `${getValue("remoto").toFixed(1)}%`,
      icon: Laptop,
      color: "text-[#00B6E8]",
      bg: "bg-[#E6F7FD] dark:bg-[#0F2A3A]",
    },
    {
      key: "híbrido",
      label: "Modalidad híbrida",
      value: `${getValue("híbrido").toFixed(1)}%`,
      icon: Users,
      color: "text-emerald-500",
      bg: "bg-emerald-50 dark:bg-emerald-900/20",
    },
    {
      key: "presencial",
      label: "Trabajo presencial",
      value: `${getValue("presencial").toFixed(1)}%`,
      icon: Home,
      color: "text-orange-500",
      bg: "bg-orange-50 dark:bg-orange-900/20",
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {kpis.map((kpi) => (
        <div
          key={kpi.key}
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
