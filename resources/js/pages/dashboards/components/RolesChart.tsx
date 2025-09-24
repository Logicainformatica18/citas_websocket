import { Card, CardContent } from "@/components/ui/card";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

const data = [
  { role: "AI Prompt Engineer", value: 80 },
  { role: "Cloud Specialist", value: 85 },
  { role: "DevSecOps", value: 70 },
  { role: "Data Product Manager", value: 65 },
  { role: "Ethical Hacking Consultant", value: 60 },
  { role: "ML/AI Architect", value: 55 },
];

export default function RolesChart() {
  return (
    <Card className="bg-gray-800 text-white">
      <CardContent className="p-4">
        <h2 className="text-lg font-bold mb-2">Nuevos roles profesionales</h2>
        <ResponsiveContainer width="100%" height={200}>
          <BarChart data={data}>
            <XAxis dataKey="role" hide />
            <YAxis />
            <Tooltip />
            <Bar dataKey="value" fill="#8884d8" />
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
