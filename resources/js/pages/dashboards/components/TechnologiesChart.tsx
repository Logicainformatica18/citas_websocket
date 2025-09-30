import { Card, CardContent } from "@/components/ui/card";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  ResponsiveContainer,
  LabelList,
  Cell,
} from "recharts";
import { useEffect, useState } from "react";
import axios from "axios";

type TechData = { name: string; value: number; color: string };

export default function TechnologiesChart() {
  const [data, setData] = useState<TechData[]>([]);
  const [offset, setOffset] = useState(0);
  const [hasMore, setHasMore] = useState(true);

  const gradients = [
    { id: "python", from: "#06b6d4", to: "#3b82f6" },
    { id: "java", from: "#3b82f6", to: "#2563eb" },
    { id: "react", from: "#2563eb", to: "#1d4ed8" },
    { id: "javascript", from: "#1e40af", to: "#4338ca" },
    { id: "tensorflow", from: "#f97316", to: "#ea580c" },
  ];

  const loadData = async (reset = false) => {
    try {
      const res = await axios.get("/ai/technologies", {
        params: { limit: 10, offset: reset ? 0 : offset },
      });

      const results = res.data.aggregations?.percent || {};
      const meta = res.data.meta || {};

      const mapped: TechData[] = Object.entries(results).map(
        ([name, value], i) => ({
          name,
          value: Number(value), // porcentaje
          color: `url(#${gradients[i % gradients.length].id})`,
        })
      );

      if (reset) {
        setData(mapped);
        setOffset(10);
      } else {
        setData((prev) => [...prev, ...mapped]);
        setOffset((prev) => prev + 10); // ✅ evitar estado viejo
      }

      // ✅ calcular si hay más lenguajes disponibles
      const totalLangs = meta.total_languages || 0;
      setHasMore(offset + mapped.length < totalLangs);
    } catch (err) {
      console.error("❌ Error cargando tecnologías:", err);
    }
  };

  useEffect(() => {
    loadData(true); // primera carga
  }, []);

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6">
        {/* Título */}
        <h2 className="text-center text-lg font-semibold mb-6">
          Tecnologías más mencionadas (%)
        </h2>

        {/* Gráfico con altura dinámica */}
        <ResponsiveContainer width="100%" height={data.length * 40}>
          <BarChart
            data={data}
            layout="vertical"
            margin={{ top: 0, right: 40, left: 0, bottom: 20 }}
          >
            <XAxis
              type="number"
              domain={[0, 30]}
              tick={{ fill: "#9CA3AF", fontSize: 12 }}
            />
            <YAxis
              dataKey="name"
              type="category"
              width={120}
              tick={{ fill: "#fff", fontSize: 12 }}
            />

            <Bar dataKey="value" barSize={22} radius={[0, 6, 6, 0]}>
              <LabelList
                dataKey="value"
                position="right"
                formatter={(val: number) => `${val}%`}
                style={{ fill: "#fff", fontWeight: "bold", fontSize: 13 }}
              />
              {data.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={entry.color} />
              ))}
            </Bar>

            {/* Gradientes */}
            <defs>
              {gradients.map((g) => (
                <linearGradient
                  key={g.id}
                  id={g.id}
                  x1="0"
                  y1="0"
                  x2="1"
                  y2="0"
                >
                  <stop offset="0%" stopColor={g.from} />
                  <stop offset="100%" stopColor={g.to} />
                </linearGradient>
              ))}
            </defs>
          </BarChart>
        </ResponsiveContainer>

        {/* Botón cargar más */}
        {hasMore && (
          <div className="flex justify-center mt-4">
            <button
              onClick={() => loadData()}
              className="px-4 py-0 bg-blue-600 hover:bg-blue-700 rounded text-sm font-medium"
            >
              Cargar más
            </button>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
