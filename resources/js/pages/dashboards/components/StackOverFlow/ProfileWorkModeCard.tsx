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
import { Filter, Search, X, ChevronDown, ChevronUp } from "lucide-react";
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
    year: 2024,
    countries: [] as string[],
    industries: [] as string[],
    ed_levels: [] as string[],
    employment: [] as string[],
  });

  const [search, setSearch] = useState({
    country: "",
    industry: "",
  });

  const [showFilters, setShowFilters] = useState(false);
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({
    year: true,
    country: false,
    industry: false,
    education: false,
    employment: false,
  });
  const [loading, setLoading] = useState(false);

  // === METADATA ===
  useEffect(() => {
    axios
      .get("/api/ai/stackoverflow/profile/workmode/metadata")
      .then((res) => setMetadata(res.data))
      .catch((err) => console.error("❌ Error metadata:", err));
  }, []);

  // === FETCH DATA ===
  useEffect(() => {
    fetchData();
  }, [filters]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const res = await axios.get("/api/ai/stackoverflow/profile/workmode", {
        params: filters,
      });
      const work_modes = res.data.work_modes || [];
      setData(work_modes);
    } catch (err) {
      console.error("❌ Error obteniendo datos:", err);
    } finally {
      setLoading(false);
    }
  };

  // === UTILS ===
  const toggleArray = (key: keyof typeof filters, value: string) => {
    setFilters((f) => ({
      ...f,
      [key]: f[key].includes(value)
        ? f[key].filter((v) => v !== value)
        : [...f[key], value],
    }));
  };

  const toggleGroup = (key: string) => {
    setOpenGroups((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const resetFilters = () => {
    setFilters({
      year: 2024,
      countries: [],
      industries: [],
      ed_levels: [],
      employment: [],
    });
    setSearch({ country: "", industry: "" });
  };

  // === RENDER ===
  return (
    <Card className="bg-[#161616] border border-gray-700 text-white relative">
      <CardContent className="p-4">
        {/* === HEADER === */}
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

        {/* === FILTROS === */}
        {showFilters && (
          <div className="absolute top-12 right-4 bg-[#1a1a1a] border border-gray-700 rounded-lg shadow-xl p-4 text-xs w-[320px] z-[999] max-h-[520px] overflow-y-auto">
            <div className="flex justify-between items-center mb-2">
              <h3 className="font-semibold text-white">{t("Filtros")}</h3>
              <button onClick={() => setShowFilters(false)}>
                <X className="w-4 h-4 text-gray-400 hover:text-white" />
              </button>
            </div>

            {[
              { key: "year", title: t("Año") },
              { key: "country", title: t("Países") },
              { key: "industry", title: t("Industrias") },
              { key: "education", title: t("Nivel educativo") },
              { key: "employment", title: t("Tipo de empleo") },
            ].map((sec) => (
              <div key={sec.key} className="mb-3 border-b border-gray-700 pb-2">
                <button
                  onClick={() => toggleGroup(sec.key)}
                  className="flex justify-between w-full items-center text-blue-400 font-semibold mb-1"
                >
                  {sec.title}
                  {openGroups[sec.key] ? (
                    <ChevronUp className="w-3 h-3" />
                  ) : (
                    <ChevronDown className="w-3 h-3" />
                  )}
                </button>

                {openGroups[sec.key] && (
                  <>
                    {/* === Año === */}
                    {sec.key === "year" &&
                      metadata.years.map((y) => (
                        <label key={y} className="flex items-center gap-1">
                          <input
                            type="radio"
                            checked={filters.year === y}
                            onChange={() => setFilters((f) => ({ ...f, year: y }))}
                          />
                          <span>{y}</span>
                        </label>
                      ))}

                    {/* === País === */}
                    {sec.key === "country" && (
                      <>
                        <div className="flex items-center gap-1 mb-1 bg-gray-800 rounded px-1">
                          <Search className="w-3 h-3 text-gray-400" />
                          <input
                            type="text"
                            placeholder={t("Buscar país...")}
                            value={search.country}
                            onChange={(e) =>
                              setSearch({ ...search, country: e.target.value })
                            }
                            className="bg-transparent w-full text-gray-200 text-xs outline-none"
                          />
                        </div>
                        <div className="max-h-28 overflow-y-auto border border-gray-700 rounded p-2">
                          {metadata.countries
                            .filter((c) =>
                              c
                                .toLowerCase()
                                .includes(search.country.toLowerCase())
                            )
                            .map((c) => (
                              <label key={c} className="flex items-center gap-1">
                                <input
                                  type="checkbox"
                                  checked={filters.countries.includes(c)}
                                  onChange={() => toggleArray("countries", c)}
                                />
                                <span>{c}</span>
                              </label>
                            ))}
                        </div>
                      </>
                    )}

                    {/* === Industria === */}
                    {sec.key === "industry" && (
                      <>
                        <div className="flex items-center gap-1 mb-1 bg-gray-800 rounded px-1">
                          <Search className="w-3 h-3 text-gray-400" />
                          <input
                            type="text"
                            placeholder={t("Buscar industria...")}
                            value={search.industry}
                            onChange={(e) =>
                              setSearch({ ...search, industry: e.target.value })
                            }
                            className="bg-transparent w-full text-gray-200 text-xs outline-none"
                          />
                        </div>
                        <div className="max-h-28 overflow-y-auto border border-gray-700 rounded p-2">
                          {metadata.industries
                            .filter((i) =>
                              i
                                .toLowerCase()
                                .includes(search.industry.toLowerCase())
                            )
                            .map((i) => (
                              <label key={i} className="flex items-center gap-1">
                                <input
                                  type="checkbox"
                                  checked={filters.industries.includes(i)}
                                  onChange={() => toggleArray("industries", i)}
                                />
                                <span>{i}</span>
                              </label>
                            ))}
                        </div>
                      </>
                    )}

                    {/* === Nivel educativo === */}
                    {sec.key === "education" &&
                      metadata.ed_levels.map((lvl) => (
                        <label key={lvl} className="flex items-center gap-1 mb-1">
                          <input
                            type="checkbox"
                            checked={filters.ed_levels.includes(lvl)}
                            onChange={() => toggleArray("ed_levels", lvl)}
                          />
                          <span>{t(lvl)}</span>
                        </label>
                      ))}

                    {/* === Tipo de empleo === */}
                    {sec.key === "employment" &&
                      metadata.employment.map((e) => (
                        <label key={e} className="flex items-center gap-1 mb-1">
                          <input
                            type="checkbox"
                            checked={filters.employment.includes(e)}
                            onChange={() => toggleArray("employment", e)}
                          />
                          <span>{t(e)}</span>
                        </label>
                      ))}
                  </>
                )}
              </div>
            ))}

            {/* === BOTONES === */}
            <div className="flex justify-between mt-3 pt-2 border-t border-gray-700">
              <button
                onClick={() => {
                  resetFilters();
                  fetchData();
                }}
                className="bg-gray-800 px-3 py-1 rounded text-gray-300 hover:bg-gray-700"
              >
                {t("Limpiar")}
              </button>
              <button
                onClick={() => {
                  fetchData();
                  setShowFilters(false);
                }}
                className="bg-blue-600 px-3 py-1 rounded text-white hover:bg-blue-500"
              >
                {t("Aplicar")}
              </button>
            </div>
          </div>
        )}

        {/* === GRAFICO === */}
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
                    const radius =
                      innerRadius + (outerRadius - innerRadius) * 0.55;
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
                    t(name),
                  ]}
                />
                <Legend />
              </PieChart>
            </ResponsiveContainer>

            {/* 🔹 Total respuestas */}
            <div className="text-center mt-2 text-sm text-gray-400">
              {t("Total")}:{" "}
              <span className="text-gray-200 font-semibold">
                {data.reduce((sum, d) => sum + d.total, 0).toLocaleString()}
              </span>{" "}
              {t("respuestas")}
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
