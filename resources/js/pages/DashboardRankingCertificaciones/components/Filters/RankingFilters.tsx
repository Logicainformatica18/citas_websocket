import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { ChevronDown, X } from "lucide-react";

/* =====================================================
   Helpers
===================================================== */
const formatArea = (v: string) => v;

const RANKING_TYPES = [
  { value: "all", label: "Ranking general" },
  { value: "certification", label: "Solo certificaciones" },
  { value: "trend", label: "Solo tendencias" },
];

/* =====================================================
   Component
===================================================== */
export default function RankingFilters() {
  const {
    filters,
    availableAreas,
    availableCareers,
    availableTrendCategories,
  } = usePage().props as any;

  const [openArea, setOpenArea] = useState(false);
  const [openCareer, setOpenCareer] = useState(false);
  const [openType, setOpenType] = useState(false);

  const [search, setSearch] = useState(filters.search ?? "");

  const areaRef = useRef<HTMLDivElement>(null);
  const careerRef = useRef<HTMLDivElement>(null);
  const typeRef = useRef<HTMLDivElement>(null);

  const activeRankingType = filters.ranking_type ?? "all";
  const isTrendOnly = activeRankingType === "trend";

  /* =========================================
     Cerrar combos al hacer click afuera
  ========================================= */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (areaRef.current && !areaRef.current.contains(e.target as Node)) {
        setOpenArea(false);
      }
      if (careerRef.current && !careerRef.current.contains(e.target as Node)) {
        setOpenCareer(false);
      }
      if (typeRef.current && !typeRef.current.contains(e.target as Node)) {
        setOpenType(false);
      }
    };

    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* =========================================
     Navegación centralizada
  ========================================= */
  const navigate = (next: Record<string, any>, resetPage = false) => {
    const payload: any = {
      ...filters,
      ...next,
    };

    if (!payload.area?.length) delete payload.area;
    if (!payload.career?.length) delete payload.career;
    if (!payload.trend_category) delete payload.trend_category;
    if (!payload.ranking_type || payload.ranking_type === "all") {
      delete payload.ranking_type;
    }

    router.get(
      "/dashboard/ranking-certificaciones",
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

  const toggleValue = (key: "area" | "career", value: string) => {
    const current = filters[key] ?? [];

    navigate(
      {
        [key]: current.includes(value)
          ? current.filter((v: string) => v !== value)
          : [...current, value],
      },
      true
    );
  };

  const runSearch = (value: string) => {
    setSearch(value);

    navigate(
      {
        search: value || null,
      },
      true
    );
  };

  const rankingTypeLabel =
    RANKING_TYPES.find((t) => t.value === activeRankingType)?.label ??
    "Ranking general";

  return (
    <div className="space-y-4">

      {/* =========================
          FILTROS PRINCIPALES
      ========================= */}
      <div className="flex flex-wrap gap-4 items-stretch">

        {/* ===== BUSCADOR ===== */}
        <div className="flex-1 min-w-[260px]">
          <input
            type="text"
            placeholder="Buscar certificación..."
            value={search}
            onChange={(e) => runSearch(e.target.value)}
            className="
              w-full rounded-xl border px-4 py-2
              bg-white text-gray-700 border-gray-300
              dark:bg-[#0F2A3A]
              dark:text-slate-200
              dark:border-[#1E3A4A]
              focus:border-[#1CBCE8] focus:outline-none
            "
          />
        </div>

        {/* ===== ÁREA ===== */}
    

        {/* ===== CARRERA ===== */}
        <div ref={careerRef} className="flex-1 min-w-[220px]">
          <Combo
            label="Carrera ISIL"
            disabled={isTrendOnly}
            open={openCareer}
            setOpen={setOpenCareer}
            items={availableCareers}
            selected={filters.career ?? []}
            onToggle={(slug: string) => toggleValue("career", slug)}
            getValue={(c: any) => c.slug}
            renderLabel={(c: any) => c.name}
          />
        </div>
    <div ref={areaRef} className="flex-1 min-w-[220px]">
          <Combo
            label="Área tecnológica"
            disabled={isTrendOnly}
            open={openArea}
            setOpen={setOpenArea}
            items={availableAreas}
            selected={filters.area ?? []}
            onToggle={(v: string) => toggleValue("area", v)}
            getValue={(v: string) => v}
            renderLabel={(v: string) => formatArea(v)}
          />
        </div>
        {/* ===== CATEGORÍA TENDENCIAS ===== */}
        {isTrendOnly && (
          <div className="flex-1 min-w-[220px]">
            <select
              value={filters.trend_category ?? ""}
              onChange={(e) =>
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
              <option value="">Todas las categorías</option>
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
          CHIPS
      ========================= */}
      <div className="flex flex-wrap gap-2">

        {activeRankingType !== "all" && (
          <Chip
            label={`Tipo: ${rankingTypeLabel}`}
            onRemove={() =>
              navigate(
                {
                  ranking_type: "all",
                  trend_category: null,
                },
                true
              )
            }
          />
        )}

        {!isTrendOnly &&
          filters.area?.map((a: string) => (
            <Chip
              key={`area-${a}`}
              label={`Área: ${formatArea(a)}`}
              onRemove={() =>
                navigate(
                  {
                    area: filters.area.filter((x: string) => x !== a),
                  },
                  true
                )
              }
            />
          ))}

        {!isTrendOnly &&
          filters.career?.map((slug: string) => {
            const career = availableCareers.find(
              (c: any) => c.slug === slug
            );

            return (
              <Chip
                key={`career-${slug}`}
                label={`Carrera: ${career?.name ?? slug}`}
                onRemove={() =>
                  navigate(
                    {
                      career: filters.career.filter(
                        (x: string) => x !== slug
                      ),
                    },
                    true
                  )
                }
              />
            );
          })}

        {isTrendOnly && filters.trend_category && (
          <Chip
            label={`Categoría: ${filters.trend_category}`}
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

function Combo({
  label,
  open,
  setOpen,
  items,
  selected,
  onToggle,
  getValue,
  renderLabel,
  disabled = false,
}: any) {
  return (
    <div className="relative w-full">

      <button
        disabled={disabled}
        onClick={() => !disabled && setOpen(!open)}
        className="
          w-full rounded-xl border px-4 py-2
          text-left flex items-center justify-between
          bg-white text-gray-700 border-gray-300
          dark:bg-[#0F2A3A]
          dark:text-slate-200
          dark:border-[#1E3A4A]
          hover:border-[#1CBCE8]
        "
      >
        <span className="text-sm">
          {selected.length
            ? `${selected.length} seleccionados`
            : label}
        </span>

        <ChevronDown className="h-4 w-4 text-gray-400" />
      </button>

      {open && !disabled && (
        <div
          className="
            absolute z-20 mt-2 w-full
            rounded-xl border shadow-lg
            bg-white border-gray-200
            dark:bg-[#0F2A3A]
            dark:border-[#1E3A4A]
            max-h-60 overflow-auto
          "
        >
          {items.map((item: any) => {
            const value = getValue(item);

            return (
              <label
                key={value}
                className="
                  flex items-center gap-2 px-4 py-2 text-sm cursor-pointer
                  hover:bg-sky-50 dark:hover:bg-[#14384F]
                "
              >
                <input
                  type="checkbox"
                  checked={selected.includes(value)}
                  onChange={() => onToggle(value)}
                  className="accent-[#1CBCE8]"
                />

                {renderLabel(item)}
              </label>
            );
          })}
        </div>
      )}
    </div>
  );
}

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