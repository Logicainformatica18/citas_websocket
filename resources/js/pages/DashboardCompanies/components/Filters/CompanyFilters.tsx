import { router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import axios from "axios";
import { Search, X } from "lucide-react";

interface Props {
  filters: {
    year: number;
    period: "s1" | "s2";
    region?: string | null;
    country?: string | null;
    perPage: number;
  };
  regions: string[];
}

export default function CompanyFilters({ filters, regions }: Props) {
  const [countryQuery, setCountryQuery] = useState(filters.country ?? "");
  const [results, setResults] = useState<string[]>([]);
  const [open, setOpen] = useState(false);
  const [locked, setLocked] = useState(!!filters.country);

  const inputRef = useRef<HTMLInputElement | null>(null);

  /* ===================================================== */
  const updateFilter = (key: string, value: string | null) => {
    router.get(
      "/dashboard/indicators/companies",
      {
        ...filters,
        [key]: value || undefined,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  /* ===================================================== */
  const clearAllFilters = () => {
    router.get(
      "/dashboard/indicators/companies",
      {
        year: filters.year,
        period: filters.period,
        perPage: filters.perPage,
      },
      {
        preserveState: true,
        replace: true,
      }
    );

    setCountryQuery("");
    setLocked(false);
    setResults([]);
    setOpen(false);
  };

  /* ===================================================== */
  useEffect(() => {
    if (locked) {
      setOpen(false);
      return;
    }

    if (countryQuery.length < 2) {
      setResults([]);
      setOpen(false);
      return;
    }

    axios
      .get("/dashboard/indicators/companies/countries", {
        params: {
          q: countryQuery,
          region: filters.region,
        },
      })
      .then((res) => {
        setResults(res.data ?? []);
        setOpen(true);
      });
  }, [countryQuery, filters.region, locked]);

  return (
    <div className="rounded-2xl border bg-slate-50 p-5 shadow-sm dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
      <div className="flex items-center justify-between mb-4">
        <p className="text-sm font-semibold text-slate-700 dark:text-white">
          Filtros
        </p>

        {(filters.region || filters.country) && (
          <button
            onClick={clearAllFilters}
            className="text-xs font-semibold text-red-600 hover:underline"
          >
            Eliminar todos
          </button>
        )}
      </div>

      <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-4 items-end">

        {/* ===== REGIÓN ===== */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Región
          </label>
          <select
            value={filters.region ?? ""}
            onChange={(e) => {
              updateFilter("region", e.target.value || null);
              setCountryQuery("");
              setLocked(false);
            }}
            className="rounded-full border bg-white px-4 py-2 text-sm focus:ring-2 focus:ring-[#1CBCE8]/40 dark:bg-slate-900"
          >
            <option value="">Todas</option>
            {regions.map((r) => (
              <option key={r} value={r}>
                {r}
              </option>
            ))}
          </select>
        </div>

        {/* ===== PAÍS ===== */}
        <div className="relative flex flex-col gap-1">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            País
          </label>

          <div className="relative">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />

            <input
              ref={inputRef}
              value={countryQuery}
              onChange={(e) => {
                setCountryQuery(e.target.value);
                setLocked(false);
              }}
              placeholder="Buscar país…"
              className="w-full rounded-full border bg-white pl-9 pr-4 py-2 text-sm focus:ring-2 focus:ring-[#1CBCE8]/40 dark:bg-slate-900"
            />

            {filters.country && (
              <button
                onClick={() => {
                  updateFilter("country", null);
                  setCountryQuery("");
                  setLocked(false);
                }}
                className="absolute right-3 top-2.5 text-slate-400 hover:text-red-500"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>

          {open && results.length > 0 && (
            <div className="absolute z-30 top-full mt-2 w-full rounded-xl border bg-white shadow-xl dark:bg-slate-900">
              {results.map((c) => (
                <button
                  key={c}
                  type="button"
                  onClick={() => {
                    updateFilter("country", c);
                    setCountryQuery(c);
                    setLocked(true);
                    setResults([]);
                    setOpen(false);
                  }}
                  className="block w-full px-4 py-2 text-left text-sm hover:bg-[#E6F7FD] dark:hover:bg-[#123A52]"
                >
                  {c}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* ===== RESULTADOS ===== */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Resultados
          </label>
          <select
            value={filters.perPage}
            onChange={(e) => updateFilter("perPage", e.target.value)}
            className="rounded-full border bg-white px-4 py-2 text-sm focus:ring-2 focus:ring-[#1CBCE8]/40 dark:bg-slate-900"
          >
            {[7, 10, 20, 50].map((n) => (
              <option key={n} value={n}>
                {n} por página
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* ===== CHIPS ACTIVOS ===== */}
      {(filters.region || filters.country) && (
        <div className="flex flex-wrap gap-2 mt-4">
          {filters.region && (
            <Chip
              label={`Región: ${filters.region}`}
              onRemove={() => updateFilter("region", null)}
            />
          )}

          {filters.country && (
            <Chip
              label={`País: ${filters.country}`}
              onRemove={() => {
                updateFilter("country", null);
                setCountryQuery("");
                setLocked(false);
              }}
            />
          )}
        </div>
      )}
    </div>
  );
}

/* ===================================================== */
function Chip({ label, onRemove }: any) {
  return (
    <span className="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold bg-sky-100 text-sky-700 dark:bg-[#14384F] dark:text-[#7DD3FC]">
      {label}
      <button onClick={onRemove} className="hover:text-red-500">
        <X className="h-3 w-3" />
      </button>
    </span>
  );
}
