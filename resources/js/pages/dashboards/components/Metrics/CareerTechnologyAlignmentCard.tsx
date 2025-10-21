import { Card, CardContent } from "@/components/ui/card";
import { Filter, FileSpreadsheet, FileText } from "lucide-react";
import { useState, useEffect, useCallback, useMemo } from "react";
import axios from "axios";
import CareerTechnologyFilters from "./CareerTechnologyFilters";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  Legend,
  LabelList,
  LineChart,
  Line,
  CartesianGrid,
} from "recharts";

export default function CareerTechnologyAlignmentCard() {
  const [showFilters, setShowFilters] = useState(false);
  const [metadata, setMetadata] = useState({ years: [], careers: [] });
  const [filters, setFilters] = useState({
    start_date: "",
    end_date: "",
    group_by: "week",
    careers: [] as number[],
  });
  const [visibleFields, setVisibleFields] = useState(["alineacion_tecnologias"]);
  const [data, setData] = useState<any[]>([]);
  const [trendData, setTrendData] = useState<any[]>([]);
  const [summary, setSummary] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  // Etiquetas y colores
  const fieldLabels: Record<string, string> = {
    alineacion_tecnologias: "Alineación total",
  };

  const fieldColors: Record<string, string> = {
    alineacion_tecnologias: "#f97316", // naranja ISIL
  };

  // Alternar campos visibles
  const toggleField = (field: string) => {
    setVisibleFields((prev) =>
      prev.includes(field)
        ? prev.filter((f) => f !== field)
        : [...prev, field]
    );
  };

  // Obtener datos
  const fetchData = useCallback(async () => {
    try {
      setLoading(true);

      const today = new Date();
      const start = filters.start_date
        ? new Date(filters.start_date)
        : new Date(today.getTime() - 90 * 24 * 60 * 60 * 1000);

      const queryParams = {
        ...filters,
        start_date: filters.start_date || start.toISOString().slice(0, 10),
        end_date: filters.end_date || today.toISOString().slice(0, 10),
      };

      const res = await axios.get("/api/ai/career-technology-alignment/data", {
        params: queryParams,
      });

      setData(res.data.results || []);
      setTrendData(res.data.trend_data || []);
      setSummary({
        avg_alignment: res.data.avg_alignment,
        total_careers: res.data.total_careers ?? 0,
        start_date: res.data.start_date,
        end_date: res.data.end_date,
      });
    } catch (err) {
      console.error("❌ Error al obtener datos", err);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  // Metadata inicial
  useEffect(() => {
    axios
      .get("/api/ai/career-technology-alignment/metadata")
      .then((res) => setMetadata(res.data));
  }, []);

  // Actualizar cuando cambian los filtros
  useEffect(() => {
    fetchData();
  }, [filters]);

  // Exportar
  const handleExport = (format: "excel" | "pdf") => {
    const query = new URLSearchParams({ ...filters, format });
    window.open(
      `/api/ai/career-technology-alignment/export?${query.toString()}`,
      "_blank"
    );
  };

  // Escala eje X (barras)
  const xDomain = useMemo(() => {
    if (data.length === 0) return [0, 100];
    const vals = data.map((d) => parseFloat(d.alineacion_tecnologias) || 0);
    const min = Math.max(Math.floor(Math.min(...vals) - 5), 0);
    const max = Math.min(Math.ceil(Math.max(...vals) + 5), 100);
    return [min, max];
  }, [data]);

  const isTemporal =
    filters.group_by === "week" || filters.group_by === "month";

  return (
    <Card className="bg-[#0d0d0d] text-white rounded-xl border border-gray-700 relative">
      <CardContent className="p-6 flex flex-col gap-4 relative">
        {/* 🔹 Header */}
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-base font-semibold text-white">
              ⚙️ Alineación de Carreras por Tecnologías
            </h2>
            <p className="text-xs text-gray-400">
              Basado en el modelo 4D (Presencia · Demanda · Alcance · Dinámica)
            </p>
            {summary?.start_date && (
              <p className="text-[11px] text-gray-500 mt-1">
                Periodo: {summary.start_date} → {summary.end_date}
              </p>
            )}
          </div>

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
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs text-gray-300 mt-2">
            <div className="bg-gray-900/60 rounded-lg p-2">
              <p>Promedio de Alineación</p>
              <p className="text-white font-semibold text-lg">
                {summary.avg_alignment ?? 0}%
              </p>
            </div>
            <div className="bg-gray-900/60 rounded-lg p-2">
              <p>Total de Carreras</p>
              <p className="text-white font-semibold text-lg">
                {summary.total_careers ?? 0}
              </p>
            </div>
          </div>
        )}

        {/* 🔹 Gráfico principal */}
        <div className="bg-gray-900/50 rounded-lg p-4 mt-2 h-[420px] overflow-x-auto scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-900">
          {loading ? (
            <div className="flex items-center justify-center h-full text-gray-400 text-sm">
              Cargando datos...
            </div>
          ) : isTemporal ? (
            // 📈 Vista temporal (líneas)
            <ResponsiveContainer width="100%" height={400}>
              <LineChart data={trendData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#333" />
                <XAxis dataKey="periodo" tick={{ fill: "#bbb", fontSize: 11 }} />
                <YAxis domain={[0, 100]} tick={{ fill: "#bbb", fontSize: 11 }} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "#1e1e1e",
                    border: "none",
                    borderRadius: "6px",
                  }}
                  formatter={(v: any) =>
                    isNaN(v) ? "—" : `${parseFloat(v).toFixed(1)}%`
                  }
                />
                <Legend verticalAlign="top" height={36} />
                {Object.keys(trendData[0] || {})
                  .filter((key) => key !== "periodo")
                  .map((career, i) => (
                    <Line
                      key={career}
                      type="monotone"
                      dataKey={career}
                      strokeWidth={2}
                      stroke={`hsl(${(i * 47) % 360}, 70%, 60%)`}
                      dot={false}
                      name={career}
                    />
                  ))}
              </LineChart>
            </ResponsiveContainer>
          ) : (
            // 📊 Vista snapshot (barras)
            <ResponsiveContainer width="100%" height={400}>
              <BarChart
                data={data}
                layout="vertical"
                margin={{ top: 20, right: 40, left: 160, bottom: 10 }}
                barCategoryGap="25%"
              >
                <XAxis
                  type="number"
                  domain={xDomain}
                  tick={{ fill: "#bbb", fontSize: 12 }}
                />
                <YAxis
                  type="category"
                  dataKey="career"
                  tick={{ fill: "#bbb", fontSize: 12 }}
                  width={180}
                />
                <Tooltip
                  formatter={(v: any, name: string) => {
                    const num = Number(v);
                    return [
                      isNaN(num) ? "—" : `${num.toFixed(2)}%`,
                      fieldLabels[name] || name,
                    ];
                  }}
                  labelFormatter={(label) => `Carrera: ${label}`}
                  contentStyle={{
                    backgroundColor: "#1e1e1e",
                    border: "none",
                    borderRadius: "6px",
                  }}
                />
                <Legend
                  wrapperStyle={{
                    fontSize: 11,
                    color: "#aaa",
                    marginBottom: 10,
                  }}
                  iconType="circle"
                  verticalAlign="top"
                />
                {visibleFields.map((field) => (
                  <Bar
                    key={field}
                    dataKey={field}
                    name={fieldLabels[field]}
                    barSize={20}
                    fill={fieldColors[field]}
                    radius={[4, 4, 4, 4]}
                  >
                    <LabelList
                      dataKey={field}
                      position="right"
                      formatter={(v) => {
                        const num = Number(v);
                        return isNaN(num) ? "—" : `${num.toFixed(1)}%`;
                      }}
                      fill="#fff"
                      fontSize={11}
                    />
                  </Bar>
                ))}
              </BarChart>
            </ResponsiveContainer>
          )}
        </div>

        {/* 🔹 Filtros laterales */}
        <CareerTechnologyFilters
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
