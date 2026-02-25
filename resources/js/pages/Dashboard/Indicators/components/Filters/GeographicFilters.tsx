import { useEffect, useRef, useState } from "react";
import axios from "axios";

type Filters = {
  region?: string | null;
  country?: string | null;
  city?: string | null;
};

interface Props {
  filters: Filters;
  onChange: (filters: Partial<Filters>) => void;
  onClear: () => void;
}

export default function GeographicFilters({
  filters,
  onChange,
  onClear,
}: Props) {
  const [regionQuery, setRegionQuery] = useState(filters.region ?? "");
  const [countryQuery, setCountryQuery] = useState(filters.country ?? "");
  const [cityQuery, setCityQuery] = useState(filters.city ?? "");

  const [regions, setRegions] = useState<string[]>([]);
  const [countries, setCountries] = useState<string[]>([]);
  const [cities, setCities] = useState<string[]>([]);

  const [open, setOpen] = useState<"region" | "country" | "city" | null>(null);

  const containerRef = useRef<HTMLDivElement>(null);

  /* ===============================
     Sync externo → inputs
  =============================== */
  useEffect(() => setRegionQuery(filters.region ?? ""), [filters.region]);
  useEffect(() => setCountryQuery(filters.country ?? ""), [filters.country]);
  useEffect(() => setCityQuery(filters.city ?? ""), [filters.city]);

  /* ===============================
     Click fuera (UNO SOLO)
  =============================== */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (!containerRef.current?.contains(e.target as Node)) {
        setOpen(null);
      }
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* ===============================
     Fetchers
  =============================== */
  const fetchRegions = (q = "") =>
    axios
      .get("/dashboard/indicadores/modalidad-laboral/regions", { params: { q } })
      .then((r) => setRegions(r.data))
      .catch(() => setRegions([]));

  const fetchCountries = (q = "") => {
    if (!filters.region) return;
    axios
      .get("/dashboard/indicadores/modalidad-laboral/countries", {
        params: { q, region: filters.region },
      })
      .then((r) => setCountries(r.data))
      .catch(() => setCountries([]));
  };

  const fetchCities = (q = "") => {
    if (!filters.country) return;
    axios
      .get("/dashboard/indicadores/modalidad-laboral/cities", {
        params: { q, country: filters.country },
      })
      .then((r) => setCities(r.data))
      .catch(() => setCities([]));
  };

  /* ===============================
     Debounce
  =============================== */
  useEffect(() => {
    if (open === "region") {
      const t = setTimeout(() => fetchRegions(regionQuery), 250);
      return () => clearTimeout(t);
    }
  }, [regionQuery, open]);

  useEffect(() => {
    if (open === "country") {
      const t = setTimeout(() => fetchCountries(countryQuery), 250);
      return () => clearTimeout(t);
    }
  }, [countryQuery, open, filters.region]);

  useEffect(() => {
    if (open === "city") {
      const t = setTimeout(() => fetchCities(cityQuery), 250);
      return () => clearTimeout(t);
    }
  }, [cityQuery, open, filters.country]);

  /* ===============================
     Render
  =============================== */
 return (
  <div
    ref={containerRef}
    className="
      rounded-2xl border p-5 shadow-sm
      bg-white dark:bg-slate-900
      border-slate-200 dark:border-slate-800
    "
  >
    {/* HEADER */}
    <div className="flex items-center justify-between mb-4">
      <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
        Filtros geográficos
      </span>

      {(filters.region || filters.country || filters.city) && (
        <button
          onClick={onClear}
          className="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline"
        >
          Limpiar filtros
        </button>
      )}
    </div>

    <div className="grid gap-5 md:grid-cols-3">

      {/* ================= REGIÓN ================= */}
      <div className="relative">
        <label className="text-xs font-semibold text-slate-600 dark:text-slate-400">
          Región
        </label>

        <input
          value={regionQuery}
          onFocus={() => {
            setOpen("region");
            fetchRegions(regionQuery);
          }}
          onChange={(e) => setRegionQuery(e.target.value)}
          className="
            mt-1 w-full rounded-lg border px-3 py-2 text-sm
            bg-white dark:bg-slate-800
            border-slate-300 dark:border-slate-700
            text-slate-700 dark:text-slate-200
            focus:ring-2 focus:ring-sky-500 focus:border-sky-500
            outline-none transition
          "
        />

        {open === "region" && regions.length > 0 && (
          <div
            className="
              absolute z-20 mt-1 w-full rounded-lg border shadow-lg
              bg-white dark:bg-slate-800
              border-slate-200 dark:border-slate-700
              max-h-60 overflow-y-auto
            "
          >
            {regions.map((r) => (
              <button
                key={r}
                onMouseDown={(e) => {
                  e.preventDefault();
                  e.stopPropagation();

                  setRegionQuery(r);
                  setCountryQuery("");
                  setCityQuery("");

                  onChange({ region: r, country: null, city: null });
                  setOpen(null);
                }}
                className="
                  block w-full px-3 py-2 text-left text-sm
                  text-slate-700 dark:text-slate-200
                  hover:bg-slate-100 dark:hover:bg-slate-700
                  transition
                "
              >
                {r}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* ================= PAÍS ================= */}
      <div className="relative">
        <label className="text-xs font-semibold text-slate-600 dark:text-slate-400">
          País
        </label>

        <input
          value={countryQuery}
          disabled={!filters.region}
          onFocus={() => {
            if (!filters.region) return;
            setOpen("country");
            fetchCountries(countryQuery);
          }}
          onChange={(e) => setCountryQuery(e.target.value)}
          className="
            mt-1 w-full rounded-lg border px-3 py-2 text-sm
            bg-white dark:bg-slate-800
            border-slate-300 dark:border-slate-700
            text-slate-700 dark:text-slate-200
            disabled:bg-slate-100 dark:disabled:bg-slate-800/40
            disabled:text-slate-400 dark:disabled:text-slate-600
            focus:ring-2 focus:ring-sky-500 focus:border-sky-500
            outline-none transition
          "
        />

        {open === "country" && countries.length > 0 && (
          <div
            className="
              absolute z-20 mt-1 w-full rounded-lg border shadow-lg
              bg-white dark:bg-slate-800
              border-slate-200 dark:border-slate-700
              max-h-60 overflow-y-auto
            "
          >
            {countries.map((c) => (
              <button
                key={c}
                onMouseDown={(e) => {
                  e.preventDefault();
                  e.stopPropagation();

                  setCountryQuery(c);
                  setCityQuery("");

                  onChange({ country: c, city: null });
                  setOpen(null);
                }}
                className="
                  block w-full px-3 py-2 text-left text-sm
                  text-slate-700 dark:text-slate-200
                  hover:bg-slate-100 dark:hover:bg-slate-700
                  transition
                "
              >
                {c}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* ================= CIUDAD ================= */}
      <div className="relative">
        <label className="text-xs font-semibold text-slate-600 dark:text-slate-400">
          Ciudad
        </label>

        <input
          value={cityQuery}
          disabled={!filters.country}
          onFocus={() => {
            if (!filters.country) return;
            setOpen("city");
            fetchCities(cityQuery);
          }}
          onChange={(e) => setCityQuery(e.target.value)}
          className="
            mt-1 w-full rounded-lg border px-3 py-2 text-sm
            bg-white dark:bg-slate-800
            border-slate-300 dark:border-slate-700
            text-slate-700 dark:text-slate-200
            disabled:bg-slate-100 dark:disabled:bg-slate-800/40
            disabled:text-slate-400 dark:disabled:text-slate-600
            focus:ring-2 focus:ring-sky-500 focus:border-sky-500
            outline-none transition
          "
        />

        {open === "city" && cities.length > 0 && (
          <div
            className="
              absolute z-20 mt-1 w-full rounded-lg border shadow-lg
              bg-white dark:bg-slate-800
              border-slate-200 dark:border-slate-700
              max-h-60 overflow-y-auto
            "
          >
            {cities.map((c) => (
              <button
                key={c}
                onMouseDown={(e) => {
                  e.preventDefault();
                  e.stopPropagation();

                  setCityQuery(c);
                  onChange({ city: c });
                  setOpen(null);
                }}
                className="
                  block w-full px-3 py-2 text-left text-sm
                  text-slate-700 dark:text-slate-200
                  hover:bg-slate-100 dark:hover:bg-slate-700
                  transition
                "
              >
                {c}
              </button>
            ))}
          </div>
        )}
      </div>

    </div>
  </div>
);
}