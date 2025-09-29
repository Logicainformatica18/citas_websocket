import { useDashboard } from "../DashboardContext";
import { PieChart, Pie, Cell } from "recharts";
import { Card, CardContent } from "@/components/ui/card";

export default function WorkModeChart() {
  const { data } = useDashboard();

  const chartData = Object.entries(data.aggregations.percent ?? {}).map(
    ([key, value]) => ({
      name: key,
      value,
    })
  );

  if (chartData.length === 0) {
    return <p className="text-gray-400">Sin datos aún</p>;
  }

  const COLORS = ["#1E3A8A", "#38BDF8", "#DC2626"]; // Hybrid, On site, Remote

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6 flex flex-col items-center">
        {/* Título */}
        <h2 className="text-center text-sm font-medium text-gray-200 mb-4">
          Preferencia por modalidad
        </h2>

        {/* PieChart */}
        <PieChart width={300} height={300}>
          <Pie
            data={chartData}
            dataKey="value"
            nameKey="name"
            cx="50%"
            cy="50%"
            outerRadius={100}
            label={({ name, value }) => `${name} ${value}%`}
            labelStyle={{
              fill: "#fff",
              fontSize: 14,
              fontWeight: "500",
            }}
          >
            {chartData.map((_, index) => (
              <Cell
                key={`cell-${index}`}
                fill={COLORS[index % COLORS.length]}
              />
            ))}
          </Pie>
        </PieChart>
      </CardContent>
    </Card>
  );
}
