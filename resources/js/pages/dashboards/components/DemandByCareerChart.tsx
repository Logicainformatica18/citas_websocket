import { Card, CardContent } from "@/components/ui/card";
import { PieChart, Pie, Cell, ResponsiveContainer } from "recharts";

const data = [
  { name: "Software", value: 200 },
  { name: "Ciberseguridad", value: 180 },
  { name: "Data Science", value: 250 },
  { name: "Marketing Digital", value: 140 },
  { name: "Diseño UX", value: 90 },
  { name: "Negocios", value: 320 },
];

const COLORS = [
  "#0d47a1", // Software
  "#1976d2", // Ciberseguridad
  "#42a5f5", // Data Science
  "#64b5f6", // Marketing Digital
  "#1565c0", // Diseño UX
  "#90caf9", // Negocios
];

export default function DemandByCareerChart() {
  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6">
        <div className="flex items-center justify-between">
          {/* Donut chart */}
          <ResponsiveContainer width="70%" height={320}>
            <PieChart>
              <Pie
                data={data}
                cx="50%"
                cy="50%"
                innerRadius={90}
                outerRadius={130}
                dataKey="value"
                paddingAngle={2}
                label={({ value }) => value}
                labelLine={false}
                labelStyle={{
                  fill: "#fff",   // valores en blanco
                  fontWeight: "bold",
                  fontSize: 12,  // tamaño reducido
                }}
              >
                {data.map((entry, index) => (
                  <Cell
                    key={`cell-${index}`}
                    fill={COLORS[index % COLORS.length]}
                  />
                ))}
              </Pie>
            </PieChart>
          </ResponsiveContainer>

          {/* Título y leyenda */}
          <div className="ml-6 flex flex-col items-start">
            <h2 className="text-md font-bold mb-4 text-white">
              Demanda Laboral <br /> por Carrera
            </h2>
            <ul className="space-y-2 text-xs">
              {data.map((entry, index) => (
                <li key={index} className="flex items-center gap-2">
                  <span
                    className="inline-block w-3 h-3 rounded-sm"
                    style={{ backgroundColor: COLORS[index % COLORS.length] }}
                  ></span>
                  {entry.name}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
