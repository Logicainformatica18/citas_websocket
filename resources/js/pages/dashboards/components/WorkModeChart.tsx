import { useDashboard } from "../DashboardContext";
import { PieChart, Pie, Cell, Tooltip, Legend, ResponsiveContainer } from "recharts";
import { Card, CardContent } from "@/components/ui/card";
import { useEffect, useState } from "react";
import { ChartPie } from "lucide-react"; // 🔹 Icono

// 🔹 Modalidades esperadas (coinciden con backend)
const MODALITIES = [
  { key: "Presencial", label: "Presencial" },
  { key: "Remoto local", label: "Remoto local" },
  { key: "Remoto", label: "Remoto" },
  { key: "Híbrido", label: "Híbrido" },
];

export default function WorkModeChart({ initialData }: { initialData?: any }) {
  const { data } = useDashboard();
  const [chartData, setChartData] = useState<{ name: string; value: number }[]>([]);

  // Normalizar datos
  const normalizeData = (percentObj: Record<string, number> = {}) => {
    return MODALITIES.map(({ key, label }) => ({
      name: label,
      value: Number(percentObj[key]) || 0,
    }));
  };

  useEffect(() => {
    if (initialData?.aggregations?.percent) {
      setChartData(normalizeData(initialData.aggregations.percent));
    }
  }, [initialData]);

  useEffect(() => {
    if (data?.aggregations?.percent) {
      setChartData(normalizeData(data.aggregations.percent));
    }
  }, [data]);

  const safeData =
    chartData.length > 0
      ? chartData
      : [
          { name: "Presencial", value: 40 },
          { name: "Remoto local", value: 20 },
          { name: "Remoto", value: 25 },
          { name: "Híbrido", value: 15 },
        ];

  // 🔹 Colores degradados para hacerlo más moderno
  const COLORS = [
    "url(#colorPresencial)",
    "url(#colorRemotoLocal)",
    "url(#colorRemoto)",
    "url(#colorHibrido)",
  ];

  // Tooltip personalizado con color
  const CustomTooltip = ({ active, payload }: any) => {
    if (active && payload && payload.length) {
      const { name, value, payload: entry } = payload[0];
      return (
        <div className="bg-gray-900 border border-gray-700 text-white text-xs px-3 py-2 rounded shadow-md">
          <p className="font-semibold flex items-center gap-2">
            <span
              className="inline-block w-3 h-3 rounded-full"
              style={{ background: entry.fill }}
            />
            {name}
          </p>
          <p>{value}%</p>
        </div>
      );
    }
    return null;
  };

  return (
    <Card className="bg-gradient-to-b from-gray-900 to-gray-800 text-white rounded-xl border border-gray-700 shadow-lg">
      <CardContent className="p-6 flex flex-col items-center">
        {/* 🔹 Título con ícono */}
        <div className="flex items-center gap-2 mb-4">
          <ChartPie size={18} className="text-blue-400" />
          <h2 className="text-center text-base font-semibold text-gray-100 tracking-wide">
            Preferencia por modalidad
          </h2>
        </div>

        <div className="w-full h-72">
          <ResponsiveContainer>
            <PieChart>
              {/* Definir degradados */}
              <defs>
                <linearGradient id="colorPresencial" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="5%" stopColor="#2563EB" stopOpacity={0.9} />
                  <stop offset="95%" stopColor="#3B82F6" stopOpacity={0.9} />
                </linearGradient>
                <linearGradient id="colorRemotoLocal" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="5%" stopColor="#06B6D4" stopOpacity={0.9} />
                  <stop offset="95%" stopColor="#22D3EE" stopOpacity={0.9} />
                </linearGradient>
                <linearGradient id="colorRemoto" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="5%" stopColor="#DC2626" stopOpacity={0.9} />
                  <stop offset="95%" stopColor="#F87171" stopOpacity={0.9} />
                </linearGradient>
                <linearGradient id="colorHibrido" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="5%" stopColor="#10B981" stopOpacity={0.9} />
                  <stop offset="95%" stopColor="#34D399" stopOpacity={0.9} />
                </linearGradient>
              </defs>

              {/* Gráfico */}
              <Pie
                data={safeData}
                dataKey="value"
                nameKey="name"
                cx="50%"
                cy="50%"
                outerRadius="80%"
                labelLine
                label={({ name, value }) => `${name}: ${value}%`}
              >
                {safeData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={COLORS[index]} stroke="#1f2937" strokeWidth={1} />
                ))}
              </Pie>
              <Tooltip content={<CustomTooltip />} />
              <Legend
                verticalAlign="bottom"
                align="center"
                iconType="circle"
                wrapperStyle={{
                  fontSize: "12px",
                  color: "#ddd",
                  marginTop: "10px",
                }}
              />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </CardContent>
    </Card>
  );
}
