import { useEffect, useState } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Legend } from "recharts";
import { Filter } from "lucide-react";
import axios from "axios";

type WorkMode = { mode: string; total: number };
type AiSentiment = { sentiment: string; total: number };
type Country = { country: string; total: number };

export default function StackOverflowInsights() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [year, setYear] = useState<number>(2024);

  // 🎨 Colores por categoría
  const colors = ["#3b82f6", "#22c55e", "#f59e0b", "#e11d48", "#8b5cf6", "#0ea5e9", "#10b981", "#f43f5e"];

  // 📦 Cargar datos desde API
  useEffect(() => {
    const loadData = async () => {
      setLoading(true);
      try {
        const res = await axios.get("/api/ai/stackoverflow/get-data", { params: { year } });
        setData(res.data.summary);
      } catch (err) {
        console.error("❌ Error cargando Stack Overflow Insights:", err);
      } finally {
        setLoading(false);
      }
    };
    loadData();
  }, [year]);

  if (loading) return <p className="text-gray-400 text-center mt-8">Cargando Stack Overflow Insights...</p>;
  if (!data) return <p className="text-gray-400 text-center mt-8">No hay datos disponibles</p>;

  // ===================================================
  // 📊 Preprocesar datos
  // ===================================================
  const workModes = (data.work_modes || []).map((m: WorkMode) => ({ name: m.mode || "Desconocido", value: m.total }));
  const aiSentiments = (data.ai_sentiments || []).map((a: AiSentiment) => ({ name: a.sentiment || "Sin respuesta", value: a.total }));
  const topLanguages = Object.entries(data.top_languages || {}).map(([name, value]) => ({ name, value }));

  const avgSalary = data.avg_salary ? data.avg_salary.toLocaleString("en-US", { style: "currency", currency: "USD" }) : "No disponible";

  // ===================================================
  // 🎨 Renderizado principal
  // ===================================================
  return (
    <Card className="bg-[#111] text-white border border-gray-700 rounded-xl p-6">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-sm font-semibold uppercase tracking-wide">
          Stack Overflow Insights {year}
        </h2>
        <button
          onClick={() => setYear(year === 2024 ? 2025 : 2024)}
          className="flex items-center gap-2 text-xs bg-gray-800 hover:bg-gray-700 px-3 py-1 rounded transition"
        >
          <Filter className="w-3 h-3" /> Cambiar año ({year})
        </button>
      </div>

      <CardContent className="grid grid-cols-2 gap-6">
        {/* 🧑‍💻 Modalidad laboral */}
        <div>
          <p className="text-gray-400 text-xs mb-2">Modalidad laboral</p>
          {workModes.length > 0 ? (
            <ResponsiveContainer width="100%" height={180}>
              <PieChart>
                <Pie data={workModes} dataKey="value" nameKey="name" outerRadius={60} label>
                  {workModes.map((entry, index) => (
                    <Cell key={index} fill={colors[index % colors.length]} />
                  ))}
                </Pie>
                <Tooltip formatter={(v) => `${v} respuestas`} />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-gray-500 text-xs text-center mt-8">Sin datos</p>
          )}
        </div>

        {/* 💰 Salario promedio */}
        <div className="flex flex-col items-center justify-center">
          <p className="text-gray-400 text-xs mb-1">Salario promedio</p>
          <p className="text-3xl font-bold text-green-400">{avgSalary}</p>
          <p className="text-gray-500 text-xs mt-1">Valores atípicos excluidos</p>
        </div>

        {/* 🤖 Opinión sobre IA */}
        <div className="col-span-2">
          <p className="text-gray-400 text-xs mb-2">Opinión sobre IA</p>
          {aiSentiments.length > 0 ? (
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={aiSentiments}>
                <CartesianGrid strokeDasharray="3 3" stroke="#333" />
                <XAxis dataKey="name" tick={{ fill: "#ccc", fontSize: 10 }} />
                <YAxis tick={{ fill: "#ccc", fontSize: 10 }} />
                <Tooltip />
                <Bar dataKey="value" fill="#3b82f6" />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-gray-500 text-xs text-center mt-6">Sin respuestas sobre IA</p>
          )}
        </div>

        {/* 💻 Lenguajes más usados */}
        <div className="col-span-2">
          <p className="text-gray-400 text-xs mb-2">Lenguajes más usados</p>
          {topLanguages.length > 0 ? (
            <ul className="text-xs grid grid-cols-2 gap-x-4 gap-y-1">
              {topLanguages.map((lang, i) => (
                <li key={i} className="flex justify-between border-b border-gray-800 pb-1">
                  <span>{lang.name}</span>
                  <span className="text-gray-400">{lang.value}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-gray-500 text-xs text-center mt-4">Sin datos de lenguajes</p>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
