
import { Card, CardContent } from "@/components/ui/card";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

const data = [
  { skill: "Inteligencia Artificial", value: 68 },
  { skill: "Cloud Computing", value: 54 },
  { skill: "Data Analytics", value: 49 },
  { skill: "Ciberseguridad", value: 45 },
];

export default function EmploymentRequestChart() {
  return (
    <Card className="bg-gray-800 text-white">
      <CardContent className="p-4">
        <h2 className="text-lg font-bold mb-2">% de empleos que solicitan skills</h2>
        <ResponsiveContainer width="100%" height={250}>
          <BarChart data={data} layout="vertical">
            <XAxis type="number" />
            <YAxis dataKey="skill" type="category" width={150} />
            <Tooltip />
            <Bar dataKey="value" fill="#0088FE" />
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
