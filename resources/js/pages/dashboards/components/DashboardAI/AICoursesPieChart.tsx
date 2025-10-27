import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from "recharts";

export default function AICoursesPieChart({ data }: { data: any }) {
  const percent = data.value ?? 0;

  const chartData = [
    { name: "Con IA", value: percent },
    { name: "Sin IA", value: 100 - percent },
  ];

  const COLORS = ["#3b82f6", "#1f2937"];

  return (
    <div className="flex flex-col items-center justify-center text-center">
      <h4 className="text-sm text-gray-300 mb-2">{data.label}</h4>
      <div className="w-48 h-48">
        <ResponsiveContainer>
          <PieChart>
            <Pie
              data={chartData}
              dataKey="value"
              innerRadius={50}
              outerRadius={70}
              paddingAngle={3}
              startAngle={90}
              endAngle={450}
            >
              {chartData.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={COLORS[index]} />
              ))}
            </Pie>
            <Tooltip
              contentStyle={{
                backgroundColor: "#1f2937",
                border: "none",
                color: "#fff",
              }}
            />
          </PieChart>
        </ResponsiveContainer>
      </div>
      <p className="text-blue-400 text-lg font-semibold mt-2">
        {percent.toFixed(1)}%
      </p>
    </div>
  );
}
