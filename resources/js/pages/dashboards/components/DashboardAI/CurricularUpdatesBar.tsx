import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

export default function CurricularUpdatesBar({ data }: { data: any }) {
  const chartData = Array.isArray(data)
    ? data
    : [{ name: "Actualizaciones", value: data.value ?? 0 }];

  return (
    <div className="text-center">
      <h4 className="text-sm text-gray-300 mb-2">{data.label}</h4>
      <div className="w-full h-64">
        <ResponsiveContainer>
          <BarChart data={chartData}>
            <XAxis dataKey="name" stroke="#9ca3af" />
            <YAxis stroke="#9ca3af" />
            <Tooltip
              contentStyle={{
                backgroundColor: "#1f2937",
                border: "none",
                color: "#fff",
              }}
            />
            <Bar dataKey="value" fill="#3b82f6" radius={[5, 5, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </div>
      {data.value && (
        <p className="text-blue-400 text-lg font-semibold mt-2">
          {data.value} {data.unit}
        </p>
      )}
    </div>
  );
}
