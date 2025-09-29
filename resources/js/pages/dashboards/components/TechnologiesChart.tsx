import { Card, CardContent } from "@/components/ui/card";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  ResponsiveContainer,
  LabelList,
  Cell,
} from "recharts";

const data = [
  { name: "Python", value: 100, color: "url(#python)" },
  { name: "Java", value: 80, color: "url(#java)" },
  { name: "React", value: 70, color: "url(#react)" },
  { name: "JavaScript", value: 65, color: "url(#javascript)" },
  { name: "TensorFlow", value: 50, color: "url(#tensorflow)" },
];

export default function TechnologiesChart() {
  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6">
        {/* Título */}
        <h2 className="text-center text-lg font-semibold mb-6">
          Tecnologías más mencionadas
        </h2>

        <ResponsiveContainer width="100%" height={280}>
          <BarChart
            data={data}
            layout="vertical"
            margin={{ top: 0, right: 40, left: 0, bottom: 20 }}
          >
            {/* Escala numérica abajo */}
            <XAxis
              type="number"
              domain={[0, 100]}
              tick={{ fill: "#9CA3AF", fontSize: 12 }}
            />
            {/* Nombres a la izquierda */}
            <YAxis
              dataKey="name"
              type="category"
              width={120}
              tick={{ fill: "#fff", fontSize: 12 }}
            />

            {/* Barras */}
            <Bar dataKey="value" barSize={22} radius={[0, 6, 6, 0]}>
              <LabelList
                dataKey="value"
                position="right"
                formatter={(val: number) => `${val}`}
                style={{
                  fill: "#fff",
                  fontWeight: "bold",
                  fontSize: 13,
                }}
              />
              {data.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={entry.color} />
              ))}
            </Bar>

            {/* Gradientes */}
            <defs>
              <linearGradient id="python" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#06b6d4" />
                <stop offset="100%" stopColor="#3b82f6" />
              </linearGradient>
              <linearGradient id="java" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#3b82f6" />
                <stop offset="100%" stopColor="#2563eb" />
              </linearGradient>
              <linearGradient id="react" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#2563eb" />
                <stop offset="100%" stopColor="#1d4ed8" />
              </linearGradient>
              <linearGradient id="javascript" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#1e40af" />
                <stop offset="100%" stopColor="#4338ca" />
              </linearGradient>
              <linearGradient id="tensorflow" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#f97316" />
                <stop offset="100%" stopColor="#ea580c" />
              </linearGradient>
            </defs>
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
