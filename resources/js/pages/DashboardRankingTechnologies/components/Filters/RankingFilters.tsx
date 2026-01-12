import { router, usePage } from "@inertiajs/react";
import { useState } from "react";
import { Filter } from "lucide-react";

type PageProps = {
  filters?: {
    year?: number;
    period?: string;
    category?: string[];
  };
  availableCategories?: string[];
};

export default function RankingFilters() {
  const { filters, availableCategories } = usePage<PageProps>().props;

  /* =========================
     STATE LOCAL
  ========================= */
  const [year, setYear] = useState<number>(
    filters?.year ?? new Date().getFullYear()
  );
  const [period, setPeriod] = useState<string>(filters?.period ?? "s2");
  const [categories, setCategories] = useState<string[]>(
    filters?.category ?? []
  );

  const [isLoading, setIsLoading] = useState(false);

  /* =========================
     APPLY FILTERS
  ========================= */
  const applyFilters = () => {
    router.get(
      "/dashboard/ranking/technologies",
      {
        year,
        period,
        category: categories,
        page: 1,
      },
      {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onStart: () => setIsLoading(true),
        onFinish: () => setIsLoading(false),
      }
    );
  };

  /* =========================
     TOGGLE CATEGORY
  ========================= */
  const toggleCategory = (cat: string) => {
    setCategories((prev) =>
      prev.includes(cat)
        ? prev.filter((c) => c !== cat)
        : [...prev, cat]
    );
  };

  return (
    <div
      className="
        rounded-2xl border bg-white dark:bg-[#0F2A3A]
        dark:border-[#1E3A4A] p-4 space-y-4
      "
    >
      {/* HEADER */}
      <div className="flex items-center gap-2">
        <Filter className="h-4 w-4 text-[#1CBCE8]" />
        <h3 className="text-sm font-semibold uppercase tracking-wider">
          Filtros del ranking
        </h3>
      </div>

      {/* CONTROLS */}
      <div className="flex flex-wrap gap-6 items-end">
        {/* AÑO */}
        <div className="flex flex-col gap-1">
          <label className="text-xs uppercase text-gray-500">Año</label>
          <select
            value={year}
            onChange={(e) => setYear(Number(e.target.value))}
            className="rounded-lg border px-3 py-2 text-sm"
            disabled={isLoading}
          >
            {[2023, 2024, 2025].map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </select>
        </div>

        {/* PERIODO */}
        <div className="flex flex-col gap-1">
          <label className="text-xs uppercase text-gray-500">Periodo</label>
          <select
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            className="rounded-lg border px-3 py-2 text-sm"
            disabled={isLoading}
          >
            <option value="s1">Semestre 1</option>
            <option value="s2">Semestre 2</option>
          </select>
        </div>

        {/* CATEGORÍAS */}
        {availableCategories && (
          <div className="flex flex-wrap gap-2">
            {availableCategories.map((cat) => {
              const active = categories.includes(cat);

              return (
                <button
                  key={cat}
                  type="button"
                  disabled={isLoading}
                  onClick={() => toggleCategory(cat)}
                  className={`
                    px-3 py-1.5 rounded-full text-xs border transition
                    ${
                      active
                        ? "bg-[#1CBCE8] text-white"
                        : "bg-white text-gray-600"
                    }
                    disabled:opacity-50
                  `}
                >
                  {cat}
                </button>
              );
            })}
          </div>
        )}

        {/* APPLY BUTTON */}
        <button
          onClick={applyFilters}
          disabled={isLoading}
          className="
            ml-auto px-4 py-2 rounded-lg text-sm font-semibold
            bg-[#1CBCE8] text-white
            hover:opacity-90
            disabled:opacity-60
            flex items-center gap-2
          "
        >
          {isLoading && (
            <span
              className="
                h-4 w-4 rounded-full border-2
                border-white border-t-transparent
                animate-spin
              "
            />
          )}

          {isLoading ? "Aplicando…" : "Aplicar filtros"}
        </button>
      </div>
    </div>
  );
}
