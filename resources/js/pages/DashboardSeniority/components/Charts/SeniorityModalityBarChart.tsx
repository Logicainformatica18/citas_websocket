import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts";

type Props = {
  data: {
    remote: number;
    hybrid: number;
    onsite: number;
  };
};

const MODALITY_COLORS = {
  remote: "#00B6E8",   // Remoto
  hybrid: "#F4C430",   // Híbrido
  onsite: "#0A2540",   // Presencial
};

export function SeniorityModalityPieChart({ data }: Props) {
  const chartData = [
    { name: "Remoto", value: data.remote, key: "remote" },
    { name: "Híbrido", value: data.hybrid, key: "hybrid" },
    { name: "Presencial", value: data.onsite, key: "onsite" },
  ];

  return (
    <div className="border rounded-xl p-6 bg-white dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
      <p className="text-base font-semibold mb-4 text-slate-900 dark:text-slate-100">
        Distribución de modalidad laboral (%)
      </p>

      <div className="h-[320px]">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={chartData}
              dataKey="value"
              nameKey="name"
              innerRadius={70}
              outerRadius={110}
              paddingAngle={3}
              label={({ percent }) =>
                `${(percent * 100).toFixed(0)}%`
              }
            >
              {chartData.map((entry) => (
                <Cell
                  key={entry.key}
                  fill={MODALITY_COLORS[entry.key as keyof typeof MODALITY_COLORS]}
                />
              ))}
            </Pie>

            <Tooltip formatter={(v: number) => `${v}%`} />

            <Legend
              verticalAlign="bottom"
              iconType="circle"
              formatter={(value) => (
                <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                  {value}
                </span>
              )}
            />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
