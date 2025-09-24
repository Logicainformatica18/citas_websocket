import { Card, CardContent } from "@/components/ui/card";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

const data = [
  { tech: "Python", value: 100 },
  { tech: "Java", value: 80 },
  { tech: "React", value: 70 },
  { tech: "JavaScript", value: 65 },
  { tech: "TensorFlow", value: 50 },
];

export default function TechnologiesChart() {
  return (
    <Card className="bg-gray-800 text-white">
      <CardContent className="p-4">
        <h2 className="text-lg font-bold mb-2">Tecnologías más mencionadas</h2>
        <ResponsiveContainer width="100%" height={200}>
          <BarChart data={data}>
            <XAxis dataKey="tech" />
            <YAxis />
            <Tooltip />
            <Bar dataKey="value" fill="#00C49F" />
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
