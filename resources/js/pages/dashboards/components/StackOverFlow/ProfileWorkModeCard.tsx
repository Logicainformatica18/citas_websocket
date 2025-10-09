import { Card, CardContent } from "@/components/ui/card";
import { useEffect, useState } from "react";
import axios from "axios";
import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts";
import { Filter, Search, X } from "lucide-react";
import { useTranslation } from "react-i18next";

const COLORS = ["#3b82f6", "#22c55e", "#eab308", "#ef4444"];

export default function ProfileWorkModeCard() {
  const { t } = useTranslation();
  const [data, setData] = useState<any[]>([]);
  const [metadata, setMetadata] = useState({
    years: [] as number[],
    countries: [] as string[],
    industries: [] as string[],
    ed_levels: [] as string[],
    employment: [] as string[],
  });

  const [filters, setFilters] = useState({
    year: [] ,
    countries: [] as string[],
    industries: [] as string[],
    ed_levels: [] as string[],
    employment: [] as string[],
  });

  const [searchCountry, setSearchCountry] = useState("");
  const [showFilters, setShowFilters] = useState(false);
  const [loading, setLoading] = useState(false);

  // 📦 Cargar metadata al iniciar
  useEffect(() => {
    axios
      .get("/api/ai/stackoverflow/profile/workmode/metadata")
      .then((res) => setMetadata(res.data))
      .catch((err) => console.error("❌ Error cargando metadata:", err));
  }, []);

  // 📈 Cargar datos cuando cambien filtros
  useEffect(() => {
    fetchData();
  }, [filters]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const res = await axios.get("/api/ai/stackoverflow/profile/workmode", {
        params: filters,
      });

      // el controlador ya devuelve [{ name, total, percent }]
      const work_modes = res.data.work_modes || [];
      setData(work_modes);
    } catch (err) {
      console.error("❌ Error obteniendo datos:", err);
    } finally {
      setLoading(false);
    }
  };

  // 🔄 Toggle checkbox genérico
  const toggleItem = (key: keyof typeof filters, value: string) => {
    setFilters((f) => ({
      ...f,
      [key]: f[key].includes(value)
        ? f[key].filter((v: string) => v !== value)
        : [...f[key], value],
    }));
  };

  return (
    <Card className="bg-[#161616] border border-gray-700 text-white relative">
      <CardContent className="p-4">
        {/* HEADER */}
        <div className="flex justify-between items-center mb-3">
          <h3 className="font-semibold text-blue-400 text-sm">
            {t("Modalidad laboral")}
          </h3>
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="p-2 bg-gray-800 rounded-full hover:bg-gray-700 transition"
          >
            <Filter className="w-4 h-4 text-gray-200" />
          </button>
        </div>

        {/* PANEL DE FILTROS */}
        {showFilters && (
          <div className="absolute top-12 right-4 bg-[#1a1a1a] border border-gray-700 rounded-lg shadow-xl p-4 text-xs w-[320px] z-[999] max-h-[520px] overflow-y-auto">
            <div className="flex justify-between items-center mb-2">
              <h3 className="font-semibold text-white">{t("Filtros")}</h3>
              <button onClick={() => setShowFilters(false)}>
                <X className="w-4 h-4 text-gray-400 hover:text-white" />
              </button>
            </div>

            {/* Año */}
            <div className="mb-3 border-b border-gray-700 pb-2">
              <p className="text-blue-400 font-semibold mb-1">{t("Año")}</p>
              <div className="grid grid-cols-3 gap-1">
                {metadata.years.map((y) => (
                  <label key={y} className="flex items-center gap-1">
                    <input
                      type="radio"
                      checked={filters.year === y}
                      onChange={() => setFilters((f) => ({ ...f, year: y }))}
                    />
                    <span>{y}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Países */}
            <details open className="mb-3">
              <summary className="cursor-pointer text-blue-400 font-semibold mb-1">
                {t("Países")}
              </summary>
              <div className="relative mb-2">
                <Search className="w-4 h-4 text-gray-400 absolute left-2 top-2.5" />
                <input
                  type="text"
                  placeholder={t("Buscar país...")}
                  value={searchCountry}
                  onChange={(e) => setSearchCountry(e.target.value)}
                  className="w-full bg-gray-800 border border-gray-600 rounded p-1 pl-7 text-gray-200"
                />
              </div>
              <div className="max-h-28 overflow-y-auto border border-gray-700 rounded p-2">
                {metadata.countries
                  .filter((c) =>
                    c.toLowerCase().includes(searchCountry.toLowerCase())
                  )
                  .map((c) => (
                    <label key={c} className="flex items-center gap-1 mb-1">
                      <input
                        type="checkbox"
                        checked={filters.countries.includes(c)}
                        onChange={() => toggleItem("countries", c)}
                      />
                      <span>{c}</span>
                    </label>
                  ))}
              </div>
            </details>
          </div>
        )}

        {/* GRAFICO */}
        {loading ? (
          <p className="text-gray-400 text-center mt-10">{t("Cargando...")}</p>
        ) : data.length > 0 ? (
          <div className="relative">
            <ResponsiveContainer width="100%" height={280}>
              <PieChart>
                <Pie
                  data={data}
                  dataKey="total"
                  nameKey="name"
                  cx="50%"
                  cy="50%"
                  outerRadius={100}
                  labelLine={false}
                  label={({ cx, cy, midAngle, innerRadius, outerRadius, index }) => {
                    const RADIAN = Math.PI / 180;
                    const radius = innerRadius + (outerRadius - innerRadius) * 0.55;
                    const x = cx + radius * Math.cos(-midAngle * RADIAN);
                    const y = cy + radius * Math.sin(-midAngle * RADIAN);
                    const color = COLORS[index % COLORS.length];
                    const percent = data[index].percent;

                    return (
                      <text
                        x={x}
                        y={y}
                        fill={color === "#eab308" ? "#000" : "#fff"}
                        textAnchor="middle"
                        dominantBaseline="central"
                        fontSize={12}
                        fontWeight="bold"
                      >
                        {`${percent}%`}
                      </text>
                    );
                  }}
                >
                  {data.map((_, i) => (
                    <Cell key={i} fill={COLORS[i % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip
                  formatter={(v: number, name: string, entry: any) => [
                    `${v.toLocaleString()} (${entry.payload.percent}%)`,
                    entry.name,
                  ]}
                />
                <Legend />
              </PieChart>
            </ResponsiveContainer>

            {/* 🔹 Total de respuestas debajo del gráfico */}
            <div className="text-center mt-2 text-sm text-gray-400">
              Total:{" "}
              <span className="text-gray-200 font-semibold">
                {data
                  .reduce((sum, d) => sum + d.total, 0)
                  .toLocaleString()}{" "}
              </span>
              respuestas
            </div>
          </div>
        ) : (
          <p className="text-gray-500 text-sm text-center mt-10">
            {t("No hay datos disponibles")}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
