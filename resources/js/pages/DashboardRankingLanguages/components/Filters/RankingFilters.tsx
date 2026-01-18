import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { ChevronDown, X } from "lucide-react";

/* =====================================================
   Constantes – Lenguajes
===================================================== */
const RANKING_TYPES = [
  { value: "all", label: "Ranking general" },
  { value: "language", label: "Lenguajes ISIL" },
  { value: "trend", label: "Lenguajes en tendencia" },
];

/* =====================================================
   Component
===================================================== */
export default function RankingFilters() {
  const {
    filters,
    availableTrendCategories,
  } = usePage().props as any;

  const [openType, setOpenType] = useState(false);
  const typeRef = useRef<HTMLDivElement>(null);

  const activeRankingType = filters.ranking_type ?? "all";
  const isTrendOnly = activeRankingType === "trend";

  /* =========================================
     Cerrar combos al hacer click afuera
  ========================================= */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (typeRef.current && !typeRef.current.contains(e.target as Node)) {
        setOpenType(false);
      }
    };

    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* =========================================
     Navegación centralizada – LENGUAJES
  ========================================= */
  const navigate = (next: Record<string, any>, resetPage = false) => {
    const payload: any = {
      ...filters,
      ...next,
    };

    if (!payload.trend_category) delete payload.trend_category;

    router.get(
      "/dashboard/ranking/languages",
      {
        ...payload,
        ...(resetPage ? { page: 1 } : {}),
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  const rankingTypeLabel =
    RANKING_TYPES.find(t => t.value === activeRankingType)?.label ??
    "Ranking general";

  /* =========================================
     Render
  ========================================= */
  return (
    <div className="space-y-4">
      {/* =========================
          CONTROLES
      ========================= */}
      <div className="flex flex-wrap gap-4">

        {/* ===== TIPO DE RANKING ===== */}
        <div ref={typeRef} className="relative w-72">
          <button
            onClick={() => setOpenType(!openType)}
            className="
              w-full rounded-xl border px-4 py-2
              text-left flex items-center justify-between
              bg-white text-gray-700 border-gray-300
              hover:border-[#1CBCE8]
              dark:bg-[#0F2A3A]
              dark:text-slate-200
              dark:border-[#1E3A4A]
            "
          >
            <span className="text-sm">{rankingTypeLabel}</span>
            <ChevronDown className="h-4 w-4 text-gray-400" />
          </button>

          {openType && (
            <div className="
              absolute z-20 mt-2 w-full
              rounded-xl border shadow-lg
              bg-white border-gray-200
              dark:bg-[#0F2A3A]
              dark:border-[#1E3A4A]
            ">
              {RANKING_TYPES.map(type => (
                <button
                  key={type.value}
                  onClick={() => {
                    navigate(
                      {
                        ranking_type: type.value,
                        trend_category: null,
                      },
                      true
                    );
                    setOpenType(false);
                  }}
                  className={`
                    w-full px-4 py-2 text-left text-sm
                    hover:bg-sky-50 dark:hover:bg-[#14384F]
                    ${
                      activeRankingType === type.value
                        ? "font-semibold text-[#1CBCE8]"
                        : "text-gray-700 dark:text-slate-200"
                    }
                  `}
                >
                  {type.label}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* ===== CATEGORÍA DE TENDENCIAS ===== */}
        {isTrendOnly && (
          <div className="w-72">
            <select
              value={filters.trend_category ?? ""}
              onChange={e =>
                navigate(
                  { trend_category: e.target.value || null },
                  true
                )
              }
              className="
                w-full rounded-xl border px-4 py-2
                bg-white text-gray-700
                dark:bg-[#0F2A3A]
                dark:text-slate-200
                dark:border-[#1E3A4A]
              "
            >
              <option value="">Todas las tendencias</option>
              {availableTrendCategories.map((c: string) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </div>
        )}
      </div>

      {/* =========================
          CHIPS ACTIVOS
      ========================= */}
      <div className="flex flex-wrap gap-2">
        {activeRankingType !== "all" && (
          <Chip
            label={`Tipo: ${rankingTypeLabel}`}
            onRemove={() =>
              navigate(
                { ranking_type: "all", trend_category: null },
                true
              )
            }
          />
        )}

        {isTrendOnly && filters.trend_category && (
          <Chip
            label={`Tendencia: ${filters.trend_category}`}
            onRemove={() =>
              navigate({ trend_category: null }, true)
            }
          />
        )}
      </div>
    </div>
  );
}

/* =====================================================
   UI Components
===================================================== */

function Chip({ label, onRemove }: any) {
  return (
    <span
      className="
        inline-flex items-center gap-2
        rounded-full px-3 py-1 text-xs font-semibold
        bg-sky-100 text-sky-700
        dark:bg-[#14384F]
        dark:text-[#7DD3FC]
      "
    >
      {label}
      <button onClick={onRemove} className="hover:text-red-500">
        <X className="h-3 w-3" />
      </button>
    </span>
  );
}
