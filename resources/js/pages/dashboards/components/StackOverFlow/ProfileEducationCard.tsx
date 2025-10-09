import { Card, CardContent } from "@/components/ui/card";
import { useEffect, useState } from "react";
import axios from "axios";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";
import { Filter, X } from "lucide-react";

export default function ProfileEducationCard() {
  const [data, setData] = useState<any[]>([]);
  const [metadata, setMetadata] = useState({ years: [], countries: [] });
  const [filters, setFilters] = useState({ year: 2024, countries: [] as string[] });
  const [showFilters, setShowFilters] = useState(false);

  useEffect(() => {
    axios.get("/api/ai/stackoverflow/profile/education/metadata").then((res) => setMetadata(res.data));
  }, []);

  useEffect(() => {
    axios
      .get("/api/ai/stackoverflow/profile/education", { params: filters })
      .then((res) => {
        const e = res.data.education_levels || {};
        setData(Object.entries(e).map(([name, value]) => ({ name, value })));
      });
  }, [filters]);

  const toggleCountry = (c: string) =>
    setFilters((f) => ({
      ...f,
      countries: f.countries.includes(c)
        ? f.countries.filter((x) => x !== c)
        : [...f.countries, c],
    }));

  return (
    <Card className="bg-[#161616] border border-gray-700 text-white relative">
      <CardContent className="p-4">
        <div className="flex justify-between items-center mb-2">
          <h3 className="font-semibold text-blue-400 text-sm">Nivel educativo</h3>
          <button onClick={() => setShowFilters(!showFilters)} className="p-1 bg-gray-800 rounded-full">
            <Filter className="w-4 h-4 text-gray-300" />
          </button>
        </div>

        {showFilters && (
          <div className="absolute top-10 right-4 bg-[#1b1b1b] border border-gray-700 p-3 rounded-lg text-xs w-64 z-50">
            <div className="flex justify-between items-center mb-2">
              <p className="font-semibold">Filtros</p>
              <button onClick={() => setShowFilters(false)}>
                <X className="w-3 h-3 text-gray-400" />
              </button>
            </div>

            <div className="mb-2">
              <p className="text-blue-400 font-semibold mb-1">Año</p>
              {metadata.years.map((y: number) => (
                <label key={y} className="flex items-center gap-1 mb-1">
                  <input
                    type="radio"
                    checked={filters.year === y}
                    onChange={() => setFilters((f) => ({ ...f, year: y }))}
                  />
                  <span>{y}</span>
                </label>
              ))}
            </div>

            <div>
              <p className="text-blue-400 font-semibold mb-1">Países</p>
              <div className="max-h-40 overflow-y-auto border border-gray-700 p-1 rounded">
                {metadata.countries.slice(0, 20).map((c: string) => (
                  <label key={c} className="flex items-center gap-1 mb-1">
                    <input
                      type="checkbox"
                      checked={filters.countries.includes(c)}
                      onChange={() => toggleCountry(c)}
                    />
                    <span>{c}</span>
                  </label>
                ))}
              </div>
            </div>
          </div>
        )}

        {data.length ? (
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={data} layout="vertical" margin={{ top: 10, right: 20, bottom: 10, left: 120 }}>
              <XAxis type="number" tick={{ fill: "#aaa" }} />
              <YAxis dataKey="name" type="category" tick={{ fill: "#aaa", fontSize: 9 }} width={200} />
              <Tooltip />
              <Bar dataKey="value" fill="#22c55e" />
            </BarChart>
          </ResponsiveContainer>
        ) : (
          <p className="text-gray-400 text-sm mt-6 text-center">Sin datos</p>
        )}
      </CardContent>
    </Card>
  );
}
