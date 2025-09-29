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
  { career: "Negocios", value: 55 },
  { career: "Diseño UX", value: 45 },
  { career: "Ciberseguridad", value: 90 },
  { career: "Ingeniería Software", value: 65 },
  { career: "Marketing", value: 80 },
];

// función para asignar color según valor
const getColor = (val: number) => {
  if (val >= 80) return "#2ecc71"; // verde
  if (val >= 50) return "#f1c40f"; // amarillo
  return "#e74c3c"; // rojo
};

export default function CareerAlignmentChart() {
  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6">
        <ResponsiveContainer width="100%" height={300}>
          <BarChart
            data={data}
            layout="vertical"
            margin={{ top: 20, right: 80, left: 0, bottom: 20 }}
          >
            {/* Eje X numérico */}
            <XAxis
              type="number"
              domain={[0, 100]}
              tick={{ fill: "#38bdf8", fontSize: 12 }}
              label={{
                value: "Nivel de alineación (%)",
                position: "insideBottom",
                offset: -5,
                fill: "#38bdf8",
                fontSize: 12,
              }}
            />
            {/* Eje Y carreras */}
            <YAxis
              dataKey="career"
              type="category"
              width={150}
              tick={{ fill: "#fff", fontSize: 13 }}
            />

            {/* Barras */}
            <Bar dataKey="value" barSize={28} radius={[0, 6, 6, 0]}>
              <LabelList
                dataKey="value"
                position="right"
                formatter={(val: number) => `${val}%`}
                style={{ fill: "#fff", fontWeight: "bold", fontSize: 12 }}
              />
              {data.map((entry, i) => (
                <Cell key={i} fill={getColor(entry.value)} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>

        {/* Título dentro del chart (lado derecho) */}
        {/* <div className="absolute top-6 right-10 text-white text-sm font-bold text-right">
          Grado de alineación <br /> curricular
        </div> */}
      </CardContent>
    </Card>
  );
}
