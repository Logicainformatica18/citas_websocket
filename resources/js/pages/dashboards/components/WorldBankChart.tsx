import { Card, CardContent } from "@/components/ui/card";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from "recharts";
import { useEffect, useState } from "react";
import { Filter, X, Search } from "lucide-react";
import axios from "axios";

type DataPoint = {
  year: number;
  country: string;
  value: number;
};

export default function WorldBankLineChart() {
  const [data, setData] = useState<DataPoint[]>([]);
  const [filters, setFilters] = useState({
    indicator: "IT.NET.USER.ZS",
    countries: ["PE", "CL", "BR"],
    from: 2020,
    to: 2025,
  });
  const [metadata, setMetadata] = useState({
    indicators: [] as { indicator_code: string;indicator_name_es:string, indicator_name: string }[],
    countries: [] as { country_code: string; country_name: string }[],
    years: [] as number[],
  });
  const [loading, setLoading] = useState(false);
  const [showFilters, setShowFilters] = useState(false);
  const [searchCountry, setSearchCountry] = useState("");
  const [searchIndicator, setSearchIndicator] = useState("");

  // 🎨 Paleta de colores suaves por país
  const colors = [
    "#3b82f6", "#22c55e", "#f59e0b", "#e11d48",
    "#8b5cf6", "#0ea5e9", "#f43f5e", "#10b981",
    "#a855f7", "#06b6d4",
  ];

  // 📦 Cargar metadata
  useEffect(() => {
    axios
      .get("/api/ai/worldbank/metadata")
      .then((res) => setMetadata(res.data))
      .catch((err) => console.error("❌ Error cargando metadata:", err.message));
  }, []);

  // 📈 Cargar datos
  useEffect(() => {
    const loadData = async () => {
      setLoading(true);
      try {
        const res = await axios.get("/api/ai/worldbank/get-data", {
          params: {
            indicator: filters.indicator,
            countries: filters.countries,
            from: filters.from,
            to: filters.to,
          },
        });

        const results = res.data.results || [];

        const formatted = results.map((r: any) => ({
          year: parseInt(r.year),
          country: r.country_name,
          value: Number(r.value),
        }));

        setData(formatted);
      } catch (err) {
        console.error("❌ Error obteniendo datos:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, [filters]);

  const toggleCountry = (code: string) => {
    setFilters((f) => ({
      ...f,
      countries: f.countries.includes(code)
        ? f.countries.filter((c) => c !== code)
        : [...f.countries, code],
    }));
  };

  const clearFilters = () =>
    setFilters({
      indicator: "IT.NET.USER.ZS",
      countries: [],
      from: 2020,
      to: 2025,
    });

  const CustomTooltip = ({ active, payload, label }: any) => {
    if (active && payload && payload.length) {
      return (
        <div className="bg-gray-900 text-gray-100 p-3 rounded-lg shadow-lg border border-gray-700 text-xs">
          <p className="font-semibold text-blue-400 mb-1">Año {label}</p>
          {payload.map((p: any, i: number) => (
            <p key={i}>
              {p.name}:{" "}
              <span className="text-white">{p.value?.toFixed(2)}</span>
            </p>
          ))}
        </div>
      );
    }
    return null;
  };

  // 🔧 Agrupar datos por año (necesario para Recharts)
  const years = [...new Set(data.map((d) => d.year))].sort((a, b) => a - b);
  const countries = [...new Set(data.map((d) => d.country))];
  const chartData = years.map((year) => {
    const entry: any = { year };
    countries.forEach((c) => {
      const point = data.find((d) => d.country === c && d.year === year);
      entry[c] = point ? point.value : null;
    });
    return entry;
  });

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700 relative">
      <CardContent className="p-6 relative">
        {/* HEADER */}
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide">
            Tendencia de Indicadores Globales (Banco Mundial)
          </h2>
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition"
          >
            <Filter className="w-4 h-4 text-gray-200" />
          </button>
        </div>

        {/* PANEL DE FILTROS */}
        {showFilters && (
          <div className="absolute right-6 top-12 bg-[#1a1a1a] border border-gray-700 rounded-lg shadow-xl p-4 z-[999] w-[900px] text-xs">
            <div className="flex justify-between items-center mb-2">
              <h3 className="font-semibold text-sm text-white">Filtros</h3>
              <button onClick={() => setShowFilters(false)}>
                <X className="w-4 h-4 text-gray-400 hover:text-white" />
              </button>
            </div>

            <div className="grid grid-cols-3 gap-4 max-h-[440px] overflow-y-auto pr-2">
              {/* Indicador */}
              <div>
                <p className="font-semibold text-blue-400 mb-2">Indicador:</p>
                <div className="relative mb-2">
                  <Search className="w-4 h-4 text-gray-400 absolute left-2 top-2.5" />
                  <input
                    type="text"
                    placeholder="Buscar indicador..."
                    value={searchIndicator}
                    onChange={(e) => setSearchIndicator(e.target.value)}
                    className="w-full bg-gray-800 border border-gray-600 rounded p-1 pl-7 text-gray-200"
                  />
                </div>
                <div className="max-h-80 overflow-y-auto border border-gray-700 rounded p-2">
                  {metadata.indicators
                    .filter((i) =>
                      i.indicator_name
                        .toLowerCase()
                        .includes(searchIndicator.toLowerCase())
                    )
                    .map((i) => (
                      <label key={i.indicator_code} className="flex items-center gap-1 mb-1">
                        <input
                          type="radio"
                          checked={filters.indicator === i.indicator_code}
                          onChange={() =>
                            setFilters((f) => ({
                              ...f,
                              indicator: i.indicator_code,
                            }))
                          }
                        />
                     <span>{i.indicator_name_es || i.indicator_name}</span>

                      </label>
                    ))}
                </div>
              </div>

              {/* Años (rango simple) */}
              <div>
                <p className="font-semibold text-blue-400 mb-2">Años:</p>
                <div className="grid grid-cols-2 gap-1 max-h-80 overflow-y-auto border border-gray-700 rounded p-2">
                  {metadata.years.map((y) => (
                    <label key={y} className="flex items-center gap-1">
                      <input
                        type="radio"
                        checked={filters.from === y}
                        onChange={() =>
                          setFilters((f) => ({ ...f, from: y, to: y + 5 }))
                        }
                      />
                      <span>{y}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* Países */}
              <div>
                <p className="font-semibold text-blue-400 mb-2">Países:</p>
                <div className="relative mb-2">
                  <Search className="w-4 h-4 text-gray-400 absolute left-2 top-2.5" />
                  <input
                    type="text"
                    placeholder="Buscar país..."
                    value={searchCountry}
                    onChange={(e) => setSearchCountry(e.target.value)}
                    className="w-full bg-gray-800 border border-gray-600 rounded p-1 pl-7 text-gray-200"
                  />
                </div>
                <div className="grid grid-cols-3 gap-1 max-h-80 overflow-y-auto border border-gray-700 rounded p-2">
                  {metadata.countries
                    .filter((c) =>
                      c.country_name
                        .toLowerCase()
                        .includes(searchCountry.toLowerCase())
                    )
                    .map((c) => (
                      <label key={c.country_code} className="flex items-center gap-1">
                        <input
                          type="checkbox"
                          checked={filters.countries.includes(c.country_code)}
                          onChange={() => toggleCountry(c.country_code)}
                        />
                        <span>{c.country_name}</span>
                      </label>
                    ))}
                </div>
              </div>
            </div>

            {/* BOTONES */}
            <div className="flex justify-between mt-3">
              <button
                onClick={clearFilters}
                className="text-gray-300 hover:text-white text-xs"
              >
                Limpiar
              </button>
              <button
                onClick={() => setShowFilters(false)}
                className="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-white text-xs"
              >
                Aplicar
              </button>
            </div>
          </div>
        )}

        {/* GRÁFICO */}
        {loading ? (
          <p className="text-center text-gray-400 mt-10">Cargando datos...</p>
        ) : (
          <ResponsiveContainer width="100%" height={400}>
            <LineChart data={chartData} margin={{ top: 20, right: 40, left: 0, bottom: 20 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#333" />
              <XAxis dataKey="year" tick={{ fill: "#9CA3AF", fontSize: 12 }} />
              <YAxis tick={{ fill: "#9CA3AF", fontSize: 12 }} />
              <Tooltip content={<CustomTooltip />} />
              <Legend wrapperStyle={{ color: "#ccc", fontSize: 12 }} />
              {countries.map((country, i) => (
                <Line
                  key={country}
                  type="monotone"
                  dataKey={country}
                  stroke={colors[i % colors.length]}
                  strokeWidth={2}
                  dot={false}
                  activeDot={{ r: 5 }}
                />
              ))}
            </LineChart>
          </ResponsiveContainer>
        )}
      </CardContent>
    </Card>
  );
}
