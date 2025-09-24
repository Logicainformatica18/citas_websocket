import { useDashboard } from "../DashboardContext";
import { PieChart, Pie, Tooltip, Cell } from "recharts";

export default function WorkModeChart() {
  const { data } = useDashboard();

  const chartData = Object.entries(data.aggregations.percent ?? {}).map(([key, value]) => ({
    name: key,
    value,
  }));
console.log("🎨 chartData recibido:", chartData);

  if (chartData.length === 0) {
    return <p className="text-gray-400">Sin datos aún</p>;
  }

  return (
    <div className="bg-white text-black rounded-xl p-4 shadow">
      <h2 className="font-bold mb-2">Modalidad de Trabajo</h2>
      <PieChart width={300} height={200}>
        <Pie
          data={chartData}
          dataKey="value"
          nameKey="name"
          cx="50%"
          cy="50%"
          outerRadius={80}
          fill="#8884d8"
          label
        >
          {chartData.map((_, index) => (
            <Cell key={`cell-${index}`} fill={["#0088FE", "#00C49F", "#FFBB28", "#FF8042"][index % 4]} />
          ))}
        </Pie>
        <Tooltip />
      </PieChart>
    </div>
  );
}
