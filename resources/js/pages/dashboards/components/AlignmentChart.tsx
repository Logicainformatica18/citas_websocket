import { Card, CardContent } from "@/components/ui/card";
import { PieChart, Pie, Cell } from "recharts";

const data = [
  { name: "Tech", value: 75 },
  { name: "Other", value: 25 },
];

const COLORS = ["#00C49F", "#2D2D2D"]; // verde + gris oscuro

export default function AlignmentChart() {
  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6 flex flex-col items-center justify-center">
        {/* Título */}
        <h2 className="text-center text-sm font-medium text-gray-300 uppercase tracking-wide mb-6">
          ALINEACIÓN DEL ÁREA DE TECNOLOGÍA <br />
          CON LAS TENDENCIAS DEL SECTOR
        </h2>

        <div className="flex items-center gap-8">
          {/* Donut Chart */}
          <div className="relative">
            <PieChart width={160} height={160}>
              <Pie
                data={data}
                dataKey="value"
                cx="50%"
                cy="50%"
                innerRadius={55}
                outerRadius={70}
                startAngle={90}
                endAngle={-270}
                stroke="none"
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

          {/* Caja lateral con degradado */}
          <div className="bg-gradient-to-br from-slate-800 to-slate-900/80 px-6 py-4 rounded-lg shadow-md flex flex-col items-center">
            <p className="text-sm text-gray-300">Tech</p>
            <p className="text-xl font-semibold text-white">75%</p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
