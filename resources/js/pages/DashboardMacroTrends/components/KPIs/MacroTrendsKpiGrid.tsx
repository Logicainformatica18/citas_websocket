import { BarChart3, Briefcase, TrendingUp } from "lucide-react";

interface Props {
  meta: any;
  total: number;
}

export default function MacroTrendsKpiGrid({ meta, total }: Props) {
  const items = [
    {
      label: "Macro-tendencias detectadas",
      value: total,
      icon: TrendingUp,
    },
    {
      label: "Peso demanda laboral",
      value: "60%",
      icon: Briefcase,
    },
    {
      label: "Peso reportes",
      value: "40%",
      icon: BarChart3,
    },
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      {items.map((item, i) => (
        <div
          key={i}
          className="bg-white dark:bg-[#0F2A3A] border rounded-xl p-4 flex items-center gap-4"
        >
          <item.icon className="w-6 h-6 text-[#00B6E8]" />
          <div>
            <p className="text-sm text-slate-500">{item.label}</p>
            <p className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              {item.value}
            </p>
          </div>
        </div>
      ))}
    </div>
  );
}
