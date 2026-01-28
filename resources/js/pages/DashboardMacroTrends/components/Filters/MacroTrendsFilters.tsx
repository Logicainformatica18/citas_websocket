import { router, usePage } from "@inertiajs/react";

interface Career {
  id: number;
  name: string;
  slug: string;
}

interface Props {
  regions?: string[];
  careers?: Career[];
}

export default function MacroTrendsFilters({
  regions = [],
  careers = [],
}: Props) {
  const { filters } = usePage().props as any;

  const updateFilter = (key: string, value: string | null) => {
    router.get(
      "/dashboard/indicators/macro-trends",
      {
        ...filters,
        [key]: value || undefined,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  return (
    <div className="bg-white dark:bg-[#0F2A3A] border rounded-xl p-4">
      <h3 className="font-semibold mb-4 text-slate-900 dark:text-slate-100">
        Filtros del indicador
      </h3>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">

        {/* ================= REGIÓN ================= */}
        <div>
          <label className="block text-xs font-semibold text-slate-500 mb-1">
            Región
          </label>

          <select
            value={filters?.region ?? ""}
            onChange={(e) =>
              updateFilter("region", e.target.value || null)
            }
            className="w-full rounded-lg border px-3 py-2 text-sm"
          >
            <option value="">Todas</option>
            {regions.map((region) => (
              <option key={region} value={region}>
                {region}
              </option>
            ))}
          </select>
        </div>

        {/* ================= CARRERA ================= */}
        <div>
          <label className="block text-xs font-semibold text-slate-500 mb-1">
            Carrera
          </label>

          <select
            value={filters?.career ?? ""}
            onChange={(e) =>
              updateFilter("career", e.target.value || null)
            }
            className="w-full rounded-lg border px-3 py-2 text-sm"
          >
            <option value="">Todas</option>
            {careers.map((career) => (
              <option key={career.id} value={career.slug}>
                {career.name}
              </option>
            ))}
          </select>
        </div>

      </div>
    </div>
  );
}
