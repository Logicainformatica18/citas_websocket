import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  CartesianGrid,
  Cell,
} from "recharts";

interface Props {
  courses: any[];
}

export default function CourseAlignmentChart({ courses }: Props) {
  /* =========================================
     DISTRIBUCIÓN
  ========================================= */
  const distribution = {
    strategically_aligned: 0,
    highly_aligned: 0,
    aligned: 0,
    not_aligned: 0,
  };

  courses.forEach((course) => {
    if (distribution.hasOwnProperty(course.level)) {
      distribution[course.level as keyof typeof distribution]++;
    }
  });

  const data = [
    {
      name: "Estratégico",
      value: distribution.strategically_aligned,
      color: "#0A2540",
    },
    {
      name: "Alto",
      value: distribution.highly_aligned,
      color: "#1CBCE8",
    },
    {
      name: "Básico",
      value: distribution.aligned,
      color: "#F59E0B",
    },
    {
      name: "No alineado",
      value: distribution.not_aligned,
      color: "#EF4444",
    },
  ];

  /* =========================================
     RENDER
  ========================================= */
  return (
    <div className="bg-white dark:bg-[#0A2540] border rounded-xl p-6 space-y-6 shadow-sm">

      <div>
        <h3 className="text-lg font-semibold text-[#0A2540] dark:text-white">
          Distribución de Alineación Curricular
        </h3>
        <p className="text-sm text-gray-500 dark:text-gray-400">
          Clasificación CCTC por curso – Periodo actual
        </p>
      </div>

      <div className="w-full h-80">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data}>
            <CartesianGrid strokeDasharray="3 3" opacity={0.15} />
            <XAxis
              dataKey="name"
              tick={{ fill: "#64748B", fontSize: 13 }}
            />
            <YAxis
              allowDecimals={false}
              tick={{ fill: "#64748B", fontSize: 13 }}
            />
            <Tooltip
              contentStyle={{
                backgroundColor: "#ffffff",
                borderRadius: "8px",
                border: "1px solid #E2E8F0",
              }}
            />
            <Bar dataKey="value" radius={[8, 8, 0, 0]}>
              {data.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={entry.color} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
