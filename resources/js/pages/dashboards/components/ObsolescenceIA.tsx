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
  { name: "Ingeniería de Software", value: 30 },
  { name: "Ciencia de Datos", value: 20 },
  { name: "Desarrollo Web", value: 45 },
  { name: "Redes y Comunicaciones", value: 35 },
];

export default function ObsolescenceIA() {
  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6">
        {/* Título */}
        <h2 className="text-center text-sm font-medium text-gray-300 uppercase tracking-wide mb-6">
          NIVEL DE OBSOLESCENCIA EN IA
        </h2>

        <ResponsiveContainer width="100%" height={220}>
          <BarChart
            data={data}
            layout="vertical"
            margin={{ top: 0, right: 40, left: 0, bottom: 0 }}
          >
            {/* Ocultar eje X */}
            <XAxis type="number" hide />
            {/* Etiquetas a la izquierda */}
            <YAxis
              dataKey="name"
              type="category"
              width={160}
              tick={{ fill: "#fff", fontSize: 12 }}
            />

            {/* Barras */}
            <Bar dataKey="value" barSize={22} radius={[0, 6, 6, 0]} fill="url(#turquoise)">
              <LabelList
                dataKey="value"
                position="right"
                formatter={(val: number) => `${val}%`}
                style={{ fill: "#fff", fontWeight: "bold", fontSize: 13 }}
              />
              {data.map((_, i) => (
                <Cell key={i} fill="url(#turquoise)" />
              ))}
            </Bar>

            {/* Gradiente turquesa */}
            <defs>
              <linearGradient id="turquoise" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#38bdf8" />
                <stop offset="100%" stopColor="#3f979e" />
              </linearGradient>
            </defs>
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
