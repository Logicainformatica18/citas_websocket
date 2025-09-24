import { Card, CardContent } from "@/components/ui/card";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

const data = [
  { career: "Negocios", value: 60 },
  { career: "Diseño UX", value: 45 },
  { career: "Ciberseguridad", value: 70 },
  { career: "Ingeniería Software", value: 75 },
  { career: "Marketing Digital", value: 50 },
];

export default function CareerAlignmentChart() {
  return (
    <Card className="bg-gray-800 text-white">
      <CardContent className="p-4">
        <h2 className="text-lg font-bold mb-2">Grado de alineación curricular</h2>
        <ResponsiveContainer width="100%" height={250}>
          <BarChart data={data} layout="vertical">
            <XAxis type="number" />
            <YAxis dataKey="career" type="category" width={120} />
            <Tooltip />
            <Bar dataKey="value" fill="#FF8042" />
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
