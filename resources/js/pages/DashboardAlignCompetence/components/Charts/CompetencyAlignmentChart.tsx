import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

/* ======================================================
   TYPES
====================================================== */
type Competency = {
  id: number;
  name: string;
  market_match: boolean;
  trend_match: boolean;
};

type Props = {
  competencies: Competency[];
};

/* ======================================================
   HELPERS
====================================================== */
const shortenLabel = (text: string, max = 32) => {
  if (!text) return "";
  return text.length > max ? text.slice(0, max) + "…" : text;
};

/**
 * Orden semántico:
 * 1 → Alineada
 * 2 → Parcial
 * 3 → GAP
 */
const getStatusOrder = (mercado: number, reportes: number) => {
  if (mercado === 1 && reportes === 1) return 1;
  if (mercado === 1 || reportes === 1) return 2;
  return 3;
};

/* ======================================================
   COMPONENT
====================================================== */
export default function CompetencyAlignmentChart({ competencies }: Props) {
  const data = competencies
    .map((c) => {
      const mercado = c.market_match ? 1 : 0;
      const reportes = c.trend_match ? 1 : 0;

      return {
        name: c.name,
        mercado,
        reportes,
        gap: mercado === 0 && reportes === 0 ? 1 : 0,
        order: getStatusOrder(mercado, reportes),
      };
    })
    .sort((a, b) => {
      if (a.order !== b.order) return a.order - b.order;
      return a.name.localeCompare(b.name);
    });

  return (
    <div className="rounded-xl border bg-white p-5 shadow-sm">
      {/* HEADER */}
      <h3 className="text-sm font-semibold mb-1">
        Análisis de Competencias del Perfil de Egreso
      </h3>
      <p className="text-xs text-gray-500 mb-4">
        Presencia en mercado laboral y reportes estratégicos
      </p>

      {/* CHART */}
      <div className="h-[420px]">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart
            data={data}
            layout="vertical"
            barCategoryGap={28}
            margin={{ left: 10, right: 20 }}
          >
            {/* EJE X */}
            <XAxis
              type="number"
              domain={[0, 1]}
              ticks={[0, 1]}
              tickFormatter={(v) => (v === 1 ? "Sí" : "")}
              axisLine={false}
              tickLine={false}
              fontSize={11}
            />

            {/* EJE Y – TEXTO ALINEADO A LA IZQUIERDA */}
            <YAxis
              type="category"
              dataKey="name"
              width={220}
              tick={({ x, y, payload }) => (
                <g transform={`translate(${x},${y})`}>
                  <text
                    x={-210}              // 👈 empuja a la izquierda
                    y={0}
                    dy={4}
                    textAnchor="start"   // 👈 alineación izquierda
                    fill="#374151"
                    fontSize={12}
                  >
                    {shortenLabel(payload.value)}
                    <title>{payload.value}</title>
                  </text>
                </g>
              )}
            />

            {/* TOOLTIP */}
            <Tooltip
              cursor={{ fill: "#F9FAFB" }}
              formatter={(value, name) => {
                if (value !== 1) return null;
                if (name === "mercado") return ["Sí", "Mercado"];
                if (name === "reportes") return ["Sí", "Reportes"];
                if (name === "gap") return ["GAP", "No alineado"];
                return null;
              }}
            />

            {/* BARRAS */}
            <Bar
              dataKey="mercado"
              fill="#16A34A"
              barSize={6}
              radius={[0, 4, 4, 0]}
            />
            <Bar
              dataKey="reportes"
              fill="#0EA5E9"
              barSize={6}
              radius={[0, 4, 4, 0]}
            />
            <Bar
              dataKey="gap"
              fill="#9CA3AF"
              barSize={6}
              radius={[0, 4, 4, 0]}
            />
          </BarChart>
        </ResponsiveContainer>
      </div>

      {/* LEYENDA */}
      <div className="flex flex-wrap gap-4 text-xs text-gray-600 mt-4">
        <span className="flex items-center gap-1">
          <span className="w-3 h-3 bg-green-600 rounded-sm" />
          Alineada (Mercado + Reportes)
        </span>
        <span className="flex items-center gap-1">
          <span className="w-3 h-3 bg-sky-500 rounded-sm" />
          Parcialmente alineada
        </span>
        <span className="flex items-center gap-1">
          <span className="w-3 h-3 bg-gray-400 rounded-sm" />
          GAP (No alineado)
        </span>
      </div>
    </div>
  );
}
