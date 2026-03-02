import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { ChevronDown, X } from "lucide-react";

/* ===================================================== */
export default function RankingFilters() {
  const {
    filters,
    availableCareers,
  } = usePage().props as any;

  const [openCareer, setOpenCareer] = useState(false);
  const careerRef = useRef<HTMLDivElement>(null);

  const activeCareers = filters.career ?? [];

  /* ========================================= */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (careerRef.current && !careerRef.current.contains(e.target as Node))
        setOpenCareer(false);
    };

    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* ========================================= */
  const navigate = (next: Record<string, any>, resetPage = false) => {
    const payload: any = {
      ...filters,
      ...next,
    };

    if (!payload.career?.length) delete payload.career;
    if (!payload.category?.length) delete payload.category;
    if (!payload.trend_category) delete payload.trend_category;

    router.get(
      "/dashboard/ranking/technologies",
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

  const toggleCareer = (slug: string) => {
    const current = filters.career ?? [];
    navigate(
      {
        career: current.includes(slug)
          ? current.filter((v: string) => v !== slug)
          : [...current, slug],
      },
      true
    );
  };

  const clearAllFilters = () => {
    router.get(
      "/dashboard/ranking/technologies",
      {
        ranking_type: "all",
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  const getCareerName = (slug: string) => {
    const found = availableCareers.find((c: any) => c.slug === slug);
    return found?.name ?? slug;
  };

  /* ========================================= */
  return (
    <div className="space-y-4">

      {/* ================= COMBOS ================= */}
      <div className="flex flex-wrap gap-4">

        {/* ===== CARRERA ISIL ===== */}
        <div ref={careerRef}>
          <div className="relative w-64">
            <button
              onClick={() => setOpenCareer(!openCareer)}
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
                {activeCareers.length
                  ? `${activeCareers.length} seleccionadas`
                  : "Carrera ISIL"}
              </span>
              <ChevronDown className="h-4 w-4 text-gray-400" />
            </button>

            {openCareer && (
              <div className="
                absolute z-20 mt-2 w-full
                rounded-xl border shadow-lg
                bg-white border-gray-200
                dark:bg-[#0F2A3A]
                dark:border-[#1E3A4A]
                max-h-60 overflow-auto
              ">
                {availableCareers.map((career: any) => (
                  <label
                    key={career.slug}
                    className="
                      flex items-center gap-2 px-4 py-2 text-sm cursor-pointer
                      hover:bg-sky-50 dark:hover:bg-[#14384F]
                    "
                  >
                    <input
                      type="checkbox"
                      checked={activeCareers.includes(career.slug)}
                      onChange={() => toggleCareer(career.slug)}
                      className="accent-[#1CBCE8]"
                    />
                    {career.name}
                  </label>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ================= CHIPS ================= */}
      {(activeCareers.length > 0) && (
        <div className="flex flex-wrap gap-2 items-center">

          {/* Chips de carreras */}
          {activeCareers.map((slug: string) => (
            <span
              key={slug}
              className="
                inline-flex items-center gap-2
                rounded-full px-3 py-1 text-xs font-semibold
                bg-sky-100 text-sky-700
                dark:bg-[#14384F]
                dark:text-[#7DD3FC]
              "
            >
              {getCareerName(slug)}
              <button
                onClick={() => toggleCareer(slug)}
                className="hover:text-red-500"
              >
                <X className="h-3 w-3" />
              </button>
            </span>
          ))}

          {/* Botón limpiar todo */}
          <button
            onClick={clearAllFilters}
            className="
              ml-2 text-xs font-semibold
              text-red-600 hover:underline
            "
          >
            Eliminar filtros
          </button>
        </div>
      )}
    </div>
  );
}
