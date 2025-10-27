import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

export default function TopTechnologiesChart({ data }: { data: any }) {
  const chartData = Array.isArray(data.data)
    ? data.data.map((item: any) => ({
        name: item.technology_name,
        value: item.nuevos ?? 0,
      }))
    : [];

  return (
    <div className="text-center">
      <h4 className="text-sm text-gray-300 mb-2">{data.label}</h4>
      <div className="w-full h-72">
        <ResponsiveContainer>
          <BarChart
            data={chartData}
            layout="vertical"
            margin={{ left: 60, right: 20 }}
          >
            <XAxis type="number" stroke="#9ca3af" />
            <YAxis
              type="category"
              dataKey="name"
              width={100}
              stroke="#9ca3af"
            />
            <Tooltip
              contentStyle={{
                backgroundColor: "#1f2937",
                border: "none",
                color: "#fff",
              }}
            />
            <Bar dataKey="value" fill="#3b82f6" radius={[0, 5, 5, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
