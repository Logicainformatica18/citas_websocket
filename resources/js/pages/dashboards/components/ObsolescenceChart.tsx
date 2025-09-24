import { Card, CardContent } from "@/components/ui/card";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

const data = [
  { name: "Ingeniería de Software", value: 30 },
  { name: "Ciencia de Datos", value: 20 },
  { name: "Desarrollo Web", value: 45 },
  { name: "Redes y Comunicaciones", value: 35 },
];

export default function ObsolescenceChart() {
  return (
    <Card className="bg-gray-800 text-white">
      <CardContent className="p-4">
        <h2 className="text-lg font-bold mb-2">Nivel de Obsolescencia en IA</h2>
        <ResponsiveContainer width="100%" height={200}>
          <BarChart data={data} layout="vertical">
            <XAxis type="number" />
            <YAxis dataKey="name" type="category" width={150} />
            <Tooltip />
            <Bar dataKey="value" fill="#82ca9d" />
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
