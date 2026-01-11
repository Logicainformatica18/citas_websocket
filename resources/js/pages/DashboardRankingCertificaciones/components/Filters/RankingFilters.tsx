import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { ChevronDown, X } from "lucide-react";

/* =====================================================
   Helpers
===================================================== */
const formatArea = (v: string) =>
  ({
    cloud: "Cloud Computing",
    ai: "Inteligencia Artificial",
    data: "Data & Analytics",
    security: "Ciberseguridad",
    networks: "Redes",
  }[v] ?? v);

const formatCareer = (v: string) =>
  ({
    cloud: "Computación en la Nube",
    data_ai: "Ciencia de Datos e IA",
    cyber: "Ciberseguridad",
    software: "Desarrollo de Software",
    networks: "Redes y Comunicaciones",
  }[v] ?? v);

/* =====================================================
   Component
===================================================== */
export default function RankingFilters() {
  const { filters, availableAreas } = usePage().props as any;

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
     Navegación CENTRALIZADA
     (esto arregla la paginación)
  ========================================= */
  const navigate = (next: Partial<typeof filters>, resetPage = false) => {
    router.get(
      "/dashboard/ranking-certificaciones",
      {
        ...filters,
        ...next,
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
      true // reset page
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
        {/* ===== ÁREA TECNOLÓGICA ===== */}
        <div ref={areaRef}>
          <Combo
            label="Área tecnológica"
            open={openArea}
            setOpen={setOpenArea}
            items={availableAreas}
            selected={filters.area ?? []}
            onToggle={(v) => toggleValue("area", v)}
            format={formatArea}
          />
        </div>

        {/* ===== CARRERA ISIL ===== */}
        <div ref={careerRef}>
          <Combo
            label="Carrera ISIL"
            open={openCareer}
            setOpen={setOpenCareer}
            items={[
              "cloud",
              "data_ai",
              "cyber",
              "software",
              "networks",
            ]}
            selected={filters.career ?? []}
            onToggle={(v) => toggleValue("career", v)}
            format={formatCareer}
          />
        </div>
      </div>

      {/* =========================
          CHIPS ACTIVOS
      ========================= */}
      <div className="flex flex-wrap gap-2">
        {filters.area?.map((a: string) => (
          <Chip
            key={`area-${a}`}
            label={`Área: ${formatArea(a)}`}
            onRemove={() => removeValue("area", a)}
          />
        ))}

        {filters.career?.map((c: string) => (
          <Chip
            key={`career-${c}`}
            label={`Carrera: ${formatCareer(c)}`}
            onRemove={() => removeValue("career", c)}
          />
        ))}
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
  format,
}: any) {
  return (
    <div className="relative w-64">
      <button
        onClick={() => setOpen(!open)}
        className="
          w-full
          rounded-xl
          border
          bg-white
          px-4 py-2
          text-left
          flex items-center justify-between
          hover:border-[#1CBCE8]
        "
      >
        <span className="text-sm text-gray-700">
          {selected.length
            ? `${selected.length} seleccionados`
            : label}
        </span>
        <ChevronDown className="h-4 w-4 text-gray-400" />
      </button>

      {open && (
        <div className="absolute z-20 mt-2 w-full rounded-xl border bg-white shadow-lg max-h-60 overflow-auto">
          {items.map((item: string) => (
            <label
              key={item}
              className="flex items-center gap-2 px-4 py-2 text-sm hover:bg-sky-50 cursor-pointer"
            >
              <input
                type="checkbox"
                checked={selected.includes(item)}
                onChange={() => onToggle(item)}
                className="accent-[#1CBCE8]"
              />
              {format(item)}
            </label>
          ))}
        </div>
      )}
    </div>
  );
}

function Chip({ label, onRemove }: any) {
  return (
    <span className="
      inline-flex items-center gap-2
      rounded-full bg-sky-100
      px-3 py-1 text-xs font-semibold text-sky-700
    ">
      {label}
      <button onClick={onRemove}>
        <X className="h-3 w-3" />
      </button>
    </span>
  );
}
