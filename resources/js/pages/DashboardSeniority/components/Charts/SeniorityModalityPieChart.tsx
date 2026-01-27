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

/* =====================================================
   Label externo (porcentaje legible)
===================================================== */
const renderLabel = ({
  cx,
  cy,
  midAngle,
  outerRadius,
  percent,
}: any) => {
  const RADIAN = Math.PI / 180;
  const radius = outerRadius + 18;
  const x = cx + radius * Math.cos(-midAngle * RADIAN);
  const y = cy + radius * Math.sin(-midAngle * RADIAN);

  return (
    <text
      x={x}
      y={y}
      fill="#0A2540"
      textAnchor={x > cx ? "start" : "end"}
      dominantBaseline="central"
      fontSize={12}
      fontWeight={600}
    >
      {(percent * 100).toFixed(0)}%
    </text>
  );
};

export function SeniorityModalityPieChart({ data }: Props) {
  const chartData = [
    { name: "Remoto", value: data.remote, key: "remote" },
    { name: "Híbrido", value: data.hybrid, key: "hybrid" },
    { name: "Presencial", value: data.onsite, key: "onsite" },
  ].filter((d) => d.value > 0);

  const total = chartData.reduce((acc, d) => acc + d.value, 0);

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
    <div className="border rounded-xl p-6 bg-white dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
      <p className="text-base font-semibold mb-4 text-slate-900 dark:text-slate-100">
        Distribución de modalidad laboral (%)
      </p>

      <div className="h-[320px] relative">
        <ResponsiveContainer>
          <PieChart>
            <Pie
              data={chartData}
              dataKey="value"
              nameKey="name"
              innerRadius={75}
              outerRadius={115}
              paddingAngle={4}
              labelLine={{ stroke: "#94A3B8", strokeWidth: 1 }}
              label={renderLabel}
            >
              {chartData.map((entry) => (
                <Cell
                  key={entry.key}
                  fill={
                    MODALITY_COLORS[
                      entry.key as keyof typeof MODALITY_COLORS
                    ]
                  }
                />
              ))}
            </Pie>

            {/* Tooltip enriquecido */}
            <Tooltip
              formatter={(v: number) => `${v}%`}
              contentStyle={{
                borderRadius: 10,
                border: "1px solid #E2E8F0",
                fontSize: 13,
              }}
            />

            {/* Leyenda */}
            <Legend
              verticalAlign="bottom"
              iconType="circle"
              iconSize={10}
              wrapperStyle={{
                fontSize: 13,
                paddingTop: 12,
              }}
            />
          </PieChart>
        </ResponsiveContainer>

        {/* ===== CENTRO DEL DONUT ===== */}
        <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Total
          </p>
          <p className="text-xl font-bold text-[#0A2540] dark:text-white">
            {total}%
          </p>
        </div>
      </div>
    </div>
  );
}
