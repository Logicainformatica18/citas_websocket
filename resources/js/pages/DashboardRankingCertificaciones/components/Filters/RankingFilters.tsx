import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { ChevronDown, X } from "lucide-react";

/* =====================================================
   Helpers
===================================================== */
const formatArea = (v: string) => v;

/* =====================================================
   Component
===================================================== */
export default function RankingFilters() {
  const { filters, availableAreas, availableCareers } =
    usePage().props as any;

  const [openArea, setOpenArea] = useState(false);
  const [openCareer, setOpenCareer] = useState(false);

  const areaRef = useRef<HTMLDivElement>(null);
  const careerRef = useRef<HTMLDivElement>(null);

  /* =========================================
     Cerrar combos al hacer click afuera
  ========================================= */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (areaRef.current && !areaRef.current.contains(e.target as Node)) {
        setOpenArea(false);
      }
      if (
        careerRef.current &&
        !careerRef.current.contains(e.target as Node)
      ) {
        setOpenCareer(false);
      }
    };

    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* =========================================
     Navegación (limpia filtros vacíos)
  ========================================= */
  const navigate = (next: Partial<typeof filters>, resetPage = false) => {
    const payload: any = {
      ...filters,
      ...next,
    };

    if (!payload.area?.length) delete payload.area;
    if (!payload.career?.length) delete payload.career;

    router.get(
      "/dashboard/ranking-certificaciones",
      {
        ...payload,
        ...(resetPage ? { page: 1 } : {}),
      },
      { preserveState: true, replace: true }
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

  const removeValue = (key: "area" | "career", value: string) => {
    navigate(
      {
        [key]: (filters[key] ?? []).filter((v: string) => v !== value),
      },
      true
    );
  };

  return (
    <div className="space-y-4">
      {/* =========================
          COMBOS
      ========================= */}
      <div className="flex flex-wrap gap-4">
        {/* ===== ÁREA ===== */}
        <div ref={areaRef}>
          <Combo
            label="Área tecnológica"
            open={openArea}
            setOpen={setOpenArea}
            items={availableAreas}
            selected={filters.area ?? []}
            onToggle={(v: string) => toggleValue("area", v)}
            getValue={(v: string) => v}
            renderLabel={(v: string) => formatArea(v)}
          />
        </div>

        {/* ===== CARRERA ISIL ===== */}
        <div ref={careerRef}>
          <Combo
            label="Carrera ISIL"
            open={openCareer}
            setOpen={setOpenCareer}
            items={availableCareers}
            selected={filters.career ?? []}
            onToggle={(slug: string) => toggleValue("career", slug)}
            getValue={(c: any) => c.slug}
            renderLabel={(c: any) => c.name}
          />
        </div>
      </div>

      {/* =========================
          CHIPS
      ========================= */}
      <div className="flex flex-wrap gap-2">
        {filters.area?.map((a: string) => (
          <Chip
            key={`area-${a}`}
            label={`Área: ${formatArea(a)}`}
            onRemove={() => removeValue("area", a)}
          />
        ))}

        {filters.career?.map((slug: string) => {
          const career = availableCareers.find(
            (c: any) => c.slug === slug
          );

          return (
            <Chip
              key={`career-${slug}`}
              label={`Carrera: ${career?.name ?? slug}`}
              onRemove={() => removeValue("career", slug)}
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

function Combo({
  label,
  open,
  setOpen,
  items,
  selected,
  onToggle,
  getValue,
  renderLabel,
}: any) {
  return (
    <div className="relative w-64">
      <button
        onClick={() => setOpen(!open)}
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
        <ChevronDown className="h-4 w-4 text-gray-400 dark:text-slate-400" />
      </button>

      {open && (
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
                  hover:bg-sky-50
                  dark:hover:bg-[#14384F]
                  text-gray-700 dark:text-slate-200
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
      <button
        onClick={onRemove}
        className="hover:text-red-500 transition"
      >
        <X className="h-3 w-3" />
      </button>
    </span>
  );
}
