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
  remote: "#00B6E8",
  hybrid: "#F4C430",
  onsite: "#0A2540",
};

export function SeniorityModalityPieChart({ data }: Props) {
  const chartData = [
    { name: "Remoto", value: data.remote, key: "remote" },
    { name: "Híbrido", value: data.hybrid, key: "hybrid" },
    { name: "Presencial", value: data.onsite, key: "onsite" },
  ].filter((d) => d.value > 0); // 👈 evita render vacío

  if (!chartData.length) {
    return (
      <div className="border rounded-xl p-6 bg-white dark:bg-[#0F2A3A]">
        <p className="text-sm text-slate-500">
          No hay datos de modalidad laboral para el período seleccionado.
        </p>
      </div>
    );
  }

  return (
    <div className="border rounded-xl p-6 bg-white dark:bg-[#0F2A3A]">
      <p className="text-base font-semibold mb-4">
        Distribución de modalidad laboral (%)
      </p>

      <div className="h-[300px]">
        <ResponsiveContainer>
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
            <Legend verticalAlign="bottom" iconType="circle" />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
