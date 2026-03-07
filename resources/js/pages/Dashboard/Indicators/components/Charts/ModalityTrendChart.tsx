import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from "recharts";

type TrendItem = {
  month: string;
  remoto: number;
  hibrido: number;
  presencial: number;
};

const CustomLegend = ({ payload }: any) => {
  return (
    <div className="flex justify-center gap-6 pt-2">
      {payload.map((entry: any) => (
        <div
          key={entry.value}
          className="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300"
        >
          <span
            className="inline-block h-3 w-6 rounded-sm"
            style={{
              backgroundColor: entry.color,
              borderTop:
                entry.value === "Híbrido"
                  ? `2px dashed ${entry.color}`
                  : `2px solid ${entry.color}`,
            }}
          />
          {entry.value}
        </div>
      ))}
    </div>
  );
};

export default function ModalityTrendChart({
  data,
}: {
  data: TrendItem[];
}) {
  if (!data || data.length === 0) return null;

  return (
    <div className="rounded-2xl border bg-white p-6 shadow-sm dark:bg-[#0F2A3A] dark:border-slate-700">

      {/* HEADER */}
      <div className="mb-4">
        <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">
          Evolución temporal
        </h3>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          Tendencia de los últimos 6 meses
        </p>
      </div>

      {/* CHART */}
      <div className="h-[320px]">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data}>

            <CartesianGrid strokeDasharray="3 3" stroke="#E5F0F6" />

            <XAxis
              dataKey="month"
              tick={{ fontSize: 12 }}
              stroke="#94A3B8"
            />

            <YAxis
              tickFormatter={(v) => `${v}%`}
              tick={{ fontSize: 12 }}
              stroke="#94A3B8"
              domain={[0, 100]}
            />

            <Tooltip
              formatter={(value: number) => [`${value}%`, ""]}
              labelStyle={{ fontWeight: 600 }}
            />

            <Legend verticalAlign="bottom" content={<CustomLegend />} />

            {/* REMOTO */}
            <Line
              type="monotone"
              dataKey="remoto"
              name="Remoto"
              stroke="#00B6E8"
              strokeWidth={3}
              dot={{ r: 3 }}
              activeDot={{ r: 5 }}
            />

            {/* HÍBRIDO */}
            <Line
              type="monotone"
              dataKey="hibrido"
              name="Híbrido"
              stroke="#22C55E"
              strokeWidth={3}
              strokeDasharray="6 4"
              dot={{ r: 3 }}
              activeDot={{ r: 5 }}
            />

            {/* PRESENCIAL */}
            <Line
              type="monotone"
              dataKey="presencial"
              name="Presencial"
              stroke="#F97316"
              strokeWidth={3}
              dot={{ r: 4 }}
              activeDot={{ r: 6 }}
            />

          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
