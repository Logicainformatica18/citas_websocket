import { Card, CardContent } from "@/components/ui/card";
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer } from "recharts";

const data = [
  { name: "Software", value: 300 },
  { name: "Ciberseguridad", value: 250 },
  { name: "Data Science", value: 200 },
  { name: "Marketing Digital", value: 150 },
  { name: "Gestión de Negocios", value: 100 },
];

const COLORS = ["#0088FE", "#00C49F", "#FFBB28", "#FF8042", "#AA46BE"];

export default function DemandByCareerChart() {
  return (
    <Card className="bg-gray-800 text-white">
      <CardContent className="p-4">
        <h2 className="text-lg font-bold mb-2">Demanda laboral por carrera</h2>
        <ResponsiveContainer width="100%" height={200}>
          <PieChart>
            <Pie
              data={data}
              cx="50%"
              cy="50%"
              outerRadius={80}
              fill="#8884d8"
              dataKey="value"
              label
            >
              {data.map((entry, index) => (
                <Cell key={index} fill={COLORS[index % COLORS.length]} />
              ))}
            </Pie>
            <Tooltip />
          </PieChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
