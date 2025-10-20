import { Card, CardContent } from "@/components/ui/card";
import { Filter, FileSpreadsheet, FileText } from "lucide-react";
import { useState, useEffect, useCallback } from "react";
import axios from "axios";
import CareerLanguageFilters from "./CareerLanguageFilters";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
} from "recharts";

export default function CareerLanguageAlignmentCard() {
  const [showFilters, setShowFilters] = useState(false);
  const [metadata, setMetadata] = useState({ years: [], careers: [] });
  const [filters, setFilters] = useState({
    year: new Date().getFullYear(),
    careers: [] as number[],
  });

  const [data, setData] = useState<any[]>([]);
  const [summary, setSummary] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  // 🔹 Obtener datos del backend
  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      const res = await axios.get("/api/ai/career-language-alignment/data", {
        params: filters,
      });

      setData(res.data.results || []);
      setSummary({
        avg_alignment: res.data.avg_alignment,
        total_careers: res.data.count,
      });
    } catch (err) {
      console.error("❌ Error al obtener datos", err);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  // 🔹 Cargar metadata
  useEffect(() => {
    axios
      .get("/api/ai/career-language-alignment/metadata")
      .then((res) => setMetadata(res.data));
  }, []);

  // 🔹 Actualizar al cambiar filtros
  useEffect(() => {
    fetchData();
  }, [filters]);

  // 🔹 Exportación
  const handleExport = (format: "excel" | "pdf") => {
    const query = new URLSearchParams({ ...filters, format });
    window.open(
      `/api/ai/career-language-alignment/export?${query.toString()}`,
      "_blank"
    );
  };

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700 relative">
      <CardContent className="p-6 flex flex-col gap-4 relative">
        {/* 🔹 Header */}
        <div className="flex justify-between items-center">
          <h2 className="text-sm font-semibold">
            🎓 Alineación de Carreras por Lenguajes
          </h2>
          <div className="flex gap-2">
            <button
              onClick={() => setShowFilters(!showFilters)}
              title="Filtros"
              className="bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition"
            >
              <Filter className="w-4 h-4 text-gray-200" />
            </button>

            <button
              onClick={() => handleExport("pdf")}
              title="Exportar PDF"
              className="bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition"
            >
              <FileText className="w-4 h-4 text-red-400" />
            </button>

            <button
              onClick={() => handleExport("excel")}
              title="Exportar Excel"
              className="bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition"
            >
              <FileSpreadsheet className="w-4 h-4 text-green-400" />
            </button>
          </div>
        </div>

        {/* 🔹 KPIs */}
        {summary && (
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs text-gray-300">
            <div className="bg-gray-900/60 rounded-lg p-2">
              <p>Promedio de Alineación</p>
              <p className="text-white font-semibold text-lg">
                {summary.avg_alignment}%
              </p>
            </div>
            <div className="bg-gray-900/60 rounded-lg p-2">
              <p>Total de Carreras</p>
              <p className="text-white font-semibold text-lg">
                {summary.total_careers}
              </p>
            </div>
          </div>
        )}

        {/* 🔹 Gráfico */}
        <div className="bg-gray-900/50 rounded-lg p-4 mt-2 h-[400px] overflow-x-auto scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-900">
          {loading ? (
            <div className="flex items-center justify-center h-full text-gray-400">
              Cargando datos...
            </div>
          ) : (
            <div style={{ minWidth: "600px" }}>
              <ResponsiveContainer width="100%" height={400}>
                <BarChart
                  data={data}
                  layout="vertical"
                  margin={{ top: 10, right: 20, left: 120, bottom: 10 }}
                >
                  <XAxis
                    type="number"
                    domain={[0, 100]}
                    tick={{ fill: "#ccc", fontSize: 12 }}
                  />
                  <YAxis
                    type="category"
                    dataKey="career"
                    tick={{ fill: "#ccc", fontSize: 12 }}
                    width={180}
                  />
                 <Tooltip
  formatter={(v: any) =>
    typeof v === "number"
      ? `${v.toFixed(2)}%`
      : v
        ? `${parseFloat(v).toFixed(2)}%`
        : "Sin datos"
  }
  contentStyle={{
    backgroundColor: "#1e1e1e",
    border: "none",
    borderRadius: "6px",
  }}
/>

                  <Bar
                    dataKey="language_alignment"
                    fill="#3b82f6"
                    name="Alineación (%)"
                    barSize={20}
                    radius={[4, 4, 4, 4]}
                  />
                </BarChart>
              </ResponsiveContainer>
            </div>
          )}
        </div>

        {/* 🔹 Filtros laterales */}
        <CareerLanguageFilters
          show={showFilters}
          onClose={() => setShowFilters(false)}
          metadata={metadata}
          filters={filters}
          setFilters={setFilters}
        />
      </CardContent>
    </Card>
  );
}
