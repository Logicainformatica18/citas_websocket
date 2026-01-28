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

const TREND_DOMAINS = [
  { value: "language", label: "Lenguajes" },
];

/* =====================================================
   Component
===================================================== */
export default function RankingFilters() {
  const { filters, availableCareers } = usePage().props as {
    filters: any;
    availableCareers: {
      id: number;
      name: string;
      slug: string;
    }[];
  };

  const [openType, setOpenType] = useState(false);
  const [openCareers, setOpenCareers] = useState(false);

  const typeRef = useRef<HTMLDivElement>(null);
  const careerRef = useRef<HTMLDivElement>(null);

  const activeRankingType = filters.ranking_type ?? "all";
  const trendDomain = filters.trend_domain ?? "language";
  const isTrendOnly = activeRankingType === "trend";

  const selectedCareers: string[] = filters.career ?? [];

  /* =========================================
     Cerrar combos al hacer click afuera
  ========================================= */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (typeRef.current && !typeRef.current.contains(e.target as Node)) {
        setOpenType(false);
      }
      if (careerRef.current && !careerRef.current.contains(e.target as Node)) {
        setOpenCareers(false);
      }
    };

    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* =========================================
     Navegación centralizada – LENGUAJES
  ========================================= */
  const navigate = (next: Record<string, any>, resetPage = false) => {
    const payload: Record<string, any> = {
      ...filters,
      ...next,
    };

    Object.keys(payload).forEach((k) => {
      if (
        payload[k] === null ||
        payload[k] === undefined ||
        payload[k] === "" ||
        (Array.isArray(payload[k]) && payload[k].length === 0)
      ) {
        delete payload[k];
      }
    });

    router.get(
      route("dashboard.ranking.languages"),
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
    RANKING_TYPES.find((t) => t.value === activeRankingType)?.label ??
    "Ranking general";

  const trendDomainLabel =
    TREND_DOMAINS.find((d) => d.value === trendDomain)?.label ??
    "Lenguajes";

  /* =========================================
     Toggle carrera
  ========================================= */
  const toggleCareer = (slug: string) => {
    const next = selectedCareers.includes(slug)
      ? selectedCareers.filter((c) => c !== slug)
      : [...selectedCareers, slug];

    navigate({ career: next }, true);
  };

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
        {/* <div ref={typeRef} className="relative w-72">
          <button
            onClick={() => setOpenType(!openType)}
            className="w-full rounded-xl border px-4 py-2 flex justify-between bg-white dark:bg-[#0F2A3A]"
          >
            <span className="text-sm">{rankingTypeLabel}</span>
            <ChevronDown className="h-4 w-4 opacity-60" />
          </button>

          {openType && (
            <div className="absolute z-20 mt-2 w-full rounded-xl border bg-white dark:bg-[#0F2A3A]">
              {RANKING_TYPES.map((type) => (
                <button
                  key={type.value}
                  onClick={() => {
                    navigate(
                      {
                        ranking_type: type.value,
                        ...(type.value === "trend"
                          ? { trend_domain: trendDomain }
                          : { trend_domain: null }),
                      },
                      true
                    );
                    setOpenType(false);
                  }}
                  className={`w-full px-4 py-2 text-left text-sm ${
                    activeRankingType === type.value
                      ? "font-semibold text-[#1CBCE8]"
                      : ""
                  }`}
                >
                  {type.label}
                </button>
              ))}
            </div>
          )}
        </div> */}

        {/* ===== FILTRO POR CARRERA ===== */}
        <div ref={careerRef} className="relative w-72">
          <button
            onClick={() => setOpenCareers(!openCareers)}
            className="w-full rounded-xl border px-4 py-2 flex justify-between bg-white dark:bg-[#0F2A3A]"
          >
            <span className="text-sm">
              {selectedCareers.length > 0
                ? `${selectedCareers.length} carrera(s)`
                : "Filtrar por carrera"}
            </span>
            <ChevronDown className="h-4 w-4 opacity-60" />
          </button>

          {openCareers && (
            <div className="absolute z-20 mt-2 w-full max-h-64 overflow-auto rounded-xl border bg-white dark:bg-[#0F2A3A]">
              {availableCareers.map((career) => {
                const active = selectedCareers.includes(career.slug);
                return (
                  <button
                    key={career.slug}
                    onClick={() => toggleCareer(career.slug)}
                    className={`w-full px-4 py-2 text-left text-sm ${
                      active ? "bg-sky-50 font-semibold text-[#1CBCE8]" : ""
                    }`}
                  >
                    {career.name}
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* =========================
          CHIPS ACTIVOS
      ========================= */}
      <div className="flex flex-wrap gap-2">
        {activeRankingType !== "all" && (
          <Chip
            label={`Tipo: ${rankingTypeLabel}`}
            onRemove={() =>
              navigate({ ranking_type: "all", trend_domain: null }, true)
            }
          />
        )}

        {selectedCareers.map((slug) => {
          const label =
            availableCareers.find((c) => c.slug === slug)?.name ?? slug;

          return (
            <Chip
              key={slug}
              label={`Carrera: ${label}`}
              onRemove={() =>
                toggleCareer(slug)
              }
            />
          );
        })}
      </div>
    </div>
  );
}

/* =====================================================
   UI Components
===================================================== */

function Chip({
  label,
  onRemove,
}: {
  label: string;
  onRemove: () => void;
}) {
  return (
    <span className="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold bg-sky-100 text-sky-700 dark:bg-[#14384F] dark:text-[#7DD3FC]">
      {label}
      <button onClick={onRemove}>
        <X className="h-3 w-3" />
      </button>
    </span>
  );
}
