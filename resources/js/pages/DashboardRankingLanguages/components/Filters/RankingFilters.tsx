import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { ChevronDown, X } from "lucide-react";

/* =====================================================
   Component
===================================================== */
export default function RankingFilters() {
  const { filters, availableCareers } = usePage().props as {
    filters: {
      career?: string[];
    };
    availableCareers: {
      id: number;
      name: string;
      slug: string;
    }[];
  };

  const [openCareer, setOpenCareer] = useState(false);
  const careerRef = useRef<HTMLDivElement>(null);

  /* =====================================================
     🔥 NORMALIZAR SIEMPRE A ARRAY
  ===================================================== */
  const selectedCareers: string[] = Array.isArray(filters.career)
    ? filters.career
    : filters.career
    ? [filters.career]
    : [];

  /* =========================================
     Cerrar combo al hacer click afuera
  ========================================= */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (careerRef.current && !careerRef.current.contains(e.target as Node)) {
        setOpenCareer(false);
      }
    };

    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* =========================================
     Navegación central (MISMO PATRÓN)
  ========================================= */
  const navigate = (next: Record<string, any>, resetPage = false) => {
    const payload: any = {
      ...filters,
      ...next,
    };

    if (!payload.career?.length) delete payload.career;

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

  /* =========================================
     Toggle carrera
  ========================================= */
  const toggleCareer = (slug: string) => {
    navigate(
      {
        career: selectedCareers.includes(slug)
          ? selectedCareers.filter((c) => c !== slug)
          : [...selectedCareers, slug],
      },
      true
    );
  };

  /* =========================================
     Render
  ========================================= */
  return (
    <div className="space-y-4">
      {/* =========================
          FILTROS
      ========================= */}
      <div className="flex flex-wrap gap-4">
        {/* ===== CARRERA ISIL ===== */}
        <div ref={careerRef}>
          <Combo
            label="Carrera ISIL"
            open={openCareer}
            setOpen={setOpenCareer}
            items={availableCareers}
            selected={selectedCareers}
            onToggle={toggleCareer}
            getValue={(c: any) => c.slug}
            renderLabel={(c: any) => c.name}
          />
        </div>
      </div>

      {/* =========================
          CHIPS ACTIVOS
      ========================= */}
      <div className="flex flex-wrap gap-2">
        {selectedCareers.map((slug) => {
          const career = availableCareers.find((c) => c.slug === slug);

          return (
            <Chip
              key={slug}
              label={`Carrera: ${career?.name ?? slug}`}
              onRemove={() =>
                navigate(
                  {
                    career: selectedCareers.filter((c) => c !== slug),
                  },
                  true
                )
              }
            />
          );
        })}
      </div>
    </div>
  );
}

/* =====================================================
   UI Components (MISMO QUE CERTIFICACIONES)
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
    <div className="relative w-72">
      <button
        disabled={disabled}
        onClick={() => !disabled && setOpen(!open)}
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
        <span className="text-sm">
          {selected.length ? `${selected.length} seleccionados` : label}
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
