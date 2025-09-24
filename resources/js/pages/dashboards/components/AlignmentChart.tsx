import { Card, CardContent } from "@/components/ui/card";
import { PieChart, Pie, Cell } from "recharts";

const data = [
  { name: "Tech", value: 75 },
  { name: "Other", value: 25 },
];

const COLORS = ["#00C49F", "#1f2937"]; // verde + gris oscuro

export default function AlignmentChart() {
  return (
    <Card className="bg-slate-900 text-white rounded-xl shadow-lg">
      <CardContent className="p-6 flex flex-col items-center justify-center">
        {/* Título */}
        <h2 className="text-center text-sm font-semibold text-slate-200 uppercase tracking-wide mb-4">
          Alineación del área de tecnología con las tendencias del sector
        </h2>

        <div className="flex items-center gap-6">
          {/* Donut Chart */}
          <div className="relative">
            <PieChart width={180} height={180}>
              <Pie
                data={data}
                dataKey="value"
                cx="50%"
                cy="50%"
                innerRadius={60}
                outerRadius={80}
                startAngle={90}
                endAngle={-270}
              >
                {data.map((entry, index) => (
                  <Cell key={index} fill={COLORS[index % COLORS.length]} />
                ))}
              </Pie>
            </PieChart>

            {/* Texto central */}
            <div className="absolute inset-0 flex items-center justify-center">
              <span className="text-3xl font-bold text-white">75%</span>
            </div>
          </div>

          {/* Caja lateral con label */}
          <div className="bg-gradient-to-br from-slate-700 to-slate-800 px-4 py-3 rounded-lg shadow-md">
            <p className="text-sm text-slate-300">Tech</p>
            <p className="text-lg font-semibold text-white">75%</p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
