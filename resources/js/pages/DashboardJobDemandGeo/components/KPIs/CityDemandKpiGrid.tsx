import {
  MapPin,
  Layers,
  Award,
  ChevronDown,
} from "lucide-react";
import { router } from "@inertiajs/react";
import { useState, useRef, useEffect } from "react";

interface Career {
  id: number;
  name: string;
}

interface Meta {
  top_city?: string | null;
  top_career?: string | null;
  top5_concentration?: number;
}

interface Filters {
  career_id?: number | null;
  [key: string]: any;
}

interface CityDemandKpiGridProps {
  meta: Meta;
  careers?: Career[];
  filters?: Filters;
}

export default function CityDemandKpiGrid({
  meta,
  careers = [],
  filters = {},
}: CityDemandKpiGridProps) {

  const [open, setOpen] = useState<boolean>(false);
  const ref = useRef<HTMLDivElement | null>(null);

  const topCity = meta.top_city ?? "—";
  const topCareer = meta.top_career ?? "—";
  const top5 = meta.top5_concentration ?? 0;

  const handleCareerChange = (careerId: number | null) => {
    router.get(
      "/dashboard/job-demand-geo", // evita problema de tipado con route()
      {
        ...filters,
        career_id: careerId ?? null,
      },
      { preserveState: true }
    );

    setOpen(false);
  };

  /* cerrar al hacer click fuera */
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (ref.current && !ref.current.contains(event.target as Node)) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", handleClickOutside);

    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, []);

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 relative">

      {/* KPI Carrera líder */}

    <div ref={ref} className="relative z-[2000]">

        <div
          onClick={() => setOpen(!open)}
          className="
            cursor-pointer
            relative overflow-visible rounded-2xl border
            border-slate-200 dark:border-slate-700
            bg-orange-50 dark:bg-orange-900/20
            p-5 shadow-sm transition-all
            hover:shadow-md hover:-translate-y-0.5
          "
        >
          <div className="flex items-center justify-between">

            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Carrera líder
              </p>

              <p className="mt-1 text-xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                {topCareer}
                <ChevronDown className="h-4 w-4 opacity-60" />
              </p>
            </div>

            <div
              className="
                flex h-11 w-11 items-center justify-center
                rounded-xl bg-white/70 dark:bg-slate-900/40
                text-orange-500
              "
            >
              <Award className="h-5 w-5" />
            </div>

          </div>
        </div>

        {open && (
          <div
            className="
              absolute left-0 right-0 top-full mt-2
              z-50
              rounded-xl border border-slate-200 dark:border-slate-700
              bg-white dark:bg-slate-900
              shadow-2xl
              overflow-hidden
            "
          >
            <div className="max-h-64 overflow-y-auto">

              <button
                onClick={() => handleCareerChange(null)}
                className="
                  w-full text-left px-4 py-3 text-sm
                  hover:bg-slate-100 dark:hover:bg-slate-800
                  font-medium
                "
              >
                Todas las carreras
              </button>

              {careers.map((career, index) => (
                <button
                  key={career.id}
                  onClick={() => handleCareerChange(career.id)}
                  className="
                    w-full text-left px-4 py-3 text-sm
                    flex items-center justify-between
                    hover:bg-slate-100 dark:hover:bg-slate-800
                  "
                >
                  <span>
                    #{index + 1} {career.name}
                  </span>

                  {index === 0 && (
                    <span className="text-xs text-orange-500 font-semibold">
                      TOP
                    </span>
                  )}
                </button>
              ))}

            </div>
          </div>
        )}
      </div>

      {/* KPI Ciudad líder */}

      <div
        className="
          relative overflow-hidden rounded-2xl border
          border-slate-200 dark:border-slate-700
          bg-[#E6F7FD] dark:bg-[#0F2A3A]
          p-5 shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5
        "
      >
        <div className="flex items-center justify-between">

          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Ciudad líder
            </p>

            <p className="mt-1 text-2xl font-extrabold text-slate-900 dark:text-slate-100">
              {topCity}
            </p>
          </div>

          <div
            className="
              flex h-11 w-11 items-center justify-center
              rounded-xl bg-white/70 dark:bg-slate-900/40
              text-[#00B6E8]
            "
          >
            <MapPin className="h-5 w-5" />
          </div>

        </div>
      </div>

      {/* KPI Concentración */}

      <div
        className="
          relative overflow-hidden rounded-2xl border
          border-slate-200 dark:border-slate-700
          bg-indigo-50 dark:bg-indigo-900/20
          p-5 shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5
        "
      >
        <div className="flex items-center justify-between">

          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Concentración Top 5
            </p>

            <p className="mt-1 text-2xl font-extrabold text-slate-900 dark:text-slate-100">
              {top5.toFixed(1)}%
            </p>
          </div>

          <div
            className="
              flex h-11 w-11 items-center justify-center
              rounded-xl bg-white/70 dark:bg-slate-900/40
              text-indigo-500
            "
          >
            <Layers className="h-5 w-5" />
          </div>

        </div>
      </div>

    </div>
  );
}
