import { ResponsiveContainer, RadialBarChart, RadialBar } from "recharts";

export default function ObsolescenceGauge({ data }: { data: any }) {
  const value = data.value ?? 0;
  const chartData = [{ name: data.label, value }];

  return (
    <div className="flex flex-col items-center justify-center text-center">
      <h4 className="text-sm text-gray-300 mb-2">{data.label}</h4>
      <ResponsiveContainer width={180} height={180}>
        <RadialBarChart
          innerRadius="80%"
          outerRadius="100%"
          data={chartData}
          startAngle={180}
          endAngle={0}
        >
          <RadialBar
            dataKey="value"
            fill="#ef4444"
            cornerRadius={10}
            minAngle={15}
            clockWise={false}
          />
        </RadialBarChart>
      </ResponsiveContainer>
      <p className="text-red-400 text-xl font-semibold mt-2">
        {value.toFixed(1)}%
      </p>
    </div>
  );
}
