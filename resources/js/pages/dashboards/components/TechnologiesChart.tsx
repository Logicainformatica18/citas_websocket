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
import { Filter, X } from "lucide-react";
import axios from "axios";

type TechData = { name: string; value: number; color: string };

export default function TechnologiesChart() {
  const [data, setData] = useState<TechData[]>([]);
  const [filters, setFilters] = useState({
    year: [] as number[],
    language: [] as string[],
    source: ["github"] as string[],
    country: [] as string[],
  });
  const [options, setOptions] = useState({
    years: [] as number[],
    languages: [] as string[],
    sources: [] as string[],
    countries: {} as Record<string, string>,
  });
  const [loading, setLoading] = useState(false);
  const [showFilters, setShowFilters] = useState(false);

  const gradients = [
    { id: "python", from: "#06b6d4", to: "#3b82f6" },
    { id: "java", from: "#3b82f6", to: "#2563eb" },
    { id: "react", from: "#2563eb", to: "#1d4ed8" },
    { id: "javascript", from: "#1e40af", to: "#4338ca" },
    { id: "tensorflow", from: "#f97316", to: "#ea580c" },
  ];

  useEffect(() => {
    axios.get("/api/technologies/metadata").then((res) => setOptions(res.data));
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      const res = await axios.post("/api/technologies/data", { filters });
      const results = res.data.aggregations?.percent || {};
      const mapped: TechData[] = Object.entries(results).map(([name, value], i) => ({
        name,
        value: Number(value),
        color: `url(#${gradients[i % gradients.length].id})`,
      }));
      setData(mapped);
    } catch (err) {
      console.error("❌ Error cargando tecnologías:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [filters]);

  const toggleFilter = (type: string, value: string | number) => {
    setFilters((prev) => {
      const arr = prev[type as keyof typeof prev] as (string | number)[];
      return {
        ...prev,
        [type]: arr.includes(value)
          ? arr.filter((v) => v !== value)
          : [...arr, value],
      };
    });
  };

  const clearFilters = () =>
    setFilters({
      year: [],
      language: [],
      source: ["github"],
      country: [],
    });

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700 relative">
      <CardContent className="p-6 relative">
        {/* HEADER */}
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide">
            Tendencias Tecnológicas
          </h2>
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition"
          >
            <Filter className="w-4 h-4 text-gray-200" />
          </button>
        </div>

        {/* PANEL FLOTANTE DE FILTROS */}
        {showFilters && (
          <div className="absolute right-6 top-12 bg-[#1a1a1a] border border-gray-700 rounded-lg shadow-xl p-4 z-[999] w-80 text-xs">
            <div className="flex justify-between items-center mb-2">
              <h3 className="font-semibold text-sm text-white">Filtros</h3>
              <button onClick={() => setShowFilters(false)}>
                <X className="w-4 h-4 text-gray-400 hover:text-white" />
              </button>
            </div>

            <div className="max-h-[320px] overflow-y-auto pr-2">
              {/* Años */}
              <p className="font-semibold text-blue-400 mt-1">Años:</p>
              <div className="grid grid-cols-3 gap-1 mb-2">
                {options.years.map((y) => (
                  <label key={y} className="flex items-center gap-1">
                    <input
                      type="checkbox"
                      checked={filters.year.includes(y)}
                      onChange={() => toggleFilter("year", y)}
                    />
                    <span>{y}</span>
                  </label>
                ))}
              </div>

              {/* Lenguajes */}
              <p className="font-semibold text-blue-400 mt-2">Lenguajes:</p>
              <div className="grid grid-cols-2 gap-1 mb-2">
                {options.languages.slice(0, 20).map((lang) => (
                  <label key={lang} className="flex items-center gap-1">
                    <input
                      type="checkbox"
                      checked={filters.language.includes(lang)}
                      onChange={() => toggleFilter("language", lang)}
                    />
                    <span>{lang}</span>
                  </label>
                ))}
              </div>

              {/* Países */}
              <p className="font-semibold text-blue-400 mt-2">País:</p>
              <div className="grid grid-cols-2 gap-1 mb-2">
                {Object.entries(options.countries)
                  .slice(0, 16)
                  .map(([iso2, country]) => (
                    <label key={iso2} className="flex items-center gap-1">
                      <input
                        type="checkbox"
                        checked={filters.country.includes(iso2)}
                        onChange={() => toggleFilter("country", iso2)}
                      />
                      <span>{country}</span>
                    </label>
                  ))}
              </div>

              {/* Fuente */}
              <p className="font-semibold text-blue-400 mt-2">Fuente:</p>
              <select
                value={filters.source[0]}
                onChange={(e) =>
                  setFilters((f) => ({ ...f, source: [e.target.value] }))
                }
                className="w-full bg-gray-800 border border-gray-600 rounded p-1 mb-2"
              >
                {options.sources.map((src) => (
                  <option key={src} value={src}>
                    {src}
                  </option>
                ))}
              </select>
            </div>

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
          <ResponsiveContainer width="100%" height={data.length * 40 + 60}>
            <BarChart
              data={data}
              layout="vertical"
              margin={{ top: 0, right: 40, left: 0, bottom: 20 }}
            >
              <XAxis
                type="number"
                domain={[0, "dataMax + 5"]}
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
        )}
      </CardContent>
    </Card>
  );
}
