import { Card, CardContent } from "@/components/ui/card";
import { useEffect, useState } from "react";
import axios from "axios";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  LabelList,
} from "recharts";
import { Filter, Search, ChevronDown, ChevronUp, X } from "lucide-react";
import { useTranslation } from "react-i18next"; // ✅ i18n hook

export default function ProfileEducationCard() {
  const { t } = useTranslation(); // 🎯 Traducción activa
  const [data, setData] = useState<any[]>([]);
  const [metadata, setMetadata] = useState({
    years: [] as number[],
    countries: [] as string[],
    remote_work: [] as string[],
    employment: [] as string[],
    org_sizes: [] as string[],
    industries: [] as string[],
  });

  const [filters, setFilters] = useState({
    year: 2024,
    countries: [] as string[],
    remote_work: "",
    employment: "",
    org_size: "",
    industries: [] as string[],
  });

  const [search, setSearch] = useState({ country: "", industry: "" });
  const [showFilters, setShowFilters] = useState(false);
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({
    year: true,
    country: false,
    remote: false,
    employment: false,
    org: false,
    industry: false,
  });

  // === Cargar metadatos ===
  useEffect(() => {
    axios
      .get("/api/ai/stackoverflow/profile/education/metadata")
      .then((res) => setMetadata(res.data));
  }, []);

  // === Cargar datos ===
  const loadData = () => {
    axios
      .get("/api/ai/stackoverflow/profile/education", { params: filters })
      .then((res) => {
        const e = res.data.education_levels || [];
        setData(
          e.map((d: any) => ({
            name: t(d.ed_level || ""), // Traduce dinámicamente
            value: d.total,
          }))
        );
      });
  };

  useEffect(loadData, [filters]);

  // === Helper: alternar valores múltiples ===
  const toggleArrayFilter = (key: keyof typeof filters, value: string) => {
    setFilters((prev) => ({
      ...prev,
      [key]: (prev[key] as string[]).includes(value)
        ? (prev[key] as string[]).filter((v) => v !== value)
        : [...(prev[key] as string[]), value],
    }));
  };

  // === Helper: alternar valor único ===
  const handleRadio = (key: keyof typeof filters, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
  };

  // === Helper: colapsar secciones ===
  const toggleGroup = (key: string) => {
    setOpenGroups((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  // === Reset filtros ===
  const resetFilters = () => {
    setFilters({
      year: 2024,
      countries: [],
      remote_work: "",
      employment: "",
      org_size: "",
      industries: [],
    });
    setSearch({ country: "", industry: "" });
  };

  return (
    <Card className="bg-[#161616] border border-gray-700 text-white relative">
      <CardContent className="p-4">
        {/* === HEADER === */}
        <div className="flex justify-between items-center mb-2">
          <h3 className="font-semibold text-blue-400 text-sm">
            {t("Nivel educativo")}
          </h3>
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="p-1 bg-gray-800 rounded-full hover:bg-gray-700"
          >
            <Filter className="w-4 h-4 text-gray-300" />
          </button>
        </div>

        {/* === FILTROS === */}
        {showFilters && (
          <div className="absolute top-10 right-4 bg-[#1b1b1b] border border-gray-700 p-3 rounded-lg text-xs w-80 z-50 shadow-lg max-h-[440px] overflow-y-auto">
            <div className="flex justify-between items-center mb-2">
              <p className="font-semibold text-gray-200">{t("Filtros")}</p>
              <button onClick={() => setShowFilters(false)}>
                <X className="w-3 h-3 text-gray-400" />
              </button>
            </div>

            {[
              { key: "year", title: t("Año") },
              { key: "country", title: t("Países") },
              { key: "remote", title: t("Modalidad laboral") },
              { key: "employment", title: t("Tipo de empleo") },
              { key: "org", title: t("Tamaño de organización") },
              { key: "industry", title: t("Industrias") },
            ].map((sec) => (
              <div key={sec.key} className="mb-2 border-b border-gray-800 pb-1">
                <button
                  onClick={() => toggleGroup(sec.key)}
                  className="flex justify-between items-center w-full text-blue-400 font-semibold mb-1"
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
                        <label
                          key={y}
                          className="flex items-center gap-1 mb-1"
                        >
                          <input
                            type="radio"
                            checked={filters.year === y}
                            onChange={() => setFilters((f) => ({ ...f, year: y }))}
                          />
                          <span>{y}</span>
                        </label>
                      ))}

                    {/* === Países === */}
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
                        <div className="max-h-32 overflow-y-auto border border-gray-700 p-1 rounded">
                          {metadata.countries
                            .filter((c) =>
                              c
                                .toLowerCase()
                                .includes(search.country.toLowerCase())
                            )
                            .slice(0, 40)
                            .map((c) => (
                              <label
                                key={c}
                                className="flex items-center gap-1 mb-1"
                              >
                                <input
                                  type="checkbox"
                                  checked={filters.countries.includes(c)}
                                  onChange={() =>
                                    toggleArrayFilter("countries", c)
                                  }
                                />
                                <span>{c}</span>
                              </label>
                            ))}
                        </div>
                      </>
                    )}

                    {/* === Modalidad laboral === */}
                    {sec.key === "remote" &&
                      metadata.remote_work.map((r) => (
                        <label
                          key={r}
                          className="flex items-center gap-1 mb-1"
                        >
                          <input
                            type="radio"
                            checked={filters.remote_work === r}
                            onChange={() => handleRadio("remote_work", r)}
                          />
                          <span>{t(r)}</span>
                        </label>
                      ))}

                    {/* === Tipo de empleo === */}
                    {sec.key === "employment" &&
                      metadata.employment.map((e) => (
                        <label key={e} className="flex items-center gap-1 mb-1">
                          <input
                            type="radio"
                            checked={filters.employment === e}
                            onChange={() => handleRadio("employment", e)}
                          />
                          <span>{t(e)}</span>
                        </label>
                      ))}

                    {/* === Tamaño de organización === */}
                    {sec.key === "org" &&
                      metadata.org_sizes.map((o) => (
                        <label key={o} className="flex items-center gap-1 mb-1">
                          <input
                            type="radio"
                            checked={filters.org_size === o}
                            onChange={() => handleRadio("org_size", o)}
                          />
                          <span>{t(o)}</span>
                        </label>
                      ))}

                    {/* === Industrias === */}
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
                        <div className="max-h-32 overflow-y-auto border border-gray-700 p-1 rounded">
                          {metadata.industries
                            .filter((i) =>
                              i
                                .toLowerCase()
                                .includes(search.industry.toLowerCase())
                            )
                            .slice(0, 40)
                            .map((i) => (
                              <label
                                key={i}
                                className="flex items-center gap-1 mb-1"
                              >
                                <input
                                  type="checkbox"
                                  checked={filters.industries.includes(i)}
                                  onChange={() =>
                                    toggleArrayFilter("industries", i)
                                  }
                                />
                                <span>{i}</span>
                              </label>
                            ))}
                        </div>
                      </>
                    )}
                  </>
                )}
              </div>
            ))}

            {/* === BOTONES === */}
            <div className="flex justify-between mt-2 pt-2 border-t border-gray-800">
              <button
                onClick={() => {
                  resetFilters();
                  loadData();
                }}
                className="bg-gray-800 px-3 py-1 rounded text-gray-300 hover:bg-gray-700"
              >
                {t("Limpiar")}
              </button>
              <button
                onClick={() => {
                  loadData();
                  setShowFilters(false);
                }}
                className="bg-blue-600 px-3 py-1 rounded text-white hover:bg-blue-500"
              >
                {t("Aplicar")}
              </button>
            </div>
          </div>
        )}

        {/* === GRÁFICO === */}
        {data.length ? (
          <ResponsiveContainer width="100%" height={300}>
            <BarChart
              data={data}
              layout="vertical"
              margin={{ top: 10, right: 50, bottom: 10, left: 200 }}
            >
              <XAxis type="number" tick={{ fill: "#aaa", fontSize: 10 }} />
              <YAxis
                dataKey="name"
                type="category"
                tick={{ fill: "#ccc", fontSize: 10 }}
                width={260}
              />
              <Tooltip
                cursor={{ fill: "#1f1f1f" }}
                contentStyle={{
                  backgroundColor: "#1f1f1f",
                  border: "1px solid #333",
                  borderRadius: "6px",
                  color: "#fff",
                }}
                formatter={(value, name) => [value, name]}
              />
              <Bar dataKey="value" fill="#22c55e" radius={[0, 4, 4, 0]}>
                <LabelList
                  dataKey="value"
                  position="right"
                  style={{
                    fontSize: 11,
                    fill: "#22c55e",
                    textShadow: "0 0 2px #000",
                  }}
                  offset={8}
                />
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        ) : (
          <p className="text-gray-400 text-sm mt-6 text-center">
            {t("Sin datos")}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
