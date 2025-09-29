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
  { skill: "Ciberseguridad", value: 30 },
  { skill: "Data Science", value: 38 },
  { skill: "Cloud Computing", value: 55 },
  { skill: "Inteligencia Artificial", value: 42 },
];

const COLORS = ["#6ec1e4", "#4aa3df", "#2d7ed0", "#1f5bb5"];

export default function EmploymentRequestChart() {
  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6 relative">
        <ResponsiveContainer width="100%" height={280}>
          <BarChart
            data={data}
            layout="vertical"
            margin={{ top: 40, right: 50, left: 0, bottom: 20 }}
          >
            {/* Eje X en % */}
            <XAxis
              type="number"
              domain={[0, 100]}
              tick={{ fill: "#bbb", fontSize: 12 }}
            />
            {/* Eje Y con skills */}
            <YAxis
              dataKey="skill"
              type="category"
              width={160}
              tick={{ fill: "#fff", fontSize: 13 }}
            />

            {/* Barras con degradado */}
            <Bar dataKey="value" barSize={30} radius={[0, 6, 6, 0]}>
              <LabelList
                dataKey="value"
                position="right"
                formatter={(val: number) => `${val}%`}
                style={{ fill: "#fff", fontWeight: "bold", fontSize: 12 }}
              />
              {data.map((entry, i) => (
                <Cell key={i} fill={COLORS[i % COLORS.length]} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>

        {/* Título arriba centrado */}
        <div className="absolute top-4 left-1/2 -translate-x-1/2 text-white font-bold text-sm">
          % de empleos que solicitan IA, Cloud, Data y Ciberseguridad
        </div>
      </CardContent>
    </Card>
  );
}
