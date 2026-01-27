import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  Legend,
  CartesianGrid,
} from "recharts";
import { CareerSeniority } from "../hooks/useSeniorityData";

type Props = {
  data: CareerSeniority[];
};

const ISIL_COLORS = {
  junior: "#3BC9F5",
  mid: "#00A8CC",
  senior: "#062F45",
};

export function SeniorityBarChart({ data }: Props) {
  /* =====================================================
     EMPTY STATE
  ===================================================== */
  if (!data || data.length === 0) {
    return (
      <div className="border rounded-xl p-6 bg-white dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
        <p className="text-base font-semibold mb-2 text-slate-900 dark:text-slate-100">
          Distribución de seniority por carrera (%)
        </p>

        <div className="mt-4 text-sm text-slate-500 dark:text-slate-400">
          No hay datos de seniority disponibles para la carrera seleccionada en
          este período.
        </div>
      </div>
    );
  }

  /* =====================================================
     TRANSFORM DATA
  ===================================================== */
  const chartData = data.map((career) => {
    const row: any = { name: career.career_name };

    career.distribution.forEach((d) => {
      row[d.seniority] = d.percentage;
    });

    return row;
  });

  /* =====================================================
     DIMENSIONS
  ===================================================== */
  const ROW_HEIGHT = 84;
  const MIN_HEIGHT = 240;
  const chartHeight = Math.max(data.length * ROW_HEIGHT, MIN_HEIGHT);

  /* =====================================================
     FORCE RE-RENDER (career change)
  ===================================================== */
  const chartKey = data.map((d) => d.career_id).join("-");

  return (
    <div className="border rounded-xl p-6 bg-white dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
      <p className="text-base font-semibold mb-4 text-slate-900 dark:text-slate-100">
        Distribución de seniority por carrera (%)
      </p>

      <div style={{ height: chartHeight }}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart
            key={chartKey}
            data={chartData}
            layout="vertical"
            margin={{ top: 10, right: 30, left: 180, bottom: 10 }}
            barCategoryGap={36}
            barGap={12}
          >
            {/* GRID */}
            <CartesianGrid
              strokeDasharray="3 3"
              vertical
              horizontal={false}
              stroke="#CBD5E1"
              opacity={0.4}
            />

            {/* X AXIS */}
            <XAxis
              type="number"
              domain={[0, 100]}
              ticks={[0, 25, 50, 75, 100]}
              unit="%"
              tick={{ fontSize: 12 }}
            />

            {/* Y AXIS */}
            <YAxis
              type="category"
              dataKey="name"
              width={170}
              tick={{ fontSize: 13 }}
            />

            <Tooltip formatter={(v: number) => `${v}%`} />

            {/* LEGEND */}
            <Legend
              verticalAlign="top"
              align="right"
              iconType="rect"
              iconSize={14}
              wrapperStyle={{
                paddingBottom: 12,
                fontSize: 13,
                fontWeight: 600,
              }}
              formatter={(value) => {
                const colorMap: Record<string, string> = {
                  junior: ISIL_COLORS.junior,
                  mid: ISIL_COLORS.mid,
                  senior: ISIL_COLORS.senior,
                };

                return (
                  <span style={{ color: colorMap[value] }}>
                    {value === "junior"
                      ? "Junior"
                      : value === "mid"
                      ? "Semi Senior"
                      : "Senior"}
                  </span>
                );
              }}
            />

            {/* BARS */}
            <Bar
              dataKey="junior"
              fill={ISIL_COLORS.junior}
              barSize={11}
              radius={[4, 4, 4, 4]}
            />
            <Bar
              dataKey="mid"
              fill={ISIL_COLORS.mid}
              barSize={11}
              radius={[4, 4, 4, 4]}
            />
            <Bar
              dataKey="senior"
              fill={ISIL_COLORS.senior}
              barSize={11}
              radius={[4, 4, 4, 4]}
            />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
