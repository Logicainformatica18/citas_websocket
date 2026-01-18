import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { ChevronDown, X } from "lucide-react";

/* =====================================================
   Component
===================================================== */
export default function RankingFilters() {
  const {
    filters,
    availableCategories,
    availableCareers,
  } = usePage().props as any;

  const [openCategory, setOpenCategory] = useState(false);
  const [openCareer, setOpenCareer] = useState(false);

  const categoryRef = useRef<HTMLDivElement>(null);
  const careerRef = useRef<HTMLDivElement>(null);

  /* =========================================
     Cerrar combos al hacer click afuera
  ========================================= */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (
        categoryRef.current &&
        !categoryRef.current.contains(e.target as Node)
      ) {
        setOpenCategory(false);
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
     Navegación centralizada
  ========================================= */
  const navigate = (next: Record<string, any>, resetPage = false) => {
    const payload: any = {
      ...filters,
      ...next,
    };

    if (!payload.category?.length) delete payload.category;
    if (!payload.career?.length) delete payload.career;

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

  const toggleCategory = (value: string) => {
    const current = filters.category ?? [];
    navigate(
      {
        category: current.includes(value)
          ? current.filter((v: string) => v !== value)
          : [...current, value],
      },
      true
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

  /* =========================================
     Render
  ========================================= */
  return (
    <div className="space-y-4">
      {/* =========================
          COMBOS
      ========================= */}
      <div className="flex flex-wrap gap-4">
        {/* ===== CATEGORÍA TECNOLÓGICA ===== */}
        <div ref={categoryRef}>
          <Combo
            label="Categoría tecnológica"
            open={openCategory}
            setOpen={setOpenCategory}
            items={availableCategories}
            selected={filters.category ?? []}
            onToggle={toggleCategory}
            getValue={(v: string) => v}
            renderLabel={(v: string) => v}
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
            onToggle={toggleCareer}
            getValue={(c: any) => c.slug}
            renderLabel={(c: any) => c.name}
          />
        </div>
      </div>

      {/* =========================
          CHIPS
      ========================= */}
      <div className="flex flex-wrap gap-2">
        {filters.category?.map((c: string) => (
          <Chip
            key={`category-${c}`}
            label={`Categoría: ${c}`}
            onRemove={() =>
              navigate(
                {
                  category: filters.category.filter(
                    (x: string) => x !== c
                  ),
                },
                true
              )
            }
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
          {selected.length ? `${selected.length} seleccionadas` : label}
        </span>
        <ChevronDown className="h-4 w-4 text-gray-400" />
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
